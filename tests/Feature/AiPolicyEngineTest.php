<?php

namespace Tests\Feature;

use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Modules\AI\Models\AiApproval;
use Modules\AI\Models\AiAuditLog;
use Modules\AI\Models\AiCreditLedger;
use Modules\AI\Models\AiEscalation;
use Modules\AI\Models\AiPolicy;
use Modules\AI\Models\AiPolicyRule;
use Modules\AI\Models\AiTenantSetting;
use Modules\AI\Services\Gateway\AiGateway;
use Modules\AI\Services\Gateway\AiGenerateRequest;
use Modules\AI\Services\Guardrails\PolicyEngine;
use Modules\AI\Services\Settings\AiSettingProvisioner;
use Modules\AI\Services\Settings\AiTenantSettingDefaults;
use Modules\AI\Services\Settings\TenantSettingService;
use Tests\Concerns\InteractsWithPpdbTestDatabase;
use Tests\TestCase;

/**
 * T-A020 — PolicyEngine (Sharia Guardrails), rule-based murni TANPA LLM:
 *  - blacklist tenant → pengalihan santun TANPA HTTP + audit ditolak_policy
 *    + eskalasi terredaksi;
 *  - edukatif_only + pola hukum → Tanya Ustadz; informatif → lolos provider;
 *  - jam layanan hanya mengikat kanal eksternal (wa/chat);
 *  - fitur_per_peran × peran spatie;
 *  - tenant tanpa setting aktif → "belum dikonfigurasi";
 *  - metering 0 kredit namespace 'policy-' tanpa menabrak dedup jalur lain;
 *  - isolasi rule antar tenant;
 *  - suite ≥40 kasus teknis-linguistik dari policy_cases.json.
 */
class AiPolicyEngineTest extends TestCase
{
    use InteractsWithPpdbTestDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->ensurePpdbTestingSchemaReady([
            'ai_tenant_setting', 'ai_approval', 'ai_policy', 'ai_policy_rule',
            'ai_escalation', 'ai_audit_log', 'ai_model_config', 'ai_credit_ledger',
        ]);
        $this->truncatePpdbTables(['domains', 'tenants', 'ai_credit_ledger', 'ai_tenant_budget']);
        Cache::flush();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        if (function_exists('tenancy') && tenancy()->initialized) {
            tenancy()->end();
        }

        parent::tearDown();
    }

    // ------------------------------------------------------------------
    // Pendukung
    // ------------------------------------------------------------------

    private function siapkanTenant(string $prefix): Tenant
    {
        $tenant = $this->createRuntimeTenant($prefix, 'Pesantren '.$prefix);
        tenancy()->initialize($tenant);

        $this->seedTenantRow($tenant);
        $this->seed(RolesAndPermissionsSeeder::class);
        app(AiSettingProvisioner::class)->provision((string) $tenant->id);

        return $tenant;
    }

    private function buatUser(string $email, array $permissions = [], array $roles = []): User
    {
        $user = User::create([
            'name' => 'Uji '.$email,
            'email' => $email,
            'password' => 'password',
        ]);

        foreach ($permissions as $permission) {
            $user->givePermissionTo($permission);
        }

        foreach ($roles as $role) {
            $user->assignRole($role);
        }

        return $user;
    }

    /**
     * Aktifkan setting dengan override tertentu. Field sensitif → otomatis
     * melewati alur approval kyai (T-A010) sampai versi benar-benar AKTIF.
     */
    private function terapkanSetting(array $overrides): void
    {
        $admin = $this->buatUser('admin-'.uniqid().'@test.local', ['ai.setting.kelola']);
        $kyai = $this->buatUser('kyai-'.uniqid().'@test.local', ['ai.setting.setujui']);
        $service = app(TenantSettingService::class);

        $payload = array_replace(AiTenantSettingDefaults::konservatif(), $overrides);

        $draf = $service->simpanDraf($payload, $admin);
        $hasil = $service->ajukan($draf->versi, $admin);

        if ($hasil->status === AiTenantSetting::STATUS_PENDING_APPROVAL) {
            $approval = AiApproval::query()
                ->where('ai_tenant_setting_id', $draf->id)
                ->firstOrFail();
            $service->putuskan($approval->id, $kyai, true);
        }
    }

    private function gateway(): AiGateway
    {
        return app(AiGateway::class);
    }

    private function request(
        string $prompt,
        string $agent = 'uji',
        string $channel = 'web',
        ?int $userId = null,
        ?string $key = null,
    ): AiGenerateRequest {
        return new AiGenerateRequest(
            prompt: $prompt,
            agent: $agent,
            tier: 'ekonomis',
            channel: $channel,
            userId: $userId,
            idempotencyKey: $key,
        );
    }

    private function fakeOllamaSukses(): void
    {
        Http::fake([
            '*/api/generate' => Http::response([
                'response' => 'Baik, berikut jawabannya.',
                'prompt_eval_count' => 10,
                'eval_count' => 5,
            ]),
        ]);
    }

    // ------------------------------------------------------------------
    // (1) Blacklist tenant → blokir TANPA HTTP + audit + eskalasi terredaksi
    // ------------------------------------------------------------------

    public function test_blacklist_tenant_memblokir_tanpa_http_dengan_audit_dan_eskalasi_terredaksi(): void
    {
        $this->siapkanTenant('aipbl');
        $this->terapkanSetting(['blacklist_topik' => ['topik-uji-terlarang']]);

        Http::fake();

        $result = $this->gateway()->generate($this->request(
            'Tolong bahas topik-uji-terlarang, balas ke 081234567890 ya.',
            key: 'kunci-bl-1',
        ));

        $this->assertTrue($result->ditolakPolicy);
        $this->assertFalse($result->degraded, 'Penolakan kebijakan BUKAN degradasi');
        $this->assertSame((string) config('ai.guardrails.pesan.blacklist_topik'), $result->text);

        // AC utama: NOL HTTP ke provider.
        Http::assertNothingSent();

        // Audit status ditolak_policy + rule yang menyala tercatat.
        $audit = AiAuditLog::query()->where('status', AiAuditLog::STATUS_DITOLAK_POLICY)->first();
        $this->assertNotNull($audit);
        $this->assertSame('blacklist_topik', $audit->keputusan_kebijakan['policy']['kategori']);
        $this->assertNotNull($audit->keputusan_kebijakan['policy']['rule_id']);

        // Eskalasi Tanya Ustadz: tertulis dan TERREDAKSI (nomor HP hilang).
        $eskalasi = AiEscalation::query()->where('kategori', 'blacklist_topik')->first();
        $this->assertNotNull($eskalasi);
        $this->assertSame(AiEscalation::STATUS_BARU, $eskalasi->status);
        $this->assertStringNotContainsString('081234567890', $eskalasi->pertanyaan_ringkas);
        $this->assertStringContainsString('[PII:HP', $eskalasi->pertanyaan_ringkas);
    }

    // ------------------------------------------------------------------
    // (2) Level fatwa: edukatif_only mengalihkan; informatif lolos
    // ------------------------------------------------------------------

    public function test_edukatif_only_pola_hukum_dialihkan_dengan_eskalasi(): void
    {
        $this->siapkanTenant('aipfh'); // default = edukatif_only

        Http::fake();

        $result = $this->gateway()->generate($this->request('Apa hukum melakukan kegiatan X?'));

        $this->assertTrue($result->ditolakPolicy);
        $this->assertSame((string) config('ai.guardrails.pesan.tanya_ustadz'), $result->text);
        Http::assertNothingSent();

        $this->assertSame(1, AiEscalation::query()->where('kategori', 'level_fatwa')->count());
    }

    public function test_level_informatif_meloloskan_pertanyaan_berpola_hukum_ke_provider(): void
    {
        $this->siapkanTenant('aipfi');
        $this->terapkanSetting(['level_fatwa' => 'informatif']);

        $this->fakeOllamaSukses();

        $result = $this->gateway()->generate($this->request('Apa hukum melakukan kegiatan X?'));

        $this->assertFalse($result->ditolakPolicy);
        $this->assertFalse($result->degraded);
        $this->assertSame('Baik, berikut jawabannya.', $result->text);
        Http::assertSentCount(1);
    }

    // ------------------------------------------------------------------
    // (3) Jam layanan: hanya kanal eksternal (wa/chat)
    // ------------------------------------------------------------------

    public function test_di_luar_jam_layanan_kanal_wa_ditolak_kanal_web_staf_tetap_dilayani(): void
    {
        $this->siapkanTenant('aipjm');
        $this->terapkanSetting([
            'jam_layanan' => ['mulai' => '08:00', 'selesai' => '17:00'],
        ]);

        Carbon::setTestNow(Carbon::parse('2026-06-10 20:30:00'));
        $this->fakeOllamaSukses();

        // Kanal wa (wali) → pesan di luar jam, tanpa HTTP.
        $resultWa = $this->gateway()->generate($this->request('Berapa tagihan saya?', channel: 'wa'));
        $this->assertTrue($resultWa->ditolakPolicy);
        $this->assertStringContainsString('di luar jam operasional', $resultWa->text);
        Http::assertNothingSent();

        // Kanal web (staf internal) → bebas jam, dilayani provider.
        $resultWeb = $this->gateway()->generate($this->request('Berapa tagihan saya?', channel: 'web'));
        $this->assertFalse($resultWeb->ditolakPolicy);
        Http::assertSentCount(1);
    }

    // ------------------------------------------------------------------
    // (4) Fitur per peran
    // ------------------------------------------------------------------

    public function test_fitur_tidak_diizinkan_untuk_peran_ditolak_lalu_lolos_setelah_diaktifkan(): void
    {
        $this->siapkanTenant('aipfp');
        $wali = $this->buatUser('wali-fp@test.local', roles: ['wali']);

        $this->fakeOllamaSukses();

        // Default konservatif: fitur_per_peran.wali.chatbot = false → tolak.
        $result = $this->gateway()->generate($this->request(
            'Berapa tagihan anak saya?',
            agent: 'chatbot_wali',
            userId: $wali->id,
        ));

        $this->assertTrue($result->ditolakPolicy);
        $this->assertSame((string) config('ai.guardrails.pesan.fitur_tidak_diizinkan'), $result->text);
        Http::assertNothingSent();

        // Aktifkan fitur (sensitif → approval kyai) → dilayani.
        $fitur = AiTenantSettingDefaults::konservatif()['fitur_per_peran'];
        $fitur['wali']['chatbot'] = true;
        $this->terapkanSetting(['fitur_per_peran' => $fitur]);

        $result2 = $this->gateway()->generate($this->request(
            'Berapa tagihan anak saya?',
            agent: 'chatbot_wali',
            userId: $wali->id,
        ));

        $this->assertFalse($result2->ditolakPolicy);
        Http::assertSentCount(1);
    }

    // ------------------------------------------------------------------
    // (5) Tanpa setting aktif → belum dikonfigurasi
    // ------------------------------------------------------------------

    public function test_tenant_tanpa_setting_aktif_ditolak_belum_dikonfigurasi(): void
    {
        $tenant = $this->createRuntimeTenant('aipns', 'Pesantren Tanpa Setting');
        tenancy()->initialize($tenant); // TANPA AiSettingProvisioner

        Http::fake();

        $result = $this->gateway()->generate($this->request('Assalamualaikum'));

        $this->assertTrue($result->ditolakPolicy);
        $this->assertSame((string) config('ai.guardrails.pesan.belum_dikonfigurasi'), $result->text);
        Http::assertNothingSent();

        $audit = AiAuditLog::query()->where('status', AiAuditLog::STATUS_DITOLAK_POLICY)->first();
        $this->assertNotNull($audit);
        $this->assertSame('belum_dikonfigurasi', $audit->keputusan_kebijakan['policy']['kategori']);
    }

    // ------------------------------------------------------------------
    // (6) Metering: 0 kredit, namespace 'policy-', tanpa menabrak dedup lain
    // ------------------------------------------------------------------

    public function test_penolakan_policy_termeter_nol_kredit_di_namespace_policy(): void
    {
        $tenant = $this->siapkanTenant('aipmt');
        $this->terapkanSetting(['blacklist_topik' => ['topik-uji-terlarang']]);

        $this->fakeOllamaSukses();

        // Blokir dengan kunci K → ledger 'policy-K', 0 kredit.
        $blokir = $this->gateway()->generate($this->request(
            'bahas topik-uji-terlarang', key: 'kunci-meter',
        ));
        $this->assertTrue($blokir->ditolakPolicy);

        $barisPolicy = AiCreditLedger::query()
            ->where('tenant_id', (string) $tenant->id)
            ->where('idempotency_key', 'policy-kunci-meter')
            ->first();
        $this->assertNotNull($barisPolicy, 'Penolakan policy WAJIB termeter (pagar #5)');
        $this->assertSame(0.0, (float) $barisPolicy->jumlah_kredit);

        // Kunci K yang sama pada jalur LOLOS tidak ter-dedup ke baris policy.
        $lolos = $this->gateway()->generate($this->request(
            'Berapa tagihan saya bulan ini?', key: 'kunci-meter',
        ));
        $this->assertFalse($lolos->ditolakPolicy);

        $this->assertNotNull(
            AiCreditLedger::query()
                ->where('tenant_id', (string) $tenant->id)
                ->where('idempotency_key', 'kunci-meter')
                ->first(),
            'Jalur sukses harus punya baris ledger sendiri (namespace berbeda)',
        );
    }

    // ------------------------------------------------------------------
    // (7) Isolasi tenant
    // ------------------------------------------------------------------

    public function test_rule_blacklist_tenant_a_tidak_berlaku_di_tenant_b(): void
    {
        $prompt = 'Saya ingin membahas topik-uji-terlarang hari ini.';

        $this->siapkanTenant('aipia');
        $this->terapkanSetting(['blacklist_topik' => ['topik-uji-terlarang']]);

        Http::fake();
        $diA = $this->gateway()->generate($this->request($prompt));
        $this->assertTrue($diA->ditolakPolicy, 'Tenant A: blacklist harus menyala');
        Http::assertNothingSent();
        tenancy()->end();

        $this->siapkanTenant('aipib'); // tanpa blacklist
        $this->fakeOllamaSukses();
        $diB = $this->gateway()->generate($this->request($prompt));
        $this->assertFalse($diB->ditolakPolicy, 'Rule tenant A bocor ke tenant B');
        Http::assertSentCount(1);
    }

    // ------------------------------------------------------------------
    // (8) Suite kasus teknis-linguistik dari policy_cases.json (≥40 kasus)
    // ------------------------------------------------------------------

    public function test_suite_policy_cases_json(): void
    {
        $berkas = base_path('Modules/AI/database/seeders/data/policy_cases.json');
        $kasus = json_decode((string) file_get_contents($berkas), true)['kasus'] ?? [];

        $this->assertGreaterThanOrEqual(40, count($kasus), 'Suite kebijakan minimal 40 kasus');

        $tenants = [];

        foreach (['edukatif_only', 'informatif'] as $level) {
            $tenant = $this->siapkanTenant('aipc'.substr($level, 0, 2));
            $this->terapkanSetting([
                'level_fatwa' => $level,
                'blacklist_topik' => ['topik-uji-terlarang', 'frasa uji rahasia'],
            ]);

            // Rule regex tambahan (pola teknis netral) di policy tenant.
            $policy = AiPolicy::query()
                ->where('nama', AiPolicy::NAMA_KEBIJAKAN_PESANTREN)
                ->firstOrFail();
            AiPolicyRule::query()->create([
                'policy_id' => $policy->id,
                'tipe' => AiPolicyRule::TIPE_BLACKLIST_TOPIK,
                'pola' => ['keyword' => [], 'regex' => ['/uji-regex-\d+/iu']],
                'prioritas' => 50,
                'aktif' => true,
            ]);

            $tenants[$level] = $tenant;
            tenancy()->end();
        }

        $engine = app(PolicyEngine::class);

        foreach (['edukatif_only', 'informatif'] as $level) {
            $tenant = $tenants[$level];
            tenancy()->initialize($tenant);

            foreach ($kasus as $i => $k) {
                if (($k['level_fatwa'] ?? 'edukatif_only') !== $level) {
                    continue;
                }

                $keputusan = $engine->pre(
                    new AiGenerateRequest(prompt: (string) $k['kalimat'], agent: 'uji'),
                    (string) $tenant->id,
                );

                $label = sprintf('kasus #%d [%s] "%s"', $i, $level, $k['kalimat']);

                if ($k['expected'] === 'blokir') {
                    $this->assertFalse($keputusan->boleh, "{$label} seharusnya BLOKIR");
                    $this->assertSame($k['kategori'], $keputusan->kategori, "{$label} kategori salah");
                    $this->assertNotNull($keputusan->responsTemplate, "{$label} tanpa respons template");
                } else {
                    $this->assertTrue(
                        $keputusan->boleh,
                        "{$label} seharusnya LOLOS (terblokir kategori {$keputusan->kategori}: {$keputusan->alasan})",
                    );
                }
            }

            tenancy()->end();
        }
    }
}
