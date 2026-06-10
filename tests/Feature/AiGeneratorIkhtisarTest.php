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
use Modules\AI\Filament\Pages\IkhtisarKitab;
use Modules\AI\Jobs\GenerateIkhtisarJob;
use Modules\AI\Models\AiAgent;
use Modules\AI\Models\AiApproval;
use Modules\AI\Models\AiCreditLedger;
use Modules\AI\Models\AiDrafIkhtisar;
use Modules\AI\Models\AiKnowledgeSource;
use Modules\AI\Models\AiPromptVersion;
use Modules\AI\Models\AiTenantSetting;
use Modules\AI\Services\Generator\IkhtisarGenerator;
use Modules\AI\Services\Rag\IngestService;
use Modules\AI\Services\Settings\AiSettingProvisioner;
use Modules\AI\Services\Settings\AiTenantSettingDefaults;
use Modules\AI\Services\Settings\TenantSettingService;
use Modules\Notifikasi\Models\LogNotifikasi;
use Tests\Concerns\InteractsWithPpdbTestDatabase;
use Tests\TestCase;

/**
 * T-A042 — Ikhtisar materi kitab bersitasi & generator kultum (kembaran pola
 * AiGeneratorSoalTest/AiGeneratorRppTest):
 *  (a) submit → draf antri + GenerateIkhtisarJob dispatched; ikhtisar wajib
 *      sumber (adaptasi sadar: sumber = Sumber Pengetahuan tenant, whitelist
 *      kitab GATE-3 belum terisi);
 *  (b) job sukses ikhtisar → poin + istilah + pertanyaan_diskusi; SETIAP poin
 *      tertaut sitasi_idx → kolom sitasi DETERMINISTIK pipeline (model TIDAK
 *      diminta menulis sitasi) + notifikasi;
 *  (c) KULTUM OPT-IN default OFF: opsi tersembunyi di form (assert render),
 *      buatDraf ditolak santun, dan jalur gateway ditolak PolicyEngine TANPA
 *      HTTP generate (dua lapis);
 *  (d) kultum ON via alur approval setting → kerangka + klaim [PERIKSA DALIL]
 *      + rujukan DIPAKSA kosong (model tak dipercaya menulis dalil) +
 *      disclaimer PRD §7.5 verbatim + teks prosedural tambahan;
 *  (e) RAG-only: tanpa korpus → gagal santun TANPA HTTP apa pun (pagar #2);
 *  (f) JSON model rusak → retry parse-prompt 1× → gagal rapi;
 *  (g) human-in-the-loop: pengesahan butuh permission ai.generator.ikhtisar,
 *      jejak identitas+waktu; edit isi tersimpan, sitasi_idx/disclaimer
 *      TERKUNCI, rujukan dalil justru DIISI ustadz;
 *  (h) idempotensi job: jalan 2× → isi & kredit tidak dobel;
 *  (i) isolasi tenant; RBAC halaman; seeder agen idempoten & netral;
 *      handout cetak print-friendly ber-MIME html + RBAC-nya.
 */
class AiGeneratorIkhtisarTest extends TestCase
{
    use InteractsWithPpdbTestDatabase;

    private const DIM = 1024;

    protected function setUp(): void
    {
        parent::setUp();

        ini_set('memory_limit', '512M');

        $this->ensurePpdbTestingSchemaReady([
            'ai_draf_ikhtisar', 'ai_knowledge_source', 'ai_chunk',
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

    /**
     * Nyalakan fitur untuk peran asatidz lewat alur approval setting
     * (fitur_per_peran = field sensitif → persetujuan kyai).
     *
     * @param  array<string, bool>  $fiturAsatidz
     */
    private function aktifkanFitur(array $fiturAsatidz): void
    {
        $fitur = AiTenantSettingDefaults::konservatif()['fitur_per_peran'];

        foreach ($fiturAsatidz as $nama => $aktif) {
            $fitur['asatidz'][$nama] = $aktif;
        }

        $this->terapkanSetting(['fitur_per_peran' => $fitur]);
    }

    /** Ustadz pemegang role asatidz (permission ai.generator.ikhtisar via role seeder). */
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

    /** JSON ikhtisar valid (jawaban model patuh format — TANPA sitasi). */
    private static function jsonIkhtisarValid(): string
    {
        return json_encode([
            'poin' => [
                'Santri memahami struktur kurikulum pelajaran bahasa Arab.',
                'Setiap bab diakhiri latihan pemahaman materi.',
            ],
            'istilah' => [
                ['istilah' => 'nahwu', 'arti' => 'ilmu tata bahasa Arab menurut konteks materi'],
                ['istilah' => 'sharaf', 'arti' => 'ilmu perubahan bentuk kata menurut konteks materi'],
            ],
            'pertanyaan_diskusi' => [
                'Mengapa latihan pemahaman ditempatkan di akhir setiap bab?',
            ],
        ], JSON_UNESCAPED_UNICODE);
    }

    /**
     * JSON kultum valid — model SENGAJA menulis klaim tanpa penanda dan
     * MENGISI rujukan (mengarang): pipeline wajib menormalkan penanda dan
     * MENGOSONGKAN rujukan (model tak dipercaya menulis dalil).
     */
    private static function jsonKultumValid(): string
    {
        return json_encode([
            'kerangka' => [
                'Pembuka: salam dan pengantar tema adab menuntut ilmu.',
                'Isi: pentingnya memuliakan guru menurut materi pesantren.',
                'Penutup: ajakan mengamalkan adab dalam keseharian.',
            ],
            'pengingat_dalil' => [
                ['klaim' => 'Menuntut ilmu adalah kewajiban.', 'rujukan' => 'HR. Karangan Model #1'],
                ['klaim' => '[PERIKSA DALIL] Memuliakan guru bagian dari adab.', 'rujukan' => ''],
            ],
        ], JSON_UNESCAPED_UNICODE);
    }

    /** Fake HTTP Ollama: embed deterministik + generate (default JSON ikhtisar valid). */
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
                'response' => $responsGenerate ?? self::jsonIkhtisarValid(),
                'prompt_eval_count' => 120,
                'eval_count' => 80,
            ]),
        ]);
    }

    private function ingestKitab(): AiKnowledgeSource
    {
        $service = app(IngestService::class);
        $source = $service->daftarkanSumber(
            'Kitab Adab Menuntut Ilmu',
            'kurikulum',
            'Materi adab menuntut ilmu memuat pentingnya memuliakan guru, struktur kurikulum pelajaran bahasa Arab, '
            .'nahwu, dan sharaf. Setiap bab diakhiri latihan pemahaman materi. Lihat halaman 3 untuk istilah penting bab satu.',
        );

        return $service->prosesSumber((int) $source->getKey());
    }

    private function buatDrafIkhtisar(User $asatidz, AiKnowledgeSource $sumber, ?string $bab = 'Bab 1'): AiDrafIkhtisar
    {
        return app(IkhtisarGenerator::class)->buatDraf([
            'jenis' => 'ikhtisar',
            'source_id' => (int) $sumber->getKey(),
            'bab' => $bab,
        ], $asatidz);
    }

    private function jalankanJob(AiDrafIkhtisar $draf, Tenant $tenant): void
    {
        (new GenerateIkhtisarJob((int) $draf->getKey(), (string) $tenant->id))
            ->handle(app(IkhtisarGenerator::class));
    }

    private function jumlahPanggilan(string $fragmen): int
    {
        return collect(Http::recorded())
            ->filter(static fn (array $pasangan): bool => str_contains($pasangan[0]->url(), $fragmen))
            ->count();
    }

    private function urlTenant(Tenant $tenant, string $path): string
    {
        return 'http://'.$tenant->id.'.localhost'.$path;
    }

    // ------------------------------------------------------------------
    // (a) Submit → draf antri + job + validasi input
    // ------------------------------------------------------------------

    public function test_submit_membuat_draf_antri_dan_dispatch_job(): void
    {
        $tenant = $this->siapkanTenant('ikha');
        $this->fakeOllama();
        $sumber = $this->ingestKitab();
        $asatidz = $this->buatAsatidz();

        $draf = $this->buatDrafIkhtisar($asatidz, $sumber);

        $this->assertSame(AiDrafIkhtisar::STATUS_ANTRI, $draf->status);
        $this->assertSame('ikhtisar', $draf->jenis);
        $this->assertSame('Ikhtisar Kitab Adab Menuntut Ilmu — Bab 1', $draf->judul);
        $this->assertSame((int) $sumber->getKey(), $draf->source_id);
        $this->assertSame((int) $asatidz->getKey(), $draf->dibuat_oleh);
        $this->assertNotEmpty($draf->idempotency_key);
        $this->assertSame([], (array) $draf->isi);

        Queue::assertPushed(
            GenerateIkhtisarJob::class,
            fn (GenerateIkhtisarJob $job): bool => $job->drafId === (int) $draf->getKey()
                && $job->tenantId === (string) $tenant->id,
        );

        // Ikhtisar tanpa sumber ditolak rapi (sumber = identitas dokumen;
        // adaptasi sadar: Sumber Pengetahuan tenant, bukan whitelist GATE-3).
        try {
            app(IkhtisarGenerator::class)->buatDraf(['jenis' => 'ikhtisar'], $asatidz);
            $this->fail('Ikhtisar tanpa sumber seharusnya ditolak.');
        } catch (\InvalidArgumentException $e) {
            $this->assertStringContainsString('Sumber Pengetahuan', $e->getMessage());
        }

        // Jenis tak dikenal ditolak rapi.
        $this->expectException(\InvalidArgumentException::class);
        app(IkhtisarGenerator::class)->buatDraf(['jenis' => 'khotbah_jumat'], $asatidz);
    }

    // ------------------------------------------------------------------
    // (b) Ikhtisar sukses → poin bersitasi tertelusur + notifikasi
    // ------------------------------------------------------------------

    public function test_ikhtisar_sukses_setiap_poin_tertaut_sitasi_dan_notifikasi(): void
    {
        $tenant = $this->siapkanTenant('ikhb');
        $this->aktifkanFitur(['ikhtisar_kitab' => true]);
        $this->fakeOllama();
        $sumber = $this->ingestKitab();
        $asatidz = $this->buatAsatidz();

        $draf = $this->buatDrafIkhtisar($asatidz, $sumber);
        $this->jalankanJob($draf, $tenant);

        $draf->refresh();

        $this->assertSame(AiDrafIkhtisar::STATUS_DRAF, $draf->status);
        $this->assertNull($draf->error_ringkas);

        $isi = (array) $draf->isi;
        $sitasi = (array) $draf->sitasi;

        // SITASI DETERMINISTIK pipeline di kolom draf (bukan dari model).
        $this->assertNotEmpty($sitasi);
        $this->assertStringContainsString((string) $sumber->judul, (string) $sitasi[0]['label']);

        // AC T-A042: SETIAP poin tertaut sitasi_idx → indeks sah kolom sitasi
        // (poin → sumber tertelusur).
        $this->assertNotEmpty($isi['poin']);

        foreach ($isi['poin'] as $poin) {
            $this->assertNotSame('', trim((string) $poin['teks']));
            $this->assertNotEmpty($poin['sitasi_idx'], 'Setiap poin wajib tertaut sitasi');

            foreach ($poin['sitasi_idx'] as $idx) {
                $this->assertArrayHasKey((int) $idx, $sitasi, 'sitasi_idx wajib menunjuk entri sitasi yang ada');
            }
        }

        // Istilah kunci + pertanyaan diskusi tersimpan.
        $this->assertSame('nahwu', $isi['istilah'][0]['istilah']);
        $this->assertNotEmpty($isi['pertanyaan_diskusi']);

        // Satu draf = SATU panggilan generate (retrieval tunggal, hemat).
        $this->assertSame(1, $this->jumlahPanggilan('/api/generate'));

        // Prompt ke provider TIDAK memuat instruksi sitasi — sitasi murni
        // urusan pipeline (Blueprint §7.2) — dan konteks lewat blok DATA.
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
            ->where('jenis', 'ai_generator_ikhtisar')
            ->where('status', 'sent')
            ->get();
        $this->assertCount(1, $notif);
        $this->assertSame((int) $draf->getKey(), $notif->first()->payload['draf_id']);
    }

    // ------------------------------------------------------------------
    // (c) Kultum OFF (default): opsi tersembunyi + dua lapis penolakan
    // ------------------------------------------------------------------

    public function test_kultum_off_opsi_tersembunyi_dan_generate_ditolak(): void
    {
        $tenant = $this->siapkanTenant('ikhc');
        Filament::setCurrentPanel(Filament::getPanel('tenant'));
        $this->fakeOllama(responsGenerate: self::jsonKultumValid());
        $sumber = $this->ingestKitab();
        $asatidz = $this->buatAsatidz();
        $this->actingAs($asatidz);

        // Default konservatif: generator_kultum OFF → fitur tak terlihat.
        $this->assertFalse(IkhtisarGenerator::kultumAktif());

        // AC: form TIDAK menampilkan opsi kultum (assert render).
        Livewire::test(IkhtisarKitab::class)
            ->assertOk()
            ->assertSee('Ikhtisar materi kitab')
            ->assertDontSee('Kerangka kultum');

        // Lapis 1: buatDraf menolak santun.
        try {
            app(IkhtisarGenerator::class)->buatDraf(['jenis' => 'kultum', 'tema' => 'Adab menuntut ilmu'], $asatidz);
            $this->fail('Kultum dengan fitur OFF seharusnya ditolak di buatDraf.');
        } catch (\InvalidArgumentException $e) {
            $this->assertStringContainsString('belum diaktifkan', $e->getMessage());
        }

        // Lapis 2 (defense-in-depth): baris kultum yang lolos entah bagaimana
        // tetap ditolak PolicyEngine di gateway (fitur_per_peran) TANPA HTTP
        // generate — job berakhir gagal santun.
        $draf = AiDrafIkhtisar::create([
            'jenis' => 'kultum',
            'judul' => 'Kultum Adab menuntut ilmu',
            'tema' => 'Adab menuntut ilmu',
            'status' => AiDrafIkhtisar::STATUS_ANTRI,
            'isi' => [],
            'sitasi' => [],
            'dibuat_oleh' => (int) $asatidz->getKey(),
            'idempotency_key' => 'ikhtisar-'.Str::ulid(),
        ]);

        $this->jalankanJob($draf, $tenant);

        $draf->refresh();
        $this->assertSame(AiDrafIkhtisar::STATUS_GAGAL, $draf->status);
        $this->assertStringContainsString('tidak tersedia untuk akun Anda', (string) $draf->error_ringkas);
        $this->assertSame(0, $this->jumlahPanggilan('/api/generate'), 'Penolakan kebijakan TIDAK boleh memicu HTTP generate');
    }

    // ------------------------------------------------------------------
    // (d) Kultum ON via approval → kerangka + PERIKSA DALIL + disclaimer
    // ------------------------------------------------------------------

    public function test_kultum_on_kerangka_periksa_dalil_rujukan_kosong_dan_disclaimer(): void
    {
        $tenant = $this->siapkanTenant('ikhd');
        Filament::setCurrentPanel(Filament::getPanel('tenant'));
        // Alur approval setting (field sensitif → persetujuan kyai).
        $this->aktifkanFitur(['ikhtisar_kitab' => true, 'generator_kultum' => true]);
        $this->fakeOllama(responsGenerate: self::jsonKultumValid());
        $this->ingestKitab();
        $asatidz = $this->buatAsatidz();
        $this->actingAs($asatidz);

        $this->assertTrue(IkhtisarGenerator::kultumAktif());

        // Opsi kultum kini tampil di form.
        Livewire::test(IkhtisarKitab::class)
            ->assertOk()
            ->assertSee('Kerangka kultum');

        $draf = app(IkhtisarGenerator::class)->buatDraf([
            'jenis' => 'kultum',
            'tema' => 'Adab menuntut ilmu',
            'durasi_menit' => 7,
        ], $asatidz);

        $this->assertSame('Kultum Adab menuntut ilmu', $draf->judul);
        $this->assertSame(7, $draf->durasi_menit);

        $this->jalankanJob($draf, $tenant);
        $draf->refresh();

        $this->assertSame(AiDrafIkhtisar::STATUS_DRAF, $draf->status);

        $isi = (array) $draf->isi;

        // Kerangka = STRUKTUR (bukan naskah dalil).
        $this->assertNotEmpty($isi['kerangka']);

        // Normalisasi pipeline: SEMUA klaim berawalan [PERIKSA DALIL];
        // rujukan SELALU kosong walau model mengarang isinya (deterministik —
        // Aturan Emas: JANGAN mengarang teks ayat/hadits/dalil).
        $this->assertNotEmpty($isi['pengingat_dalil']);

        foreach ($isi['pengingat_dalil'] as $pengingat) {
            $this->assertStringStartsWith('[PERIKSA DALIL]', (string) $pengingat['klaim']);
            $this->assertSame('', (string) $pengingat['rujukan'], 'Rujukan dari model WAJIB dikosongkan pipeline');
        }

        $this->assertStringNotContainsString('HR. Karangan Model', json_encode($isi, JSON_UNESCAPED_UNICODE));

        // Disclaimer WAJIB melekat di output: teks kategori agama PRD §7.5
        // VERBATIM + teks prosedural tambahan dari config.
        $this->assertStringContainsString(
            'Ini alat bantu belajar, bukan fatwa. Untuk amalan, mohon merujuk kepada Ustadz/Kyai yang sanadnya jelas.',
            (string) $isi['disclaimer'],
        );
        $this->assertStringContainsString(
            'Kerangka ini wajib diperiksa dan dilengkapi dalil oleh Ustadz sebelum disampaikan.',
            (string) $isi['disclaimer'],
        );
    }

    // ------------------------------------------------------------------
    // (e) RAG-only: tanpa korpus → gagal santun TANPA HTTP
    // ------------------------------------------------------------------

    public function test_tanpa_korpus_gagal_santun_tanpa_http(): void
    {
        $tenant = $this->siapkanTenant('ikhe');
        $this->fakeOllama();
        $asatidz = $this->buatAsatidz();

        // Sumber terdaftar tapi TANPA chunk siap (belum diproses).
        $sumber = app(IngestService::class)->daftarkanSumber('Kitab Kosong', 'kurikulum', 'isi belum diproses');

        $draf = app(IkhtisarGenerator::class)->buatDraf([
            'jenis' => 'ikhtisar',
            'source_id' => (int) $sumber->getKey(),
        ], $asatidz);

        $this->jalankanJob($draf, $tenant);

        $draf->refresh();

        $this->assertSame(AiDrafIkhtisar::STATUS_GAGAL, $draf->status);
        $this->assertStringContainsString('Sumber Pengetahuan', (string) $draf->error_ringkas);

        // Pagar #2: TANPA korpus tidak boleh ada HTTP apa pun ke provider
        // (generate maupun embed — retriever berhenti sebelum embed).
        Http::assertNothingSent();

        $this->assertSame(1, LogNotifikasi::query()
            ->where('jenis', 'ai_generator_ikhtisar')
            ->where('status', 'failed')
            ->count());
    }

    // ------------------------------------------------------------------
    // (f) JSON model rusak → retry parse 1× → gagal rapi
    // ------------------------------------------------------------------

    public function test_json_rusak_retry_parse_sekali_lalu_gagal_rapi(): void
    {
        $tenant = $this->siapkanTenant('ikhf');
        $this->aktifkanFitur(['ikhtisar_kitab' => true]);
        $this->fakeOllama(responsGenerate: 'Baik, berikut ringkasannya: materi membahas adab dan istilah penting.');
        $sumber = $this->ingestKitab();
        $asatidz = $this->buatAsatidz();

        $draf = $this->buatDrafIkhtisar($asatidz, $sumber);
        $this->jalankanJob($draf, $tenant);

        $draf->refresh();

        $this->assertSame(AiDrafIkhtisar::STATUS_GAGAL, $draf->status);
        $this->assertStringContainsString('format dokumen yang valid', (string) $draf->error_ringkas);
        $this->assertSame([], (array) $draf->isi);

        // Tepat 2 panggilan generate: asli + retry parse-prompt — tidak lebih.
        $this->assertSame(2, $this->jumlahPanggilan('/api/generate'));

        $this->assertSame(1, LogNotifikasi::query()
            ->where('jenis', 'ai_generator_ikhtisar')
            ->where('status', 'failed')
            ->count());
    }

    // ------------------------------------------------------------------
    // (g) Pengesahan & edit isi (human-in-the-loop)
    // ------------------------------------------------------------------

    /** Draf ikhtisar berstatus draf dengan isi jadi (tanpa melewati LLM). */
    private function drafDenganIsi(?User $pembuat = null, string $jenis = 'ikhtisar'): AiDrafIkhtisar
    {
        $sitasi = [[
            'judul' => 'Kitab Adab Menuntut Ilmu',
            'bab' => 'BAB I',
            'halaman' => 3,
            'label' => 'Kitab Adab Menuntut Ilmu — BAB I, hlm. 3',
        ]];

        $isi = $jenis === 'kultum'
            ? [
                'kerangka' => ['Pembuka: pengantar tema.', 'Isi: poin utama.', 'Penutup: ajakan amal.'],
                'pengingat_dalil' => [['klaim' => '[PERIKSA DALIL] Menuntut ilmu adalah kewajiban.', 'rujukan' => '']],
                'disclaimer' => app(IkhtisarGenerator::class)->disclaimerKultum(),
            ]
            : [
                'poin' => [
                    ['teks' => 'Poin satu tentang adab.', 'sitasi_idx' => [0]],
                    ['teks' => 'Poin dua tentang istilah.', 'sitasi_idx' => [0]],
                ],
                'istilah' => [['istilah' => 'nahwu', 'arti' => 'tata bahasa']],
                'pertanyaan_diskusi' => ['Apa makna adab dalam materi?'],
            ];

        return AiDrafIkhtisar::create([
            'jenis' => $jenis,
            'judul' => $jenis === 'kultum' ? 'Kultum Adab' : 'Ikhtisar Kitab Adab Menuntut Ilmu — Bab 1',
            'tema' => $jenis === 'kultum' ? 'Adab' : null,
            'status' => AiDrafIkhtisar::STATUS_DRAF,
            'isi' => $isi,
            'sitasi' => $sitasi,
            'dibuat_oleh' => $pembuat?->getKey(),
            'idempotency_key' => 'ikhtisar-'.Str::ulid(),
        ]);
    }

    public function test_pengesahan_rbac_dan_jejak(): void
    {
        $this->siapkanTenant('ikhg');
        $asatidz = $this->buatAsatidz();
        $draf = $this->drafDenganIsi($asatidz);

        // Tanpa permission → ditolak.
        $polos = $this->buatUser('polos-'.uniqid().'@test.local');

        try {
            app(IkhtisarGenerator::class)->sahkan($draf, $polos);
            $this->fail('Pengesahan tanpa permission seharusnya ditolak.');
        } catch (AuthorizationException) {
            // diharapkan
        }

        $this->assertSame(AiDrafIkhtisar::STATUS_DRAF, $draf->refresh()->status);
        $this->assertNull($draf->disahkan_oleh);

        // Dengan permission (role asatidz) → jejak identitas + waktu.
        $hasil = app(IkhtisarGenerator::class)->sahkan($draf, $asatidz);

        $this->assertSame(AiDrafIkhtisar::STATUS_DISAHKAN, $hasil->status);
        $this->assertSame((int) $asatidz->getKey(), $hasil->disahkan_oleh);
        $this->assertNotNull($hasil->disahkan_pada);

        // Sudah disahkan → tidak bisa disahkan ulang maupun diedit.
        try {
            app(IkhtisarGenerator::class)->sahkan($hasil, $asatidz);
            $this->fail('Pengesahan ulang seharusnya ditolak.');
        } catch (\InvalidArgumentException) {
            // diharapkan
        }

        $this->expectException(\InvalidArgumentException::class);
        app(IkhtisarGenerator::class)->perbaruiIsi($hasil, [
            'poin' => [['teks' => 'Diubah setelah sah?']],
        ]);
    }

    public function test_edit_isi_tersimpan_sitasi_terkunci_dan_rujukan_dalil_diisi_ustadz(): void
    {
        $this->siapkanTenant('ikhh');

        // Ikhtisar: teks poin bisa diedit; sitasi_idx TERKUNCI.
        $draf = $this->drafDenganIsi();

        $hasil = app(IkhtisarGenerator::class)->perbaruiIsi($draf, [
            'poin' => [
                ['teks' => 'Poin satu yang sudah diperbaiki guru.', 'sitasi_idx' => [9, 9]], // upaya menimpa → diabaikan
            ],
            'istilah' => [
                ['istilah' => 'nahwu', 'arti' => 'ilmu tata bahasa Arab (revisi guru)'],
            ],
            'pertanyaan_diskusi' => ['Pertanyaan diskusi revisi guru?'],
        ]);

        $isi = (array) $hasil->isi;

        $this->assertSame('Poin satu yang sudah diperbaiki guru.', $isi['poin'][0]['teks']);
        $this->assertSame([0], $isi['poin'][0]['sitasi_idx'], 'sitasi_idx TERKUNCI dari editor');
        $this->assertSame('Poin dua tentang istilah.', $isi['poin'][1]['teks'], 'Butir tanpa edit tidak tersentuh');
        $this->assertSame('ilmu tata bahasa Arab (revisi guru)', $isi['istilah'][0]['arti']);
        $this->assertSame('Pertanyaan diskusi revisi guru?', $isi['pertanyaan_diskusi'][0]);
        $this->assertSame('Kitab Adab Menuntut Ilmu — BAB I, hlm. 3', ((array) $hasil->sitasi)[0]['label']);

        // Kultum: rujukan dalil justru DIISI ustadz; disclaimer TERKUNCI.
        $kultum = $this->drafDenganIsi(jenis: 'kultum');
        $disclaimerAwal = (string) ((array) $kultum->isi)['disclaimer'];

        $hasilKultum = app(IkhtisarGenerator::class)->perbaruiIsi($kultum, [
            'kerangka' => ['Pembuka yang disempurnakan ustadz.'],
            'pengingat_dalil' => [
                ['klaim' => '[PERIKSA DALIL] Menuntut ilmu adalah kewajiban.', 'rujukan' => 'Rujukan sahih hasil telaah ustadz'],
            ],
            'disclaimer' => 'upaya menghapus disclaimer', // wajib diabaikan
        ]);

        $isiKultum = (array) $hasilKultum->isi;

        $this->assertSame('Pembuka yang disempurnakan ustadz.', $isiKultum['kerangka'][0]);
        $this->assertSame('Rujukan sahih hasil telaah ustadz', $isiKultum['pengingat_dalil'][0]['rujukan']);
        $this->assertSame($disclaimerAwal, $isiKultum['disclaimer'], 'Disclaimer TERKUNCI dari editor');
    }

    // ------------------------------------------------------------------
    // (h) Idempotensi job — isi & kredit tidak dobel
    // ------------------------------------------------------------------

    public function test_idempotensi_job_isi_dan_kredit_tidak_dobel(): void
    {
        $tenant = $this->siapkanTenant('ikhi');
        $this->aktifkanFitur(['ikhtisar_kitab' => true]);
        $this->fakeOllama();
        $sumber = $this->ingestKitab();
        $asatidz = $this->buatAsatidz();

        $draf = $this->buatDrafIkhtisar($asatidz, $sumber);
        $this->jalankanJob($draf, $tenant);

        $draf->refresh();
        $this->assertSame(AiDrafIkhtisar::STATUS_DRAF, $draf->status);
        $this->assertCount(2, (array) $draf->isi['poin']);

        $generateAwal = $this->jumlahPanggilan('/api/generate');
        $ledgerAwal = AiCreditLedger::query()
            ->where('idempotency_key', 'like', $draf->idempotency_key.'%')
            ->count();
        $this->assertGreaterThan(0, $ledgerAwal, 'Pemakaian generate wajib termeter');

        // (1) Re-run saat sudah final → no-op total (tanpa HTTP baru).
        $this->jalankanJob($draf, $tenant);

        $this->assertCount(2, (array) $draf->refresh()->isi['poin']);
        $this->assertSame($generateAwal, $this->jumlahPanggilan('/api/generate'));

        // (2) Retry "paksa" (status kembali antri, mis. job redelivery):
        // HTTP boleh terjadi lagi, tapi kunci metering DETERMINISTIK
        // ("{key}:g1") → ledger TIDAK bertambah (dedup T-A002) & isi TIDAK dobel.
        $draf->update(['status' => AiDrafIkhtisar::STATUS_ANTRI]);
        $this->jalankanJob($draf, $tenant);

        $draf->refresh();
        $this->assertSame(AiDrafIkhtisar::STATUS_DRAF, $draf->status);
        $this->assertCount(2, (array) $draf->isi['poin']);

        $this->assertSame($ledgerAwal, AiCreditLedger::query()
            ->where('idempotency_key', 'like', $draf->idempotency_key.'%')
            ->count(), 'Retry job tidak boleh menggandakan kredit');
    }

    // ------------------------------------------------------------------
    // (i) Isolasi tenant + RBAC halaman + seeder agen + cetak
    // ------------------------------------------------------------------

    public function test_isolasi_tenant_draf_tidak_bocor(): void
    {
        $this->siapkanTenant('ikhj');
        $this->drafDenganIsi();
        $this->assertSame(1, AiDrafIkhtisar::query()->count());

        tenancy()->end();
        $this->siapkanTenant('ikhk');

        $this->assertSame(0, AiDrafIkhtisar::query()->count(), 'Draf tenant lain TIDAK boleh terlihat');
    }

    public function test_rbac_halaman_ikhtisar_kitab(): void
    {
        $this->siapkanTenant('ikhl');
        Filament::setCurrentPanel(Filament::getPanel('tenant'));

        $this->assertTrue(Route::has('filament.tenant.pages.ikhtisar-kitab-ai'));
        $this->assertTrue(Route::has('ai.draf-ikhtisar.cetak'));

        // Tanpa izin: tertutup.
        $this->actingAs($this->buatUser('polos-rbac-ikh@test.local'));
        $this->assertFalse(IkhtisarKitab::canAccess());

        // Role asatidz (permission via seeder): terbuka + halaman dirender.
        $this->actingAs($this->buatAsatidz());
        $this->assertTrue(IkhtisarKitab::canAccess());
        Livewire::test(IkhtisarKitab::class)->assertOk();
    }

    public function test_seeder_agen_ikhtisar_dan_kultum_idempoten_dan_netral(): void
    {
        $this->seed(AiAgentSeeder::class);
        $this->seed(AiAgentSeeder::class); // idempoten

        foreach (['asatidz_ikhtisar', 'asatidz_kultum'] as $key) {
            $agen = AiAgent::query()->where('key', $key)->first();
            $this->assertNotNull($agen, "Agen {$key} wajib terseed");
            $this->assertTrue((bool) $agen->is_active);
            $this->assertSame('standar', $agen->default_tier);

            $versiAktif = AiPromptVersion::query()
                ->where('agent_key', $key)
                ->where('status', AiPromptVersion::STATUS_AKTIF)
                ->get();

            $this->assertCount(1, $versiAktif, "Tepat satu versi prompt aktif untuk {$key}");

            $prompt = (string) $versiAktif->first()->system_prompt;
            $this->assertStringContainsString('JSON', $prompt);
            $this->assertStringContainsString('bahasa Indonesia', $prompt);
            // TANPA instruksi sitasi (deterministik pipeline) & netral.
            $this->assertStringNotContainsString('sitasi', mb_strtolower($prompt));
        }

        // Prompt kultum NETRAL-PROSEDURAL: penanda periksa + larangan
        // menulis teks ayat/hadits (struktur, BUKAN konten dalil).
        $promptKultum = (string) AiPromptVersion::query()
            ->where('agent_key', 'asatidz_kultum')
            ->where('status', AiPromptVersion::STATUS_AKTIF)
            ->value('system_prompt');

        $this->assertStringContainsString('[PERIKSA DALIL]', $promptKultum);
        $this->assertStringContainsString('JANGAN menuliskan teks ayat', $promptKultum);
    }

    public function test_cetak_handout_print_friendly_dan_rbac(): void
    {
        $tenant = $this->siapkanTenant('ikhm');
        $asatidz = $this->buatAsatidz();

        // Ikhtisar: poin + istilah + diskusi + sumber, bertanda DRAF.
        $draf = $this->drafDenganIsi($asatidz);
        $cetak = $this->actingAs($asatidz)
            ->get($this->urlTenant($tenant, '/ai/draf-ikhtisar/'.$draf->getKey().'/cetak'));

        $cetak->assertOk();
        $cetak->assertHeader('Content-Type', 'text/html; charset=UTF-8');
        $cetak->assertSee('DRAF — BELUM DISAHKAN');
        $cetak->assertSee('Poin Pembelajaran');
        $cetak->assertSee('Poin satu tentang adab.');
        $cetak->assertSee('Istilah Kunci');
        $cetak->assertSee('Pertanyaan Diskusi');
        $cetak->assertSee('Kitab Adab Menuntut Ilmu — BAB I, hlm. 3');

        // Kultum: kerangka + blok PERIKSA DALIL menonjol + disclaimer wajib.
        $kultum = $this->drafDenganIsi($asatidz, jenis: 'kultum');
        $cetakKultum = $this->actingAs($asatidz)
            ->get($this->urlTenant($tenant, '/ai/draf-ikhtisar/'.$kultum->getKey().'/cetak'));

        $cetakKultum->assertOk();
        $cetakKultum->assertSee('PERIKSA DALIL — wajib dilengkapi Ustadz');
        $cetakKultum->assertSee('Ini alat bantu belajar, bukan fatwa.');
        $cetakKultum->assertSee('Kerangka ini wajib diperiksa dan dilengkapi dalil oleh Ustadz sebelum disampaikan.');

        // Tanpa permission → 403 (pagar sama dengan halaman).
        $polos = $this->buatUser('polos-cetak-ikh@test.local');
        $this->actingAs($polos)
            ->get($this->urlTenant($tenant, '/ai/draf-ikhtisar/'.$draf->getKey().'/cetak'))
            ->assertForbidden();

        // Draf berstatus antri/menyusun/gagal tidak punya lembar cetak.
        $antri = AiDrafIkhtisar::create([
            'jenis' => 'ikhtisar',
            'judul' => 'Ikhtisar Antri',
            'status' => AiDrafIkhtisar::STATUS_ANTRI,
            'isi' => [],
            'sitasi' => [],
            'idempotency_key' => 'ikhtisar-'.Str::ulid(),
        ]);

        $this->actingAs($asatidz)
            ->get($this->urlTenant($tenant, '/ai/draf-ikhtisar/'.$antri->getKey().'/cetak'))
            ->assertNotFound();
    }
}
