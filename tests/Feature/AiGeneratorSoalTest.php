<?php

namespace Tests\Feature;

use App\Models\Tenant;
use App\Models\User;
use App\Services\StorageQuotaService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Filament\Facades\Filament;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Modules\AI\Database\Seeders\AiAgentSeeder;
use Modules\AI\Filament\Pages\GeneratorSoal;
use Modules\AI\Jobs\GenerateSoalJob;
use Modules\AI\Models\AiAgent;
use Modules\AI\Models\AiApproval;
use Modules\AI\Models\AiCreditLedger;
use Modules\AI\Models\AiDrafSoal;
use Modules\AI\Models\AiKnowledgeSource;
use Modules\AI\Models\AiPromptVersion;
use Modules\AI\Models\AiTenantSetting;
use Modules\AI\Services\Generator\SoalGenerator;
use Modules\AI\Services\Rag\IngestService;
use Modules\AI\Services\Settings\AiSettingProvisioner;
use Modules\AI\Services\Settings\AiTenantSettingDefaults;
use Modules\AI\Services\Settings\TenantSettingService;
use Modules\Notifikasi\Models\LogNotifikasi;
use Tests\Concerns\InteractsWithPpdbTestDatabase;
use Tests\TestCase;

/**
 * T-A040 — Generator Soal & Kisi-kisi:
 *  (a) submit → draf antri + GenerateSoalJob dispatched; cap jumlah maks
 *      (default config 10, hard cap 30);
 *  (b) job sukses → butir tersimpan per BATCH + SITASI DETERMINISTIK per
 *      butir (model TIDAK diminta menulis sitasi) + status draf + notifikasi;
 *  (c) RAG-only: tanpa korpus → gagal santun "unggah materi … Sumber
 *      Pengetahuan" TANPA HTTP apa pun (pagar #2);
 *  (d) JSON model rusak → retry parse-prompt 1× → gagal rapi;
 *  (e) human-in-the-loop: pengesahan butuh permission ai.generator.soal,
 *      jejak identitas+waktu tercatat; edit butir tersimpan, sitasi TERKUNCI;
 *  (f) idempotensi job: jalan 2× → butir & kredit tidak dobel (kunci metering
 *      per batch deterministik);
 *  (g) isolasi tenant; RBAC halaman; seeder agen asatidz_soal idempoten.
 */
class AiGeneratorSoalTest extends TestCase
{
    use InteractsWithPpdbTestDatabase;

    private const DIM = 1024;

    protected function setUp(): void
    {
        parent::setUp();

        ini_set('memory_limit', '512M');

        $this->ensurePpdbTestingSchemaReady([
            'ai_draf_soal', 'ai_knowledge_source', 'ai_chunk',
            'ai_tenant_setting', 'ai_approval', 'ai_policy', 'ai_policy_rule',
            'ai_escalation', 'ai_audit_log', 'ai_model_config',
            'ai_credit_ledger', 'ai_agent', 'ai_prompt_version', 'log_notifikasi',
        ]);
        $this->truncatePpdbTables(['domains', 'tenants', 'ai_credit_ledger', 'ai_tenant_budget']);

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

        Storage::fake(StorageQuotaService::diskName());
        Queue::fake();

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

    /** Aktifkan setting dengan override (field sensitif lewat approval kyai). */
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

    /** Nyalakan fitur generator_soal untuk peran asatidz (default OFF konservatif). */
    private function aktifkanFiturGeneratorSoal(): void
    {
        $fitur = AiTenantSettingDefaults::konservatif()['fitur_per_peran'];
        $fitur['asatidz']['generator_soal'] = true;

        $this->terapkanSetting(['fitur_per_peran' => $fitur]);
    }

    /** Ustadz pemegang role asatidz (permission ai.generator.soal via role seeder). */
    private function buatAsatidz(): User
    {
        return $this->buatUser('ustadz-'.uniqid().'@test.local', [], ['asatidz']);
    }

    /** Vektor deterministik per teks (pola AiRetrievalSitasiTest). */
    private static function vektorTeks(string $teks): array
    {
        $v = array_fill(0, self::DIM, 0.0);

        $kata = preg_split('/[^\p{L}\p{N}]+/u', mb_strtolower($teks), -1, PREG_SPLIT_NO_EMPTY) ?: [];

        foreach ($kata as $k) {
            $v[hexdec(substr(md5($k), 0, 6)) % self::DIM] += 1.0;
        }

        $norm = sqrt(array_sum(array_map(static fn (float $x): float => $x * $x, $v)));

        if ($norm <= 0.0) {
            $v[0] = 1.0;

            return $v;
        }

        return array_map(static fn (float $x): float => $x / $norm, $v);
    }

    /** JSON 5 butir valid (jawaban model patuh format). */
    private static function jsonButirValid(): string
    {
        $butir = [];

        for ($i = 1; $i <= 5; $i++) {
            $butir[] = [
                'no' => $i,
                'pertanyaan' => "Pertanyaan nomor {$i} tentang struktur kurikulum pelajaran bahasa Arab?",
                'opsi' => ['A. Pilihan satu', 'B. Pilihan dua', 'C. Pilihan tiga', 'D. Pilihan empat'],
                'kunci' => 'A',
                'kisi' => "Memahami materi kurikulum bagian {$i}",
            ];
        }

        return json_encode($butir, JSON_UNESCAPED_UNICODE);
    }

    /** Fake HTTP Ollama: embed deterministik + generate (default JSON valid). */
    private function fakeOllama(?string $responsGenerate = null): void
    {
        Http::fake([
            '*/api/embed' => function ($request) {
                $inputs = (array) ($request['input'] ?? []);

                return Http::response([
                    'embeddings' => array_map(
                        static fn ($t): array => self::vektorTeks((string) $t),
                        $inputs,
                    ),
                ]);
            },
            '*/api/generate' => Http::response([
                'response' => $responsGenerate ?? self::jsonButirValid(),
                'prompt_eval_count' => 120,
                'eval_count' => 80,
            ]),
        ]);
    }

    private function ingestKurikulum(): AiKnowledgeSource
    {
        $service = app(IngestService::class);
        $source = $service->daftarkanSumber(
            'Struktur Kurikulum Bahasa Arab',
            'kurikulum',
            'Struktur kurikulum memuat pelajaran bahasa Arab, nahwu, dan sharaf untuk jenjang Tsanawiyah. '
            .'Setiap bab diakhiri latihan pemahaman materi. Lihat halaman 3 untuk daftar kosakata wajib bab satu.',
        );

        return $service->prosesSumber((int) $source->getKey());
    }

    private function buatDrafSiap(User $asatidz, int $jumlah = 7): AiDrafSoal
    {
        return app(SoalGenerator::class)->buatDraf([
            'mapel' => 'Bahasa Arab',
            'bab' => 'Bab 1',
            'jenjang' => 'Tsanawiyah',
            'jumlah' => $jumlah,
            'tipe' => 'pilihan_ganda',
            'tingkat_kognitif' => 'C2',
        ], $asatidz);
    }

    private function jalankanJob(AiDrafSoal $draf, Tenant $tenant): void
    {
        (new GenerateSoalJob((int) $draf->getKey(), (string) $tenant->id))
            ->handle(app(SoalGenerator::class));
    }

    private function jumlahPanggilan(string $fragmen): int
    {
        return collect(Http::recorded())
            ->filter(static fn (array $pasangan): bool => str_contains($pasangan[0]->url(), $fragmen))
            ->count();
    }

    // ------------------------------------------------------------------
    // (a) Submit → draf antri + job + cap jumlah
    // ------------------------------------------------------------------

    public function test_submit_membuat_draf_antri_dan_dispatch_job(): void
    {
        $tenant = $this->siapkanTenant('soala');
        $asatidz = $this->buatAsatidz();

        $draf = $this->buatDrafSiap($asatidz);

        $this->assertSame(AiDrafSoal::STATUS_ANTRI, $draf->status);
        $this->assertSame(7, $draf->jumlah);
        $this->assertSame('Soal Bahasa Arab — Bab 1', $draf->judul);
        $this->assertSame((int) $asatidz->getKey(), $draf->dibuat_oleh);
        $this->assertNotEmpty($draf->idempotency_key);
        $this->assertSame([], (array) $draf->butir);

        Queue::assertPushed(
            GenerateSoalJob::class,
            fn (GenerateSoalJob $job): bool => $job->drafId === (int) $draf->getKey()
                && $job->tenantId === (string) $tenant->id,
        );
    }

    public function test_jumlah_dijepit_default_dan_hard_cap(): void
    {
        $this->siapkanTenant('soalb');
        $asatidz = $this->buatAsatidz();
        $service = app(SoalGenerator::class);

        // Tanpa jumlah → default config (10).
        $default = $service->buatDraf(['mapel' => 'Matematika', 'tipe' => 'esai'], $asatidz);
        $this->assertSame(10, $default->jumlah);

        // Melebihi cap → terjepit ke hard cap 30.
        $maks = $service->buatDraf(['mapel' => 'Nahwu', 'tipe' => 'pilihan_ganda', 'jumlah' => 99], $asatidz);
        $this->assertSame(30, $maks->jumlah);

        // Input tidak valid ditolak rapi.
        $this->expectException(\InvalidArgumentException::class);
        $service->buatDraf(['mapel' => 'Nahwu', 'tipe' => 'benar_salah'], $asatidz);
    }

    // ------------------------------------------------------------------
    // (b) Job sukses → butir + sitasi deterministik + notifikasi
    // ------------------------------------------------------------------

    public function test_job_sukses_butir_tersimpan_sitasi_per_butir_dan_notifikasi(): void
    {
        $tenant = $this->siapkanTenant('soalc');
        $this->aktifkanFiturGeneratorSoal();
        $this->fakeOllama();
        $sumber = $this->ingestKurikulum();
        $asatidz = $this->buatAsatidz();

        $draf = $this->buatDrafSiap($asatidz, jumlah: 7);
        $this->jalankanJob($draf, $tenant);

        $draf->refresh();

        $this->assertSame(AiDrafSoal::STATUS_DRAF, $draf->status);
        $this->assertNull($draf->error_ringkas);

        $butir = (array) $draf->butir;
        $this->assertCount(7, $butir);
        $this->assertSame(range(1, 7), array_column($butir, 'no'));

        foreach ($butir as $item) {
            $this->assertNotSame('', trim((string) $item['pertanyaan']));
            $this->assertIsArray($item['opsi']);
            $this->assertNotSame('', trim((string) $item['kunci']));
            $this->assertNotSame('', trim((string) $item['kisi']));

            // Sitasi DETERMINISTIK per butir dari retrieval (bukan dari model).
            $this->assertNotEmpty($item['sitasi']);
            $this->assertStringContainsString(
                (string) $sumber->judul,
                (string) $item['sitasi'][0]['label'],
            );
        }

        // Batch kecil: 7 butir @5 per batch = 2 panggilan generate.
        $this->assertSame(2, $this->jumlahPanggilan('/api/generate'));

        // Prompt ke provider TIDAK memuat instruksi sitasi — sitasi murni
        // urusan pipeline (Blueprint §7.2).
        Http::assertSent(static function ($req): bool {
            if (! str_contains($req->url(), '/api/generate')) {
                return false;
            }

            $prompt = mb_strtolower((string) ($req['prompt'] ?? ''));

            return str_contains($prompt, '<<<data:konteks>>>')
                && ! str_contains($prompt, 'sitasi')
                && ! str_contains($prompt, 'citation');
        });

        // Notifikasi selesai (database) untuk pembuat draf.
        $notif = LogNotifikasi::query()
            ->where('jenis', 'ai_generator_soal')
            ->where('status', 'sent')
            ->get();
        $this->assertCount(1, $notif);
        $this->assertSame((int) $draf->getKey(), $notif->first()->payload['draf_id']);
    }

    // ------------------------------------------------------------------
    // (c) RAG-only: tanpa korpus → gagal santun TANPA HTTP
    // ------------------------------------------------------------------

    public function test_tanpa_korpus_gagal_santun_tanpa_http(): void
    {
        $tenant = $this->siapkanTenant('soald');
        $this->fakeOllama();
        $asatidz = $this->buatAsatidz();

        $draf = $this->buatDrafSiap($asatidz);
        $this->jalankanJob($draf, $tenant);

        $draf->refresh();

        $this->assertSame(AiDrafSoal::STATUS_GAGAL, $draf->status);
        $this->assertStringContainsString('Sumber Pengetahuan', (string) $draf->error_ringkas);

        // Pagar #2: TANPA korpus tidak boleh ada HTTP apa pun ke provider
        // (generate maupun embed — retriever berhenti sebelum embed).
        Http::assertNothingSent();

        $this->assertSame(1, LogNotifikasi::query()
            ->where('jenis', 'ai_generator_soal')
            ->where('status', 'failed')
            ->count());
    }

    // ------------------------------------------------------------------
    // (d) JSON model rusak → retry parse 1× → gagal rapi
    // ------------------------------------------------------------------

    public function test_json_rusak_retry_parse_sekali_lalu_gagal_rapi(): void
    {
        $tenant = $this->siapkanTenant('soale');
        $this->aktifkanFiturGeneratorSoal();
        $this->fakeOllama(responsGenerate: 'Mohon maaf, berikut soal-soalnya: 1. Apa itu nahwu? Jawab: ilmu tata bahasa.');
        $this->ingestKurikulum();
        $asatidz = $this->buatAsatidz();

        $draf = $this->buatDrafSiap($asatidz, jumlah: 3); // 1 batch
        $this->jalankanJob($draf, $tenant);

        $draf->refresh();

        $this->assertSame(AiDrafSoal::STATUS_GAGAL, $draf->status);
        $this->assertStringContainsString('format soal yang valid', (string) $draf->error_ringkas);
        $this->assertSame([], (array) $draf->butir);

        // Tepat 2 panggilan generate: asli + retry parse-prompt — tidak lebih.
        $this->assertSame(2, $this->jumlahPanggilan('/api/generate'));

        $this->assertSame(1, LogNotifikasi::query()
            ->where('jenis', 'ai_generator_soal')
            ->where('status', 'failed')
            ->count());
    }

    // ------------------------------------------------------------------
    // (e) Pengesahan & edit butir (human-in-the-loop)
    // ------------------------------------------------------------------

    /** Draf berstatus draf dengan butir jadi (tanpa melewati LLM). */
    private function drafDenganButir(?User $pembuat = null): AiDrafSoal
    {
        $sitasi = [[
            'judul' => 'Struktur Kurikulum',
            'bab' => 'BAB I',
            'halaman' => 3,
            'label' => 'Struktur Kurikulum — BAB I, hlm. 3',
        ]];

        return AiDrafSoal::create([
            'judul' => 'Soal Bahasa Arab — Bab 1',
            'mapel' => 'Bahasa Arab',
            'bab' => 'Bab 1',
            'jumlah' => 2,
            'tipe' => 'pilihan_ganda',
            'status' => AiDrafSoal::STATUS_DRAF,
            'butir' => [
                ['no' => 1, 'pertanyaan' => 'Pertanyaan satu?', 'opsi' => ['A. Satu', 'B. Dua'], 'kunci' => 'A', 'kisi' => 'Kisi satu', 'sitasi' => $sitasi],
                ['no' => 2, 'pertanyaan' => 'Pertanyaan dua?', 'opsi' => ['A. Tiga', 'B. Empat'], 'kunci' => 'B', 'kisi' => 'Kisi dua', 'sitasi' => $sitasi],
            ],
            'dibuat_oleh' => $pembuat?->getKey(),
            'idempotency_key' => 'soal-'.Str::ulid(),
        ]);
    }

    public function test_pengesahan_tanpa_permission_ditolak(): void
    {
        $this->siapkanTenant('soalf');
        $draf = $this->drafDenganButir();
        $polos = $this->buatUser('polos-'.uniqid().'@test.local');

        try {
            app(SoalGenerator::class)->sahkan($draf, $polos);
            $this->fail('Pengesahan tanpa permission seharusnya ditolak.');
        } catch (AuthorizationException) {
            // diharapkan
        }

        $this->assertSame(AiDrafSoal::STATUS_DRAF, $draf->refresh()->status);
        $this->assertNull($draf->disahkan_oleh);
    }

    public function test_pengesahan_dengan_permission_mencatat_jejak(): void
    {
        $this->siapkanTenant('soalg');
        $asatidz = $this->buatAsatidz();
        $draf = $this->drafDenganButir($asatidz);

        $hasil = app(SoalGenerator::class)->sahkan($draf, $asatidz);

        $this->assertSame(AiDrafSoal::STATUS_DISAHKAN, $hasil->status);
        $this->assertSame((int) $asatidz->getKey(), $hasil->disahkan_oleh);
        $this->assertNotNull($hasil->disahkan_pada);

        // Sudah disahkan → tidak bisa disahkan ulang maupun diedit.
        try {
            app(SoalGenerator::class)->sahkan($hasil, $asatidz);
            $this->fail('Pengesahan ulang seharusnya ditolak.');
        } catch (\InvalidArgumentException) {
            // diharapkan
        }

        $this->expectException(\InvalidArgumentException::class);
        app(SoalGenerator::class)->perbaruiButir($hasil, [
            ['no' => 1, 'pertanyaan' => 'Diubah setelah sah?'],
        ]);
    }

    public function test_edit_butir_tersimpan_dan_sitasi_terkunci(): void
    {
        $this->siapkanTenant('soalh');
        $draf = $this->drafDenganButir();

        $hasil = app(SoalGenerator::class)->perbaruiButir($draf, [
            [
                'no' => 1,
                'pertanyaan' => 'Pertanyaan satu yang sudah diperbaiki guru?',
                'opsi' => ['A. Satu rev', 'B. Dua rev'],
                'kunci' => 'B',
                'kisi' => 'Kisi satu rev',
                // Upaya menimpa sitasi dari editor WAJIB diabaikan.
                'sitasi' => [['judul' => 'Karangan Palsu', 'label' => 'Karangan Palsu']],
            ],
        ]);

        $butir = (array) $hasil->butir;

        $this->assertSame('Pertanyaan satu yang sudah diperbaiki guru?', $butir[0]['pertanyaan']);
        $this->assertSame(['A. Satu rev', 'B. Dua rev'], $butir[0]['opsi']);
        $this->assertSame('B', $butir[0]['kunci']);
        $this->assertSame('Kisi satu rev', $butir[0]['kisi']);
        $this->assertSame('Struktur Kurikulum — BAB I, hlm. 3', $butir[0]['sitasi'][0]['label']);

        // Butir lain tidak tersentuh.
        $this->assertSame('Pertanyaan dua?', $butir[1]['pertanyaan']);
    }

    // ------------------------------------------------------------------
    // (f) Idempotensi job — butir & kredit tidak dobel
    // ------------------------------------------------------------------

    public function test_idempotensi_job_butir_dan_kredit_tidak_dobel(): void
    {
        $tenant = $this->siapkanTenant('soali');
        $this->aktifkanFiturGeneratorSoal();
        $this->fakeOllama();
        $this->ingestKurikulum();
        $asatidz = $this->buatAsatidz();

        $draf = $this->buatDrafSiap($asatidz, jumlah: 4); // 1 batch
        $this->jalankanJob($draf, $tenant);

        $draf->refresh();
        $this->assertSame(AiDrafSoal::STATUS_DRAF, $draf->status);
        $this->assertCount(4, (array) $draf->butir);

        $generateAwal = $this->jumlahPanggilan('/api/generate');
        $ledgerAwal = AiCreditLedger::query()
            ->where('idempotency_key', 'like', $draf->idempotency_key.'%')
            ->count();
        $this->assertGreaterThan(0, $ledgerAwal, 'Pemakaian generate wajib termeter');

        // (1) Re-run saat sudah final → no-op total (tanpa HTTP baru).
        $this->jalankanJob($draf, $tenant);

        $this->assertCount(4, (array) $draf->refresh()->butir);
        $this->assertSame($generateAwal, $this->jumlahPanggilan('/api/generate'));

        // (2) Retry "paksa" (status kembali antri, mis. job redelivery):
        // HTTP boleh terjadi lagi, tapi kunci metering per batch DETERMINISTIK
        // → ledger TIDAK bertambah (dedup T-A002) & butir TIDAK dobel.
        $draf->update(['status' => AiDrafSoal::STATUS_ANTRI]);
        $this->jalankanJob($draf, $tenant);

        $draf->refresh();
        $this->assertSame(AiDrafSoal::STATUS_DRAF, $draf->status);
        $this->assertCount(4, (array) $draf->butir);
        $this->assertSame(range(1, 4), array_column((array) $draf->butir, 'no'));

        $this->assertSame($ledgerAwal, AiCreditLedger::query()
            ->where('idempotency_key', 'like', $draf->idempotency_key.'%')
            ->count(), 'Retry job tidak boleh menggandakan kredit');
    }

    // ------------------------------------------------------------------
    // (g) Isolasi tenant + RBAC halaman + seeder agen
    // ------------------------------------------------------------------

    public function test_isolasi_tenant_draf_tidak_bocor(): void
    {
        $this->siapkanTenant('soalj');
        $this->drafDenganButir();
        $this->assertSame(1, AiDrafSoal::query()->count());

        tenancy()->end();
        $this->siapkanTenant('soalk');

        $this->assertSame(0, AiDrafSoal::query()->count(), 'Draf tenant lain TIDAK boleh terlihat');
    }

    public function test_rbac_halaman_generator_soal(): void
    {
        $this->siapkanTenant('soall');
        Filament::setCurrentPanel(Filament::getPanel('tenant'));

        $this->assertTrue(Route::has('filament.tenant.pages.generator-soal-ai'));
        $this->assertTrue(Route::has('ai.draf-soal.cetak'));

        // Tanpa izin: tertutup.
        $this->actingAs($this->buatUser('polos-rbac@test.local'));
        $this->assertFalse(GeneratorSoal::canAccess());

        // Role asatidz (permission via seeder): terbuka + halaman dirender.
        $this->actingAs($this->buatAsatidz());
        $this->assertTrue(GeneratorSoal::canAccess());
        Livewire::test(GeneratorSoal::class)->assertOk();
    }

    public function test_seeder_agen_asatidz_soal_idempoten_dan_netral(): void
    {
        $this->seed(AiAgentSeeder::class);
        $this->seed(AiAgentSeeder::class); // idempoten

        $agen = AiAgent::query()->where('key', 'asatidz_soal')->first();
        $this->assertNotNull($agen);
        $this->assertTrue((bool) $agen->is_active);
        $this->assertSame('standar', $agen->default_tier);

        $versiAktif = AiPromptVersion::query()
            ->where('agent_key', 'asatidz_soal')
            ->where('status', AiPromptVersion::STATUS_AKTIF)
            ->get();

        $this->assertCount(1, $versiAktif, 'Tepat satu versi prompt aktif');

        $prompt = (string) $versiAktif->first()->system_prompt;
        $this->assertStringContainsString('JSON', $prompt);
        $this->assertStringContainsString('bahasa Indonesia', $prompt);
        // TANPA instruksi sitasi (deterministik pipeline) & netral.
        $this->assertStringNotContainsString('sitasi', mb_strtolower($prompt));
    }
}
