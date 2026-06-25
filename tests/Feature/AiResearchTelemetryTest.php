<?php

namespace Tests\Feature;

use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Filament\Facades\Filament;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Livewire\Livewire;
use Modules\AI\Filament\Pages\SuaraPasar;
use Modules\AI\Models\AiApproval;
use Modules\AI\Models\AiEscalation;
use Modules\AI\Models\AiResearchEvent;
use Modules\AI\Models\AiTenantSetting;
use Modules\AI\Services\Gateway\AiGenerateRequest;
use Modules\AI\Services\Guardrails\PolicyEngine;
use Modules\AI\Services\Research\ResearchTelemetry;
use Modules\AI\Services\Settings\AiSettingProvisioner;
use Modules\AI\Services\Settings\AiTenantSettingDefaults;
use Modules\AI\Services\Settings\TenantSettingService;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\Concerns\InteractsWithPpdbTestDatabase;
use Tests\TestCase;

/**
 * T-A013 — telemetri riset OPT-IN + dashboard "Suara Pasar":
 *  (a) opt-in → snapshot terekam saat aktivasi setting; eskalasi menambah
 *      event kategori (idempoten per kunci request);
 *  (b) opt-out (default) → NOL event di semua jalur (uji negatif PRD);
 *  (c) cabut izin → event lama TERHAPUS + aliran berhenti;
 *  (d) payload tanpa isi pertanyaan/PII/daftar kustom (assert kunci);
 *  (e) rekamRating: tervalidasi 1–5 + no-op saat opt-out;
 *  (f) halaman platform: super_admin ok, user/panel tenant tidak;
 *  (g) isolasi: cabut izin tenant A tidak menyentuh event tenant B.
 */
class AiResearchTelemetryTest extends TestCase
{
    use InteractsWithPpdbTestDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->ensurePpdbTestingSchemaReady([
            'ai_tenant_setting', 'ai_approval', 'ai_escalation',
            'ai_policy', 'ai_policy_rule', 'ai_research_event',
        ]);
        $this->truncatePpdbTables(['domains', 'tenants']);

        // Baseline gating modul T-A012: registry kosong = modul tampil.
        DB::table('module_registry')->delete();
        Cache::flush();
    }

    protected function tearDown(): void
    {
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

    /** Aktifkan setting dengan override (alur approval bila sensitif). */
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

    /** Picu eskalasi via PolicyEngine (edukatif_only + pola tanya hukum). */
    private function picuEskalasi(Tenant $tenant, string $prompt, ?string $key = null): void
    {
        $keputusan = app(PolicyEngine::class)->pre(
            new AiGenerateRequest(prompt: $prompt, agent: 'uji', idempotencyKey: $key),
            (string) $tenant->id,
        );

        $this->assertFalse($keputusan->boleh, 'Pertanyaan pola hukum harus terblokir (prasyarat uji)');
        $this->assertTrue($keputusan->eskalasi, 'Blokir level_fatwa harus bereskalasi (prasyarat uji)');
    }

    private function eventTenant(Tenant $tenant): Builder
    {
        return AiResearchEvent::query()->where('tenant_id', (string) $tenant->id);
    }

    // ------------------------------------------------------------------
    // (a) Opt-in: snapshot saat aktivasi + event kategori eskalasi
    // ------------------------------------------------------------------

    public function test_optin_merekam_snapshot_saat_setting_aktif_berganti(): void
    {
        $tenant = $this->siapkanTenant('airso');

        // optin_riset non-sensitif → langsung aktif → hook lifecycle.
        $this->terapkanSetting(['optin_riset' => true]);

        $snapshot = $this->eventTenant($tenant)
            ->where('jenis', AiResearchEvent::JENIS_SNAPSHOT_KONFIGURASI)
            ->get();

        $this->assertCount(1, $snapshot, 'Aktivasi setting opt-in harus merekam tepat 1 snapshot');

        $payload = $snapshot->first()->payload;
        $this->assertSame('aswaja_nu', $payload['preset_manhaj']);
        $this->assertSame('edukatif_only', $payload['level_fatwa']);
        $this->assertSame('tanpa_akses', $payload['mode_akses_santri']);
        $this->assertSame([], $payload['fitur_aktif'], 'Default konservatif: tanpa fitur aktif');
        $this->assertSame(2, $payload['jumlah_scope_aktif'], 'Default: tagihan+jadwal');
        $this->assertSame(
            now()->startOfMonth()->toDateString(),
            $snapshot->first()->periode->toDateString(),
        );
    }

    public function test_optin_eskalasi_menambah_event_kategori_dan_idempoten_per_kunci(): void
    {
        $tenant = $this->siapkanTenant('airse');
        $this->terapkanSetting(['optin_riset' => true]);

        $this->picuEskalasi($tenant, 'Apa hukum melakukan kegiatan X?', 'kunci-riset-1');
        // Retry kunci sama: eskalasi ter-dedup → statistik TIDAK menggandakan.
        $this->picuEskalasi($tenant, 'Apa hukum melakukan kegiatan X?', 'kunci-riset-1');

        $events = $this->eventTenant($tenant)
            ->where('jenis', AiResearchEvent::JENIS_ESKALASI_KATEGORI)
            ->get();

        $this->assertCount(1, $events, 'Retry idempoten tidak boleh menggandakan event riset');
        $this->assertSame('level_fatwa', $events->first()->payload['kategori']);

        // Kunci berbeda = eskalasi baru = event baru.
        $this->picuEskalasi($tenant, 'Bolehkah melakukan kegiatan Y?', 'kunci-riset-2');
        $this->assertSame(2, $this->eventTenant($tenant)
            ->where('jenis', AiResearchEvent::JENIS_ESKALASI_KATEGORI)->count());
    }

    // ------------------------------------------------------------------
    // (b) Opt-out (default) → NOL event (uji negatif eksplisit)
    // ------------------------------------------------------------------

    public function test_optout_default_tidak_menghasilkan_satu_pun_event(): void
    {
        $tenant = $this->siapkanTenant('airsn');

        // Jalur 1: aktivasi setting (non-sensitif) — tanpa opt-in.
        $this->terapkanSetting(['jam_layanan' => ['mulai' => '07:00', 'selesai' => '21:00']]);

        // Jalur 2: eskalasi tetap tertulis ke antrean ustadz…
        $this->picuEskalasi($tenant, 'Apa hukum melakukan kegiatan X?', 'kunci-noevent');
        $this->assertSame(1, AiEscalation::query()->count(), 'Antrean ustadz tetap berjalan tanpa opt-in');

        // Jalur 3: rating via API service.
        app(ResearchTelemetry::class)->rekamRating((string) $tenant->id, 'chatbot_wali', 5);

        // …tapi telemetri riset NOL baris.
        $this->assertSame(0, $this->eventTenant($tenant)->count(), 'Tenant opt-out WAJIB nol event riset');
        $this->assertFalse(app(ResearchTelemetry::class)->aktif((string) $tenant->id));
    }

    // ------------------------------------------------------------------
    // (c) Cabut izin → event lama terhapus + aliran berhenti
    // ------------------------------------------------------------------

    public function test_cabut_izin_menghapus_event_lama_dan_menghentikan_aliran(): void
    {
        $tenant = $this->siapkanTenant('airsc');

        $this->terapkanSetting(['optin_riset' => true]);
        $this->picuEskalasi($tenant, 'Apa hukum melakukan kegiatan X?', 'kunci-cabut-1');
        app(ResearchTelemetry::class)->rekamRating((string) $tenant->id, 'chatbot_wali', 4);

        $this->assertSame(3, $this->eventTenant($tenant)->count(), 'Prasyarat: snapshot+eskalasi+rating');

        // Cabut izin: versi baru optin_riset=false menggantikan yang true.
        $this->terapkanSetting(['optin_riset' => false]);

        $this->assertSame(0, $this->eventTenant($tenant)->count(), 'Cabut izin WAJIB menghapus seluruh event tenant');

        // Aliran berhenti: eskalasi/rating berikutnya tidak menulis apa pun.
        $this->picuEskalasi($tenant, 'Bolehkah melakukan kegiatan Z?', 'kunci-cabut-2');
        app(ResearchTelemetry::class)->rekamRating((string) $tenant->id, 'chatbot_wali', 3);

        $this->assertSame(0, $this->eventTenant($tenant)->count(), 'Setelah cabut izin aliran event harus berhenti');
    }

    // ------------------------------------------------------------------
    // (d) Payload bebas isi pertanyaan / PII / daftar kustom
    // ------------------------------------------------------------------

    public function test_payload_event_tidak_memuat_teks_pertanyaan_pii_atau_daftar_kustom(): void
    {
        $tenant = $this->siapkanTenant('airsp');

        // blacklist_topik sensitif → lewat alur approval; isi kustom ini
        // TIDAK boleh bocor ke snapshot riset.
        $this->terapkanSetting([
            'optin_riset' => true,
            'blacklist_topik' => ['topik-rahasia-pesantren-xyz'],
        ]);

        $promptPii = 'Apa hukum hal ini? Hubungi saya di 081234567890, nama anak saya Fulan bin Fulan.';
        $this->picuEskalasi($tenant, $promptPii, 'kunci-pii-1');

        $snapshot = $this->eventTenant($tenant)
            ->where('jenis', AiResearchEvent::JENIS_SNAPSHOT_KONFIGURASI)
            ->firstOrFail();
        $eskalasi = $this->eventTenant($tenant)
            ->where('jenis', AiResearchEvent::JENIS_ESKALASI_KATEGORI)
            ->firstOrFail();

        // Kunci snapshot PERSIS 5 field agregat — tanpa blacklist/whitelist.
        $kunciSnapshot = array_keys($snapshot->payload);
        sort($kunciSnapshot);
        $this->assertSame(
            ['fitur_aktif', 'jumlah_scope_aktif', 'level_fatwa', 'mode_akses_santri', 'preset_manhaj'],
            $kunciSnapshot,
        );

        // Kunci event eskalasi PERSIS ['kategori'].
        $this->assertSame(['kategori'], array_keys($eskalasi->payload));

        // Tidak ada serpihan teks bebas/PII/daftar kustom di payload mana pun.
        $semuaJson = $this->eventTenant($tenant)->get()
            ->map(fn (AiResearchEvent $e): string => (string) json_encode($e->payload, JSON_UNESCAPED_UNICODE))
            ->implode(' ');

        $this->assertStringNotContainsString('081234567890', $semuaJson, 'Nomor HP bocor ke telemetri');
        $this->assertStringNotContainsString('Fulan', $semuaJson, 'Nama bocor ke telemetri');
        $this->assertStringNotContainsString('Apa hukum', $semuaJson, 'Teks pertanyaan bocor ke telemetri');
        $this->assertStringNotContainsString('topik-rahasia-pesantren-xyz', $semuaJson, 'Blacklist kustom bocor ke telemetri');
    }

    // ------------------------------------------------------------------
    // (e) rekamRating: validasi rentang + terekam saat opt-in
    // ------------------------------------------------------------------

    public function test_rekam_rating_terekam_saat_optin_dan_menolak_rentang_liar(): void
    {
        $tenant = $this->siapkanTenant('airsr');
        $this->terapkanSetting(['optin_riset' => true]);

        app(ResearchTelemetry::class)->rekamRating((string) $tenant->id, 'chatbot_wali', 5);

        $event = $this->eventTenant($tenant)
            ->where('jenis', AiResearchEvent::JENIS_RATING_JAWABAN)
            ->firstOrFail();
        $this->assertSame(['agent' => 'chatbot_wali', 'rating' => 5], $event->payload);

        $this->expectException(InvalidArgumentException::class);
        app(ResearchTelemetry::class)->rekamRating((string) $tenant->id, 'chatbot_wali', 6);
    }

    // ------------------------------------------------------------------
    // (f) Halaman platform "Suara Pasar": super_admin ok, tenant tidak
    // ------------------------------------------------------------------

    public function test_halaman_suara_pasar_hanya_super_admin_panel_platform(): void
    {
        // Konteks CENTRAL (tanpa tenancy) — panel platform 'admin'.
        Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $superAdmin = $this->buatUser('super-'.uniqid().'@platform.local', [], ['super_admin']);
        $userBiasa = $this->buatUser('biasa-'.uniqid().'@platform.local');

        Filament::setCurrentPanel(Filament::getPanel('admin'));

        // Tanpa login: tertutup.
        $this->assertFalse(SuaraPasar::canAccess());

        // User platform tanpa role super_admin: tertutup (mount → 403).
        $this->actingAs($userBiasa);
        $this->assertFalse(SuaraPasar::canAccess());

        try {
            $this->withoutExceptionHandling();
            Livewire::test(SuaraPasar::class);
            $this->fail('User tanpa role super_admin seharusnya ditolak (403).');
        } catch (HttpException $e) {
            $this->assertSame(403, $e->getStatusCode());
        } finally {
            $this->withExceptionHandling();
        }

        // super_admin: terbuka + halaman dapat dirender.
        $this->actingAs($superAdmin);
        $this->assertTrue(SuaraPasar::canAccess());
        Livewire::test(SuaraPasar::class)->assertOk();

        // Panel TENANT: tertutup walau pemegang role super_admin (halaman
        // ini khusus panel platform/central).
        Filament::setCurrentPanel(Filament::getPanel('tenant'));
        $this->assertFalse(SuaraPasar::canAccess());
    }

    // ------------------------------------------------------------------
    // (g) Isolasi: cabut izin tenant A tidak menyentuh event tenant B
    // ------------------------------------------------------------------

    public function test_cabut_izin_tenant_a_tidak_menghapus_event_tenant_b(): void
    {
        $tenantA = $this->siapkanTenant('airsa');
        $this->terapkanSetting(['optin_riset' => true]);
        $this->picuEskalasi($tenantA, 'Apa hukum melakukan kegiatan X?', 'kunci-iso-a');
        $jumlahA = $this->eventTenant($tenantA)->count();
        $this->assertGreaterThan(0, $jumlahA);
        tenancy()->end();

        $tenantB = $this->siapkanTenant('airsb');
        $this->terapkanSetting(['optin_riset' => true]);
        $this->picuEskalasi($tenantB, 'Bolehkah melakukan kegiatan Y?', 'kunci-iso-b');
        $jumlahB = $this->eventTenant($tenantB)->count();
        $this->assertGreaterThan(0, $jumlahB);
        tenancy()->end();

        // Tenant A mencabut izin.
        tenancy()->initialize($tenantA);
        $this->terapkanSetting(['optin_riset' => false]);
        tenancy()->end();

        $this->assertSame(0, $this->eventTenant($tenantA)->count(), 'Event tenant A harus terhapus');
        $this->assertSame($jumlahB, $this->eventTenant($tenantB)->count(), 'Event tenant B TIDAK boleh tersentuh');
    }
}
