<?php

namespace Tests\Feature;

use App\Models\AccessWali;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Modules\AI\Jobs\ProsesPesanWaJob;
use Modules\AI\Models\AiApproval;
use Modules\AI\Models\AiConversation;
use Modules\AI\Models\AiEscalation;
use Modules\AI\Models\AiMessage;
use Modules\AI\Models\AiTenantSetting;
use Modules\AI\Services\Gateway\AiGenerateRequest;
use Modules\AI\Services\Gateway\AiGenerateResult;
use Modules\AI\Services\Rag\RagAnswerer;
use Modules\AI\Services\Settings\AiSettingProvisioner;
use Modules\AI\Services\Settings\AiTenantSettingDefaults;
use Modules\AI\Services\Settings\TenantSettingService;
use Modules\AI\Services\Wa\IntentRouter;
use Modules\AI\Services\Wa\WaBalasanService;
use Modules\Kesantrian\Models\Santri;
use Modules\Keuangan\Models\Tagihan;
use Tests\Concerns\InteractsWithPpdbTestDatabase;
use Tests\TestCase;

/**
 * T-A051 — verifikasi wali & intent inti chatbot WA (mode_uji=false):
 *  (a) nomor dikenal → tanya tanggal lahir → benar = terverifikasi & tool jalan
 *      (jawaban nominal NYATA dari tool + footer sumber+cap waktu, NOL LLM);
 *  (b) tanggal salah 3x → kunci 24 jam + saran admin (tool tak tersentuh);
 *  (c) nomor asing → HANYA info publik (PPDB/menu), tool ditolak/diam;
 *  (d) pertanyaan agama → pengalihan PolicyEngine (level fatwa) + eskalasi;
 *  (e) free-form umum → RagAnswerer terpanggil (fake) channel wa agen wali_chat;
 *  (f) guard uang: kalimat panjang bernada uang → tool dulu, RAG tak disentuh;
 *  (g) multi-anak: sebut nama di pesan + perintah GANTI ANAK pindah konteks;
 *  (h) idempotensi retry job per wa_message_id (balasan tak dobel);
 *  (i) isolasi tenant (verifikasi tenant A tidak bocor ke B);
 *  (j) suite kasus intent wa_intent_cases.json ≥25 kasus, route benar ≥97%.
 */
class AiWaIntentWaliTest extends TestCase
{
    use InteractsWithPpdbTestDatabase;

    private const TOKEN = 'token-rahasia-uji';

    private const NOMOR_JID = '628111222333@c.us';

    private const NO_HP_WALI = '08111222333'; // varian 08 — normalisasi satu pintu

    protected function setUp(): void
    {
        parent::setUp();

        $this->ensurePpdbTestingSchemaReady([
            'ai_conversation', 'ai_message', 'ai_tenant_setting', 'ai_approval',
            'ai_policy', 'ai_policy_rule', 'ai_escalation',
            'santri', 'access_wali', 'tagihan', 'pembayaran',
        ]);

        if (! Schema::connection('pgsql')->hasColumn('ai_conversation', 'verifikasi_status')) {
            Artisan::call('migrate', ['--force' => true]);
        }

        $this->truncatePpdbTables([
            'domains', 'tenants', 'santri', 'access_wali', 'tagihan', 'pembayaran',
        ]);
        // Baseline gating modul T-A012: registry kosong = modul tampil.
        DB::table('module_registry')->delete();
        Cache::flush();

        Config::set('ai.wa.webhook_token', self::TOKEN);
        Config::set('ai.wa.mode_uji', false); // T-A051: pipeline intent aktif
        Config::set('notifikasi.waha_base_url', 'http://waha.test:3000');
        Config::set('notifikasi.n8n_webhook_url', null);
        Config::set('notifikasi.waha_session', 'default');
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

    private function buatUser(string $email, array $roles = [], array $permissions = []): User
    {
        $user = User::create([
            'name' => 'Uji '.$email,
            'email' => $email,
            'password' => 'password',
        ]);

        foreach ($roles as $role) {
            $user->assignRole($role);
        }

        foreach ($permissions as $permission) {
            $user->givePermissionTo($permission);
        }

        return $user;
    }

    /** Aktifkan setting dengan override (alur approval kyai bila sensitif). */
    private function terapkanSetting(array $overrides): void
    {
        $admin = $this->buatUser('admin-'.uniqid().'@test.local', [], ['ai.setting.kelola']);
        $kyai = $this->buatUser('kyai-'.uniqid().'@test.local', [], ['ai.setting.setujui']);
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

    /** Aktivasi pilot via Wizard: fitur chatbot wali ON (default konservatif OFF). */
    private function aktifkanChatbotWali(array $extra = []): void
    {
        $fitur = AiTenantSettingDefaults::konservatif()['fitur_per_peran'];
        $fitur['wali']['chatbot'] = true;

        $this->terapkanSetting(['fitur_per_peran' => $fitur] + $extra);
    }

    /**
     * Wali nyata pola skema Kesantrian: users ber-role wali TANPA kolom phone
     * — pencocokan lewat santri.no_hp_wali (varian 08) + wali_user_id +
     * AccessWali, persis jalur PortalWaliIdentifierResolver eksisting.
     *
     * @return array{0: User, 1: Santri}
     */
    private function buatWaliDenganAnak(
        string $nama = 'Ahmad Fauzi',
        string $tanggalLahir = '2014-03-05',
        bool $primary = true,
    ): array {
        $wali = User::query()->where('email', 'wali-wa@test.local')->first()
            ?? $this->buatUser('wali-wa@test.local', ['wali']);

        $santri = Santri::create([
            'nis' => 'WA'.str_pad((string) (Santri::query()->count() + 1), 3, '0', STR_PAD_LEFT),
            'nama' => $nama,
            'tanggal_lahir' => $tanggalLahir,
            'jenis_kelamin' => 'L',
            'status' => 'aktif',
            'no_hp_wali' => self::NO_HP_WALI,
            'wali_user_id' => $wali->id,
        ]);

        AccessWali::create([
            'wali_id' => $wali->id,
            'santri_id' => $santri->id,
            'relationship_type' => 'ayah',
            'is_primary' => $primary,
        ]);

        return [$wali, $santri];
    }

    private function buatTagihan(Santri $s, float $nominal): Tagihan
    {
        return Tagihan::create([
            'santri_id' => $s->id,
            'jenis_tagihan' => 'spp',
            'nominal' => $nominal,
            'nominal_akhir' => $nominal,
            'status' => 'belum_bayar',
            'jatuh_tempo' => '2026-07-01',
        ]);
    }

    private function payloadWa(string $tenantId, string $id, string $body, string $from = self::NOMOR_JID): array
    {
        return [
            'event' => 'message',
            'session' => 'default',
            'tenant' => $tenantId,
            'payload' => ['id' => $id, 'from' => $from, 'body' => $body, 'fromMe' => false],
        ];
    }

    private function kirim(Tenant $tenant, string $id, string $body, string $from = self::NOMOR_JID)
    {
        // Webhook berjalan di konteks central; job sync memproses di tenant.
        if (tenancy()->initialized) {
            tenancy()->end();
        }

        return $this->postJson(
            '/api/ai/webhook/wa',
            $this->payloadWa((string) $tenant->id, $id, $body, $from),
            ['X-Webhook-Token' => self::TOKEN],
        );
    }

    /** Teks balasan terakhir yang terkirim ke WAHA (Http fake). */
    private function balasanTerakhir(): string
    {
        $recorded = Http::recorded(
            fn ($request) => str_contains($request->url(), '/api/sendText'),
        );

        $this->assertNotEmpty($recorded, 'Tidak ada balasan WA yang terkirim.');

        return (string) $recorded[count($recorded) - 1][0]['text'];
    }

    private function fakeWaha(): void
    {
        Http::fake(['http://waha.test:3000/*' => Http::response(['status' => 'ok'])]);
        Http::preventStrayRequests(); // HTTP tak ter-fake (mis. Ollama) = test GAGAL
    }

    /** Jalankan verifikasi sampai terverifikasi (pesan pembuka + tanggal lahir). */
    private function verifikasiSampaiSukses(Tenant $tenant, string $tanggal = '05-03-2014'): void
    {
        $this->kirim($tenant, 'ver-'.uniqid(), 'assalamualaikum, mau tanya tagihan')->assertOk();
        $this->kirim($tenant, 'ver-'.uniqid(), $tanggal)->assertOk();
    }

    // ------------------------------------------------------------------
    // (a) Verifikasi: dikenal → tanya tgl lahir → benar → tool jalan TANPA LLM
    // ------------------------------------------------------------------

    public function test_verifikasi_lalu_intent_tagihan_dari_tool_tanpa_llm(): void
    {
        $this->fakeWaha();
        $tenant = $this->siapkanTenant('waiv');
        $this->aktifkanChatbotWali();
        [, $anak] = $this->buatWaliDenganAnak();
        $this->buatTagihan($anak, 150000);

        // 1. Pesan pertama dari nomor dikenal → diminta tanggal lahir anak.
        $this->kirim($tenant, 'v-1', 'assalamualaikum, mau tanya tagihan')->assertOk();
        $this->assertStringContainsString('tanggal lahir', $this->balasanTerakhir());

        tenancy()->initialize($tenant);
        $conv = AiConversation::query()->sole();
        $this->assertSame(AiConversation::VERIFIKASI_MENUNGGU_TGL_LAHIR, $conv->verifikasi_status);
        $this->assertNotNull($conv->wali_user_id);

        // 2. Tanggal lahir benar (format dd-mm-yyyy) → terverifikasi.
        $this->kirim($tenant, 'v-2', '05-03-2014')->assertOk();
        $this->assertStringContainsString('verifikasi berhasil', $this->balasanTerakhir());

        tenancy()->initialize($tenant);
        $conv->refresh();
        $this->assertSame(AiConversation::VERIFIKASI_TERVERIFIKASI, $conv->verifikasi_status);
        $this->assertSame($anak->id, (int) $conv->santri_id);

        // 3. Intent tagihan: nominal NYATA dari tool + footer sumber+cap waktu.
        $this->kirim($tenant, 'v-3', 'brp spp bln ini pak')->assertOk();
        $balasan = $this->balasanTerakhir();

        $this->assertStringContainsString('Rp 150.000', $balasan, 'Nominal wajib dari ToolResult');
        $this->assertStringContainsString('Sumber:', $balasan, 'Footer sumber wajib ada');
        $this->assertStringContainsString('Keuangan', $balasan);
        $this->assertStringContainsString($anak->nama, $balasan);

        // NOL panggilan LLM: seluruh HTTP keluar hanya WAHA sendText
        // (preventStrayRequests menggagalkan HTTP Ollama; hitungan pas 3).
        Http::assertSentCount(3);
        Http::assertNotSent(fn ($request): bool => str_contains($request->url(), '11434'));

        // Balasan keluar tercatat terredaksi + tertanda pesan asalnya.
        tenancy()->initialize($tenant);
        $keluar = AiMessage::query()->where('arah', AiMessage::ARAH_KELUAR)->get();
        $this->assertCount(3, $keluar);
        $this->assertSame('v-3', $keluar->last()->meta['balasan_untuk'] ?? null);
    }

    public function test_format_tanggal_fleksibel_dd_spasi_mm_yyyy(): void
    {
        $this->fakeWaha();
        $tenant = $this->siapkanTenant('waif');
        $this->aktifkanChatbotWali();
        $this->buatWaliDenganAnak(tanggalLahir: '2014-03-05');

        $this->kirim($tenant, 'f-1', 'halo')->assertOk();
        $this->kirim($tenant, 'f-2', 'tanggal lahirnya 5 maret 2014 pak')->assertOk();

        $this->assertStringContainsString('verifikasi berhasil', $this->balasanTerakhir());

        tenancy()->initialize($tenant);
        $this->assertSame(
            AiConversation::VERIFIKASI_TERVERIFIKASI,
            AiConversation::query()->sole()->verifikasi_status,
        );
    }

    // ------------------------------------------------------------------
    // (b) Salah 3x → kunci 24 jam
    // ------------------------------------------------------------------

    public function test_tanggal_salah_tiga_kali_terkunci_24_jam(): void
    {
        $this->fakeWaha();
        $tenant = $this->siapkanTenant('waik');
        $this->aktifkanChatbotWali();
        [, $anak] = $this->buatWaliDenganAnak(tanggalLahir: '2014-03-05');
        $this->buatTagihan($anak, 999000);

        $this->kirim($tenant, 'k-0', 'mau tanya tagihan')->assertOk();
        $this->kirim($tenant, 'k-1', '01-01-2000')->assertOk();
        $this->assertStringContainsString('belum cocok', $this->balasanTerakhir());
        $this->kirim($tenant, 'k-2', '02-02-2001')->assertOk();
        $this->kirim($tenant, 'k-3', '03-03-2002')->assertOk();
        $this->assertStringContainsString('hubungi admin', $this->balasanTerakhir());

        tenancy()->initialize($tenant);
        $conv = AiConversation::query()->sole();
        $this->assertSame(3, $conv->verifikasi_percobaan);
        $this->assertNotNull($conv->verifikasi_terkunci_sampai);
        $this->assertTrue($conv->verifikasi_terkunci_sampai->isFuture());

        // Tanggal BENAR pun ditolak selama kunci berlaku.
        $this->kirim($tenant, 'k-4', '05-03-2014')->assertOk();
        $this->assertStringContainsString('dinonaktifkan sementara', $this->balasanTerakhir());

        tenancy()->initialize($tenant);
        $this->assertSame(
            AiConversation::VERIFIKASI_MENUNGGU_TGL_LAHIR,
            AiConversation::query()->sole()->verifikasi_status,
        );

        // Tidak ada satu pun balasan berisi nominal (tool tak pernah jalan).
        foreach (Http::recorded() as [$request]) {
            $this->assertStringNotContainsString('Rp ', (string) $request['text']);
        }
    }

    // ------------------------------------------------------------------
    // (c) Nomor asing: hanya info publik
    // ------------------------------------------------------------------

    public function test_nomor_asing_hanya_dilayani_info_publik(): void
    {
        $this->fakeWaha();
        $tenant = $this->siapkanTenant('waia');
        $this->aktifkanChatbotWali();
        // Ada data santri+tagihan milik orang lain — TIDAK boleh bocor.
        [, $anak] = $this->buatWaliDenganAnak();
        $this->buatTagihan($anak, 777000);

        $asing = '628999888777@c.us';

        // Pertanyaan data → ditolak santun, tanpa nominal, tawarkan admin.
        $this->kirim($tenant, 'a-1', 'tagihan anak saya berapa ya', $asing)->assertOk();
        $balasan = $this->balasanTerakhir();
        $this->assertStringContainsString('belum terdaftar', $balasan);
        $this->assertStringContainsString('admin', $balasan);
        $this->assertStringNotContainsString('Rp', $balasan);

        // Info publik PPDB tetap dilayani (+ jam layanan dari setting).
        $this->kirim($tenant, 'a-2', 'info ppdb dong', $asing)->assertOk();
        $balasan = $this->balasanTerakhir();
        $this->assertStringContainsString('PPDB', $balasan);
        $this->assertStringContainsString('Jam layanan', $balasan);

        tenancy()->initialize($tenant);
        $conv = AiConversation::query()
            ->where('nomor_wa_hash', AiConversation::hashNomor($asing))
            ->sole();
        $this->assertSame(AiConversation::VERIFIKASI_BELUM, $conv->verifikasi_status);
        $this->assertNull($conv->wali_user_id);
    }

    // ------------------------------------------------------------------
    // (d) Pertanyaan agama → pengalihan PolicyEngine + eskalasi
    // ------------------------------------------------------------------

    public function test_pertanyaan_agama_dialihkan_dan_tereskalasi_tanpa_rag(): void
    {
        $this->fakeWaha();
        // RAG tidak boleh tersentuh sama sekali — mock tanpa ekspektasi.
        $this->mock(RagAnswerer::class);

        $tenant = $this->siapkanTenant('waig');
        $this->aktifkanChatbotWali();
        $this->buatWaliDenganAnak();
        $this->verifikasiSampaiSukses($tenant);

        // Default level_fatwa konservatif = edukatif_only → pola tanya-hukum
        // dialihkan PolicyEngine (tanpa membangun ulang apa pun di T-A051).
        $this->kirim($tenant, 'g-1', 'Apa hukumnya meninggalkan sholat berjamaah?')->assertOk();

        $this->assertStringContainsString('ustadz', $this->balasanTerakhir());

        tenancy()->initialize($tenant);
        $eskalasi = AiEscalation::query()->where('kategori', 'level_fatwa')->sole();
        $this->assertSame('wa', $eskalasi->kanal);
        $this->assertSame('wa-g-1', $eskalasi->idempotency_key);
    }

    // ------------------------------------------------------------------
    // (e) Free-form umum → RagAnswerer terpanggil (fake)
    // ------------------------------------------------------------------

    public function test_free_form_umum_memanggil_rag_channel_wa_agen_wali_chat(): void
    {
        $this->fakeWaha();
        $this->mock(RagAnswerer::class, function ($mock): void {
            $mock->shouldReceive('jawab')
                ->once()
                ->withArgs(function (AiGenerateRequest $req, array $opsi): bool {
                    return $req->channel === 'wa'
                        && $req->agent === 'wali_chat'
                        && $req->tier === 'ekonomis'
                        && $req->idempotencyKey === 'wa-r-1'
                        && ($opsi['kategori_jawaban'] ?? null) === 'umum';
                })
                ->andReturn(new AiGenerateResult(
                    text: 'Visi pesantren adalah mencetak generasi qurani. (uji)',
                    provider: 'ollama',
                    model: 'uji',
                ));
        });

        $tenant = $this->siapkanTenant('wair');
        $this->aktifkanChatbotWali();
        $this->buatWaliDenganAnak();
        $this->verifikasiSampaiSukses($tenant);

        $this->kirim($tenant, 'r-1', 'apa visi misi pesantren ini')->assertOk();

        $this->assertStringContainsString('Visi pesantren', $this->balasanTerakhir());
    }

    // ------------------------------------------------------------------
    // (f) Guard anti-karang angka: kalimat panjang bernada uang → tool, bukan RAG
    // ------------------------------------------------------------------

    public function test_guard_uang_kalimat_panjang_ke_tool_bukan_rag(): void
    {
        $this->fakeWaha();
        $this->mock(RagAnswerer::class); // panggilan apa pun = test gagal

        $tenant = $this->siapkanTenant('waiu');
        $this->aktifkanChatbotWali();
        [, $anak] = $this->buatWaliDenganAnak();
        $this->buatTagihan($anak, 150000);
        $this->verifikasiSampaiSukses($tenant);

        $this->kirim(
            $tenant,
            'u-1',
            'pak saya mau menanyakan perihal uang bulanan ananda yang kemarin itu bagaimana ya',
        )->assertOk();

        $balasan = $this->balasanTerakhir();
        $this->assertStringContainsString('Rp 150.000', $balasan, 'Pertanyaan uang wajib dijawab tool, bukan RAG');
        $this->assertStringContainsString('Sumber:', $balasan);
    }

    // ------------------------------------------------------------------
    // (g) Multi-anak: sebut nama + perintah GANTI ANAK
    // ------------------------------------------------------------------

    public function test_multi_anak_sebut_nama_dan_ganti_konteks(): void
    {
        $this->fakeWaha();
        $tenant = $this->siapkanTenant('waim');
        $this->aktifkanChatbotWali();
        [, $anak1] = $this->buatWaliDenganAnak('Ahmad Fauzi', '2014-03-05', primary: true);
        [, $anak2] = $this->buatWaliDenganAnak('Siti Aminah', '2016-07-01', primary: false);
        $this->buatTagihan($anak1, 150000);
        $this->buatTagihan($anak2, 275000);
        $this->verifikasiSampaiSukses($tenant); // tgl lahir anak pertama

        // Default sesi = anak pertama (is_primary).
        $this->kirim($tenant, 'm-1', 'tagihan')->assertOk();
        $balasan = $this->balasanTerakhir();
        $this->assertStringContainsString('Ahmad Fauzi', $balasan);
        $this->assertStringContainsString('Rp 150.000', $balasan);

        // Nama anak DISEBUT di pesan → konteks pesan itu saja (sesi tetap).
        $this->kirim($tenant, 'm-2', 'kalau tagihan siti berapa')->assertOk();
        $balasan = $this->balasanTerakhir();
        $this->assertStringContainsString('Siti Aminah', $balasan);
        $this->assertStringContainsString('Rp 275.000', $balasan);

        tenancy()->initialize($tenant);
        $this->assertSame($anak1->id, (int) AiConversation::query()->sole()->santri_id, 'Sesi belum berpindah');

        // Perintah GANTI ANAK → default sesi berpindah.
        $this->kirim($tenant, 'm-3', 'ganti anak siti')->assertOk();
        $this->assertStringContainsString('Siti Aminah', $this->balasanTerakhir());

        tenancy()->initialize($tenant);
        $this->assertSame($anak2->id, (int) AiConversation::query()->sole()->santri_id);

        $this->kirim($tenant, 'm-4', 'tagihan')->assertOk();
        $this->assertStringContainsString('Rp 275.000', $this->balasanTerakhir());
    }

    // ------------------------------------------------------------------
    // (h) Idempotensi retry job
    // ------------------------------------------------------------------

    public function test_retry_job_pesan_sama_tidak_membalas_dobel(): void
    {
        $this->fakeWaha();
        $tenant = $this->siapkanTenant('waii');
        $this->aktifkanChatbotWali();
        [, $anak] = $this->buatWaliDenganAnak();
        $this->buatTagihan($anak, 150000);
        $this->verifikasiSampaiSukses($tenant);

        $this->kirim($tenant, 'i-1', 'tagihan')->assertOk();

        tenancy()->initialize($tenant);
        $conv = AiConversation::query()->sole();
        $masuk = AiMessage::query()->where('wa_message_id', 'i-1')->sole();
        $jumlahKeluar = AiMessage::query()->where('arah', AiMessage::ARAH_KELUAR)->count();
        $jumlahHttp = count(Http::recorded());
        tenancy()->end();

        // Retry job yang sama (mis. worker mati setelah kirim, sebelum ack).
        (new ProsesPesanWaJob(
            (string) $tenant->id,
            (int) $conv->id,
            (int) $masuk->id,
            Crypt::encryptString('tagihan'),
        ))->handle(app(WaBalasanService::class));

        $this->assertCount($jumlahHttp, Http::recorded(), 'Retry tidak boleh mengirim balasan dobel');

        tenancy()->initialize($tenant);
        $this->assertSame(
            $jumlahKeluar,
            AiMessage::query()->where('arah', AiMessage::ARAH_KELUAR)->count(),
            'Retry tidak boleh mencatat pesan keluar dobel',
        );
    }

    // ------------------------------------------------------------------
    // (i) Isolasi tenant
    // ------------------------------------------------------------------

    public function test_isolasi_tenant_nomor_wali_a_asing_di_tenant_b(): void
    {
        $this->fakeWaha();
        $tenantA = $this->siapkanTenant('waxa');
        $this->aktifkanChatbotWali();
        [, $anakA] = $this->buatWaliDenganAnak();
        $this->buatTagihan($anakA, 150000);
        tenancy()->end();

        $tenantB = $this->siapkanTenant('waxb');
        $this->aktifkanChatbotWali();
        tenancy()->end();

        // Nomor terdaftar di A — di tenant B harus diperlakukan ASING.
        $this->kirim($tenantB, 'x-1', 'tagihan anak saya berapa')->assertOk();
        $balasan = $this->balasanTerakhir();
        $this->assertStringContainsString('belum terdaftar', $balasan);
        $this->assertStringNotContainsString('Rp', $balasan);

        tenancy()->initialize($tenantB);
        $conv = AiConversation::query()->sole();
        $this->assertSame(AiConversation::VERIFIKASI_BELUM, $conv->verifikasi_status);
        $this->assertNull($conv->wali_user_id);
    }

    // ------------------------------------------------------------------
    // (j) Suite kasus intent ≥25 kasus, route benar ≥97%
    // ------------------------------------------------------------------

    public function test_suite_intent_route_benar_minimal_97_persen(): void
    {
        $berkas = base_path('Modules/AI/database/seeders/data/wa_intent_cases.json');
        $this->assertFileExists($berkas);

        $kasus = (array) (json_decode((string) file_get_contents($berkas), true)['kasus'] ?? []);
        $this->assertGreaterThanOrEqual(25, count($kasus), 'Suite wajib ≥25 kasus');

        $router = app(IntentRouter::class);
        $salah = [];

        foreach ($kasus as $k) {
            $hasil = $router->route((string) $k['teks']) ?? 'bebas';

            if ($hasil !== (string) $k['intent']) {
                $salah[] = sprintf('"%s" → %s (harap %s)', $k['teks'], $hasil, $k['intent']);
            }
        }

        $skor = 1 - count($salah) / count($kasus);

        $this->assertGreaterThanOrEqual(
            0.97,
            $skor,
            sprintf("Skor route %.1f%% (< 97%%). Salah route:\n%s", $skor * 100, implode("\n", $salah)),
        );
    }
}
