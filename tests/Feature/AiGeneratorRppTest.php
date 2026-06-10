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
use Modules\AI\Database\Seeders\AiTemplateRppSeeder;
use Modules\AI\Filament\Pages\GeneratorRpp;
use Modules\AI\Jobs\GenerateRppJob;
use Modules\AI\Models\AiAgent;
use Modules\AI\Models\AiApproval;
use Modules\AI\Models\AiCreditLedger;
use Modules\AI\Models\AiDrafRpp;
use Modules\AI\Models\AiKnowledgeSource;
use Modules\AI\Models\AiPromptVersion;
use Modules\AI\Models\AiTemplateRpp;
use Modules\AI\Models\AiTenantSetting;
use Modules\AI\Services\Generator\RppGenerator;
use Modules\AI\Services\Rag\IngestService;
use Modules\AI\Services\Settings\AiSettingProvisioner;
use Modules\AI\Services\Settings\AiTenantSettingDefaults;
use Modules\AI\Services\Settings\TenantSettingService;
use Modules\Notifikasi\Models\LogNotifikasi;
use Tests\Concerns\InteractsWithPpdbTestDatabase;
use Tests\TestCase;

/**
 * T-A041 — Generator RPP / Modul Ajar (kembaran pola AiGeneratorSoalTest):
 *  (a) seeder template default generik idempoten & NETRAL (tanpa regulasi
 *      di-hardcode) + CRUD template tervalidasi (kunci unik, hapus terjaga);
 *  (b) submit → draf antri + GenerateRppJob dispatched + input_guru disaring;
 *  (c) job sukses → isi per SEKSI mengikuti template 100% + SITASI
 *      DETERMINISTIK per seksi (model TIDAK diminta menulis sitasi) +
 *      seksi "salin" terisi dari input guru TANPA LLM (hemat — assert jumlah
 *      HTTP) + status draf + notifikasi;
 *  (d) RAG-only: tanpa korpus → gagal santun TANPA HTTP apa pun (pagar #2);
 *  (e) human-in-the-loop: pengesahan butuh permission ai.generator.rpp,
 *      jejak identitas+waktu; edit konten tersimpan, sitasi/judul TERKUNCI;
 *  (f) idempotensi job: jalan 2× → isi & kredit tidak dobel (kunci metering
 *      per seksi deterministik);
 *  (g) isolasi tenant; RBAC halaman + RBAC kelola template (ai.template.kelola);
 *      seeder agen asatidz_rpp idempoten; ekspor cetak + unduh ber-MIME Word.
 */
class AiGeneratorRppTest extends TestCase
{
    use InteractsWithPpdbTestDatabase;

    private const DIM = 1024;

    protected function setUp(): void
    {
        parent::setUp();

        ini_set('memory_limit', '512M');

        $this->ensurePpdbTestingSchemaReady([
            'ai_template_rpp', 'ai_draf_rpp', 'ai_knowledge_source', 'ai_chunk',
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

    /** Nyalakan fitur generator_rpp untuk peran asatidz (default OFF konservatif). */
    private function aktifkanFiturGeneratorRpp(): void
    {
        $fitur = AiTenantSettingDefaults::konservatif()['fitur_per_peran'];
        $fitur['asatidz']['generator_rpp'] = true;

        $this->terapkanSetting(['fitur_per_peran' => $fitur]);
    }

    /** Ustadz pemegang role asatidz (permission ai.generator.rpp via role seeder). */
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

    private const KONTEN_SEKSI = 'Santri memahami struktur kurikulum pelajaran bahasa Arab dan mampu menjelaskan pokok materi bab satu dengan kalimat sendiri.';

    /** Fake HTTP Ollama: embed deterministik + generate teks polos per seksi. */
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
                'response' => $responsGenerate ?? self::KONTEN_SEKSI,
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

    /** Template uji ramping: 1 seksi "salin" (identitas) + 2 seksi LLM. */
    private function templateUji(): AiTemplateRpp
    {
        return app(RppGenerator::class)->simpanTemplate([
            'nama' => 'Template Uji Tiga Seksi',
            'jenis_lembaga' => 'MTs',
            'aktif' => true,
            'seksi' => [
                ['kunci' => 'identitas', 'judul' => 'Identitas', 'petunjuk' => 'Salin data identitas dari isian guru.', 'wajib' => true],
                ['kunci' => 'tujuan', 'judul' => 'Tujuan Pembelajaran', 'petunjuk' => 'Turunkan tujuan pembelajaran dari materi.', 'wajib' => true],
                ['kunci' => 'materi', 'judul' => 'Materi Pembelajaran', 'petunjuk' => 'Ringkas pokok materi secara berurutan.', 'wajib' => true],
            ],
        ]);
    }

    private const IDENTITAS_GURU = 'MTs Uji — Bahasa Arab — Kelas 1 — 2 JP — Penyusun: Ust. Fulan';

    private function buatDrafSiap(User $asatidz, AiTemplateRpp $template): AiDrafRpp
    {
        return app(RppGenerator::class)->buatDraf([
            'template_id' => (int) $template->getKey(),
            'mapel' => 'Bahasa Arab',
            'kelas' => 'Tsanawiyah 1',
            'input_guru' => [
                'identitas' => self::IDENTITAS_GURU,
                'kunci_liar' => 'tidak dikenal template — wajib tersaring',
                'tujuan' => '   ', // kosong → tersaring
            ],
        ], $asatidz);
    }

    private function jalankanJob(AiDrafRpp $draf, Tenant $tenant): void
    {
        (new GenerateRppJob((int) $draf->getKey(), (string) $tenant->id))
            ->handle(app(RppGenerator::class));
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
    // (a) Seeder template default + CRUD template
    // ------------------------------------------------------------------

    public function test_seeder_template_default_idempoten_dan_netral_tanpa_regulasi(): void
    {
        $this->siapkanTenant('rppa'); // provisioning sudah memanggil seeder

        $this->assertSame(1, AiTemplateRpp::query()->count(), 'Provisioning men-seed satu template default');

        // Idempoten: seeder ulang TIDAK menambah/menimpa.
        $this->seed(AiTemplateRppSeeder::class);
        $this->seed(AiTemplateRppSeeder::class);
        $this->assertSame(1, AiTemplateRpp::query()->count());

        $template = AiTemplateRpp::query()->firstOrFail();
        $this->assertTrue((bool) $template->aktif);
        $this->assertNull($template->jenis_lembaga, 'Default generik — jenis lembaga milik tenant');

        $seksi = (array) $template->seksi;
        $this->assertSame(
            ['identitas', 'capaian', 'tujuan', 'materi', 'metode', 'langkah', 'asesmen', 'sumber'],
            array_column($seksi, 'kunci'),
            'Field umum RPP generik',
        );

        // Seksi identitas bertanda "salin" (tanpa LLM — RppGenerator).
        $this->assertTrue(RppGenerator::seksiSalin($seksi[0]));

        // NETRAL (Aturan Emas #11 + #8): tanpa taksonomi regulasi di seed.
        $json = json_encode($template->toArray(), JSON_UNESCAPED_UNICODE);
        $this->assertStringNotContainsString('KMA', (string) $json);
        $this->assertStringNotContainsString('939', (string) $json);
        $this->assertStringNotContainsString('1495', (string) $json);
    }

    public function test_crud_template_validasi_kunci_dan_hapus_terjaga(): void
    {
        $this->siapkanTenant('rppb');
        $service = app(RppGenerator::class);
        $admin = $this->buatUser('waka-'.uniqid().'@test.local', ['ai.generator.rpp', 'ai.template.kelola']);

        // Buat: kunci otomatis dari judul (slug) bila kosong.
        $template = $service->simpanTemplate([
            'nama' => 'Template Diniyah',
            'jenis_lembaga' => 'Diniyah',
            'seksi' => [
                ['judul' => 'Tujuan Pembelajaran', 'petunjuk' => 'Turunkan dari materi.', 'wajib' => true],
                ['kunci' => 'materi', 'judul' => 'Materi', 'petunjuk' => '', 'wajib' => false],
            ],
        ], $admin);

        $this->assertSame('Template Diniyah', $template->nama);
        $this->assertSame((int) $admin->getKey(), $template->dibuat_oleh);
        $this->assertSame(['tujuan_pembelajaran', 'materi'], array_column((array) $template->seksi, 'kunci'));

        // Ubah: struktur seksi sepenuhnya bisa diganti tenant.
        $template = $service->simpanTemplate([
            'nama' => 'Template Diniyah (rev)',
            'jenis_lembaga' => "Ma'had",
            'aktif' => false,
            'seksi' => [['kunci' => 'capaian', 'judul' => 'Capaian', 'petunjuk' => 'Rumuskan capaian.', 'wajib' => true]],
        ], $admin, $template);

        $this->assertSame('Template Diniyah (rev)', $template->nama);
        $this->assertFalse((bool) $template->aktif);
        $this->assertSame(['capaian'], array_column((array) $template->seksi, 'kunci'));

        // Kunci ganda ditolak rapi.
        try {
            $service->simpanTemplate([
                'nama' => 'Ganda',
                'seksi' => [
                    ['kunci' => 'materi', 'judul' => 'Materi A'],
                    ['kunci' => 'materi', 'judul' => 'Materi B'],
                ],
            ], $admin);
            $this->fail('Kunci seksi ganda seharusnya ditolak.');
        } catch (\InvalidArgumentException $e) {
            $this->assertStringContainsString('lebih dari satu kali', $e->getMessage());
        }

        // Tanpa seksi berjudul ditolak rapi.
        try {
            $service->simpanTemplate(['nama' => 'Kosong', 'seksi' => [['judul' => '  ']]], $admin);
            $this->fail('Template tanpa seksi seharusnya ditolak.');
        } catch (\InvalidArgumentException $e) {
            $this->assertStringContainsString('minimal satu seksi', $e->getMessage());
        }

        // Hapus template yang dipakai draf → ditolak santun (FK restrict).
        $dipakai = $this->templateUji();
        $asatidz = $this->buatAsatidz();
        $this->buatDrafSiap($asatidz, $dipakai);

        try {
            $service->hapusTemplate($dipakai);
            $this->fail('Hapus template terpakai seharusnya ditolak.');
        } catch (\InvalidArgumentException $e) {
            $this->assertStringContainsString('tidak dapat dihapus', $e->getMessage());
        }

        $this->assertNotNull(AiTemplateRpp::query()->find($dipakai->getKey()));

        // Template tak terpakai boleh dihapus.
        $service->hapusTemplate($template->refresh());
        $this->assertNull(AiTemplateRpp::query()->find($template->getKey()));
    }

    // ------------------------------------------------------------------
    // (b) Submit → draf antri + job + saringan input guru
    // ------------------------------------------------------------------

    public function test_submit_membuat_draf_antri_dan_dispatch_job(): void
    {
        $tenant = $this->siapkanTenant('rppc');
        $asatidz = $this->buatAsatidz();
        $template = $this->templateUji();

        $draf = $this->buatDrafSiap($asatidz, $template);

        $this->assertSame(AiDrafRpp::STATUS_ANTRI, $draf->status);
        $this->assertSame('RPP Bahasa Arab — Tsanawiyah 1', $draf->judul);
        $this->assertSame((int) $template->getKey(), $draf->template_id);
        $this->assertSame((int) $asatidz->getKey(), $draf->dibuat_oleh);
        $this->assertNotEmpty($draf->idempotency_key);
        $this->assertSame([], (array) $draf->isi);

        // Saringan input_guru: hanya kunci seksi template & non-kosong.
        $this->assertSame(['identitas' => self::IDENTITAS_GURU], (array) $draf->input_guru);

        Queue::assertPushed(
            GenerateRppJob::class,
            fn (GenerateRppJob $job): bool => $job->drafId === (int) $draf->getKey()
                && $job->tenantId === (string) $tenant->id,
        );

        // Input tidak valid ditolak rapi: template nonaktif.
        $nonaktif = app(RppGenerator::class)->simpanTemplate([
            'nama' => 'Nonaktif',
            'aktif' => false,
            'seksi' => [['kunci' => 'materi', 'judul' => 'Materi']],
        ], $asatidz);

        try {
            app(RppGenerator::class)->buatDraf([
                'template_id' => (int) $nonaktif->getKey(),
                'mapel' => 'Fikih',
            ], $asatidz);
            $this->fail('Template nonaktif seharusnya ditolak.');
        } catch (\InvalidArgumentException $e) {
            $this->assertStringContainsString('template RPP yang aktif', $e->getMessage());
        }

        // Mapel kosong ditolak rapi.
        $this->expectException(\InvalidArgumentException::class);
        app(RppGenerator::class)->buatDraf(['template_id' => (int) $template->getKey(), 'mapel' => '  '], $asatidz);
    }

    // ------------------------------------------------------------------
    // (c) Job sukses → isi per seksi + salin tanpa LLM + sitasi + notifikasi
    // ------------------------------------------------------------------

    public function test_job_sukses_seksi_salin_tanpa_llm_sitasi_per_seksi_dan_notifikasi(): void
    {
        $tenant = $this->siapkanTenant('rppd');
        $this->aktifkanFiturGeneratorRpp();
        $this->fakeOllama();
        $sumber = $this->ingestKurikulum();
        $asatidz = $this->buatAsatidz();
        $template = $this->templateUji();

        $draf = $this->buatDrafSiap($asatidz, $template);
        $this->jalankanJob($draf, $tenant);

        $draf->refresh();

        $this->assertSame(AiDrafRpp::STATUS_DRAF, $draf->status);
        $this->assertNull($draf->error_ringkas);

        $isi = (array) $draf->isi;

        // PRD T-A041: field template tenant dihormati 100% (urutan + kunci).
        $this->assertSame(
            array_column((array) $template->seksi, 'kunci'),
            array_column($isi, 'kunci'),
        );

        // Seksi "salin" (identitas): konten = isian guru VERBATIM, tanpa sitasi.
        $this->assertSame(self::IDENTITAS_GURU, $isi[0]['konten']);
        $this->assertSame([], (array) $isi[0]['sitasi']);

        // Seksi LLM: konten terisi + SITASI DETERMINISTIK per seksi dari
        // retrieval (bukan dari model).
        foreach ([1, 2] as $i) {
            $this->assertSame(self::KONTEN_SEKSI, $isi[$i]['konten']);
            $this->assertNotEmpty($isi[$i]['sitasi']);
            $this->assertStringContainsString(
                (string) $sumber->judul,
                (string) $isi[$i]['sitasi'][0]['label'],
            );
        }

        // HEMAT: 3 seksi, 1 di antaranya "salin" → TEPAT 2 panggilan generate.
        $this->assertSame(2, $this->jumlahPanggilan('/api/generate'));

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
            ->where('jenis', 'ai_generator_rpp')
            ->where('status', 'sent')
            ->get();
        $this->assertCount(1, $notif);
        $this->assertSame((int) $draf->getKey(), $notif->first()->payload['draf_id']);
    }

    // ------------------------------------------------------------------
    // (d) RAG-only: tanpa korpus → gagal santun TANPA HTTP
    // ------------------------------------------------------------------

    public function test_tanpa_korpus_gagal_santun_tanpa_http(): void
    {
        $tenant = $this->siapkanTenant('rppe');
        $this->fakeOllama();
        $asatidz = $this->buatAsatidz();

        $draf = $this->buatDrafSiap($asatidz, $this->templateUji());
        $this->jalankanJob($draf, $tenant);

        $draf->refresh();

        $this->assertSame(AiDrafRpp::STATUS_GAGAL, $draf->status);
        $this->assertStringContainsString('Sumber Pengetahuan', (string) $draf->error_ringkas);

        // Pagar #2: TANPA korpus tidak boleh ada HTTP apa pun ke provider
        // (generate maupun embed — retriever berhenti sebelum embed; seksi
        // "salin" pun tidak memicu apa-apa).
        Http::assertNothingSent();

        $this->assertSame(1, LogNotifikasi::query()
            ->where('jenis', 'ai_generator_rpp')
            ->where('status', 'failed')
            ->count());
    }

    // ------------------------------------------------------------------
    // (e) Pengesahan & edit isi (human-in-the-loop)
    // ------------------------------------------------------------------

    /** Draf berstatus draf dengan isi jadi (tanpa melewati LLM). */
    private function drafDenganIsi(?User $pembuat = null): AiDrafRpp
    {
        $template = AiTemplateRpp::query()->first() ?? $this->templateUji();

        $sitasi = [[
            'judul' => 'Struktur Kurikulum',
            'bab' => 'BAB I',
            'halaman' => 3,
            'label' => 'Struktur Kurikulum — BAB I, hlm. 3',
        ]];

        return AiDrafRpp::create([
            'template_id' => (int) $template->getKey(),
            'judul' => 'RPP Bahasa Arab — Tsanawiyah 1',
            'mapel' => 'Bahasa Arab',
            'kelas' => 'Tsanawiyah 1',
            'status' => AiDrafRpp::STATUS_DRAF,
            'isi' => [
                ['kunci' => 'identitas', 'judul' => 'Identitas', 'konten' => self::IDENTITAS_GURU, 'sitasi' => []],
                ['kunci' => 'tujuan', 'judul' => 'Tujuan Pembelajaran', 'konten' => 'Tujuan satu.', 'sitasi' => $sitasi],
            ],
            'dibuat_oleh' => $pembuat?->getKey(),
            'idempotency_key' => 'rpp-'.Str::ulid(),
        ]);
    }

    public function test_pengesahan_tanpa_permission_ditolak(): void
    {
        $this->siapkanTenant('rppf');
        $draf = $this->drafDenganIsi();
        $polos = $this->buatUser('polos-'.uniqid().'@test.local');

        try {
            app(RppGenerator::class)->sahkan($draf, $polos);
            $this->fail('Pengesahan tanpa permission seharusnya ditolak.');
        } catch (AuthorizationException) {
            // diharapkan
        }

        $this->assertSame(AiDrafRpp::STATUS_DRAF, $draf->refresh()->status);
        $this->assertNull($draf->disahkan_oleh);
    }

    public function test_pengesahan_dengan_permission_mencatat_jejak(): void
    {
        $this->siapkanTenant('rppg');
        $asatidz = $this->buatAsatidz();
        $draf = $this->drafDenganIsi($asatidz);

        $hasil = app(RppGenerator::class)->sahkan($draf, $asatidz);

        $this->assertSame(AiDrafRpp::STATUS_DISAHKAN, $hasil->status);
        $this->assertSame((int) $asatidz->getKey(), $hasil->disahkan_oleh);
        $this->assertNotNull($hasil->disahkan_pada);

        // Sudah disahkan → tidak bisa disahkan ulang maupun diedit.
        try {
            app(RppGenerator::class)->sahkan($hasil, $asatidz);
            $this->fail('Pengesahan ulang seharusnya ditolak.');
        } catch (\InvalidArgumentException) {
            // diharapkan
        }

        $this->expectException(\InvalidArgumentException::class);
        app(RppGenerator::class)->perbaruiIsi($hasil, [
            ['kunci' => 'tujuan', 'konten' => 'Diubah setelah sah?'],
        ]);
    }

    public function test_edit_isi_tersimpan_dan_sitasi_judul_terkunci(): void
    {
        $this->siapkanTenant('rpph');
        $draf = $this->drafDenganIsi();

        $hasil = app(RppGenerator::class)->perbaruiIsi($draf, [
            [
                'kunci' => 'tujuan',
                'konten' => 'Tujuan pembelajaran yang sudah diperbaiki guru.',
                // Upaya menimpa sitasi/judul dari editor WAJIB diabaikan.
                'judul' => 'Judul Palsu',
                'sitasi' => [['judul' => 'Karangan Palsu', 'label' => 'Karangan Palsu']],
            ],
        ]);

        $isi = (array) $hasil->isi;

        $this->assertSame('Tujuan pembelajaran yang sudah diperbaiki guru.', $isi[1]['konten']);
        $this->assertSame('Tujuan Pembelajaran', $isi[1]['judul']);
        $this->assertSame('Struktur Kurikulum — BAB I, hlm. 3', $isi[1]['sitasi'][0]['label']);

        // Seksi lain tidak tersentuh.
        $this->assertSame(self::IDENTITAS_GURU, $isi[0]['konten']);
    }

    // ------------------------------------------------------------------
    // (f) Idempotensi job — isi & kredit tidak dobel
    // ------------------------------------------------------------------

    public function test_idempotensi_job_isi_dan_kredit_tidak_dobel(): void
    {
        $tenant = $this->siapkanTenant('rppi');
        $this->aktifkanFiturGeneratorRpp();
        $this->fakeOllama();
        $this->ingestKurikulum();
        $asatidz = $this->buatAsatidz();

        $draf = $this->buatDrafSiap($asatidz, $this->templateUji());
        $this->jalankanJob($draf, $tenant);

        $draf->refresh();
        $this->assertSame(AiDrafRpp::STATUS_DRAF, $draf->status);
        $this->assertCount(3, (array) $draf->isi);

        $generateAwal = $this->jumlahPanggilan('/api/generate');
        $ledgerAwal = AiCreditLedger::query()
            ->where('idempotency_key', 'like', $draf->idempotency_key.'%')
            ->count();
        $this->assertGreaterThan(0, $ledgerAwal, 'Pemakaian generate wajib termeter');

        // (1) Re-run saat sudah final → no-op total (tanpa HTTP baru).
        $this->jalankanJob($draf, $tenant);

        $this->assertCount(3, (array) $draf->refresh()->isi);
        $this->assertSame($generateAwal, $this->jumlahPanggilan('/api/generate'));

        // (2) Retry "paksa" (status kembali antri, mis. job redelivery):
        // HTTP boleh terjadi lagi, tapi kunci metering per seksi DETERMINISTIK
        // ("{key}:s{kunci}") → ledger TIDAK bertambah (dedup T-A002) & isi
        // TIDAK dobel.
        $draf->update(['status' => AiDrafRpp::STATUS_ANTRI]);
        $this->jalankanJob($draf, $tenant);

        $draf->refresh();
        $this->assertSame(AiDrafRpp::STATUS_DRAF, $draf->status);
        $this->assertCount(3, (array) $draf->isi);
        $this->assertSame(
            ['identitas', 'tujuan', 'materi'],
            array_column((array) $draf->isi, 'kunci'),
        );

        $this->assertSame($ledgerAwal, AiCreditLedger::query()
            ->where('idempotency_key', 'like', $draf->idempotency_key.'%')
            ->count(), 'Retry job tidak boleh menggandakan kredit');
    }

    // ------------------------------------------------------------------
    // (g) Isolasi tenant + RBAC halaman/template + seeder agen + ekspor
    // ------------------------------------------------------------------

    public function test_isolasi_tenant_draf_dan_template_tidak_bocor(): void
    {
        $this->siapkanTenant('rppj');
        $this->drafDenganIsi();
        app(RppGenerator::class)->simpanTemplate([
            'nama' => 'Template Khusus Tenant Satu',
            'seksi' => [['kunci' => 'materi', 'judul' => 'Materi']],
        ]);
        $this->assertSame(1, AiDrafRpp::query()->count());
        $this->assertSame(2, AiTemplateRpp::query()->count()); // default + khusus

        tenancy()->end();
        $this->siapkanTenant('rppk');

        $this->assertSame(0, AiDrafRpp::query()->count(), 'Draf tenant lain TIDAK boleh terlihat');
        $this->assertSame(1, AiTemplateRpp::query()->count(), 'Hanya template default milik tenant ini');
        $this->assertSame(0, AiTemplateRpp::query()->where('nama', 'Template Khusus Tenant Satu')->count());
    }

    public function test_rbac_halaman_generator_rpp_dan_kelola_template(): void
    {
        $this->siapkanTenant('rppl');
        Filament::setCurrentPanel(Filament::getPanel('tenant'));

        $this->assertTrue(Route::has('filament.tenant.pages.generator-rpp-ai'));
        $this->assertTrue(Route::has('ai.draf-rpp.cetak'));
        $this->assertTrue(Route::has('ai.draf-rpp.unduh'));

        // Tanpa izin: tertutup.
        $this->actingAs($this->buatUser('polos-rbac-rpp@test.local'));
        $this->assertFalse(GeneratorRpp::canAccess());

        // Role asatidz (permission via seeder): terbuka + halaman dirender,
        // tapi KELOLA TEMPLATE tertutup (butuh ai.template.kelola).
        $asatidz = $this->buatAsatidz();
        $this->actingAs($asatidz);
        $this->assertTrue(GeneratorRpp::canAccess());

        $halaman = Livewire::test(GeneratorRpp::class);
        $halaman->assertOk();
        $this->assertFalse($halaman->instance()->bolehKelolaTemplate());
        $halaman->call('bukaTemplateBaru')->assertForbidden();

        // Pemegang ai.template.kelola (admin/Wakakurikulum): boleh kelola.
        $waka = $this->buatUser('waka-rbac@test.local', ['ai.generator.rpp', 'ai.template.kelola']);
        $this->actingAs($waka);

        $halamanWaka = Livewire::test(GeneratorRpp::class);
        $halamanWaka->assertOk();
        $this->assertTrue($halamanWaka->instance()->bolehKelolaTemplate());
    }

    public function test_seeder_agen_asatidz_rpp_idempoten_dan_netral(): void
    {
        $this->seed(AiAgentSeeder::class);
        $this->seed(AiAgentSeeder::class); // idempoten

        $agen = AiAgent::query()->where('key', 'asatidz_rpp')->first();
        $this->assertNotNull($agen);
        $this->assertTrue((bool) $agen->is_active);
        $this->assertSame('standar', $agen->default_tier);

        $versiAktif = AiPromptVersion::query()
            ->where('agent_key', 'asatidz_rpp')
            ->where('status', AiPromptVersion::STATUS_AKTIF)
            ->get();

        $this->assertCount(1, $versiAktif, 'Tepat satu versi prompt aktif');

        $prompt = (string) $versiAktif->first()->system_prompt;
        $this->assertStringContainsString('bahasa Indonesia', $prompt);
        $this->assertStringContainsString('SATU seksi', $prompt);
        // TANPA instruksi sitasi (deterministik pipeline) & netral tanpa
        // muatan regulasi/keagamaan (Aturan Emas #8/#11).
        $this->assertStringNotContainsString('sitasi', mb_strtolower($prompt));
        $this->assertStringNotContainsString('KMA', $prompt);
    }

    public function test_ekspor_cetak_dan_unduh_word_menghasilkan_berkas(): void
    {
        $tenant = $this->siapkanTenant('rppm');
        $asatidz = $this->buatAsatidz();
        $draf = $this->drafDenganIsi($asatidz);

        // Cetak: print-friendly + tanda DRAF jelas.
        $cetak = $this->actingAs($asatidz)
            ->get($this->urlTenant($tenant, '/ai/draf-rpp/'.$draf->getKey().'/cetak'));

        $cetak->assertOk();
        $cetak->assertSee('DRAF — BELUM DISAHKAN');
        $cetak->assertSee('Tujuan Pembelajaran');
        $cetak->assertSee('Struktur Kurikulum — BAB I, hlm. 3');

        // Unduh: berkas ber-MIME Word (HTML kompatibel Word — phpword tidak
        // tersedia di vendor; keputusan T-A041 tanpa dependency baru).
        $unduh = $this->actingAs($asatidz)
            ->get($this->urlTenant($tenant, '/ai/draf-rpp/'.$draf->getKey().'/unduh'));

        $unduh->assertOk();
        $unduh->assertHeader('Content-Type', 'application/vnd.ms-word; charset=UTF-8');
        $this->assertStringContainsString('attachment;', (string) $unduh->headers->get('Content-Disposition'));
        $this->assertStringContainsString('.doc', (string) $unduh->headers->get('Content-Disposition'));
        $this->assertStringContainsString('Tujuan Pembelajaran', $unduh->getContent());
        $this->assertStringContainsString(self::IDENTITAS_GURU, $unduh->getContent());

        // Tanpa permission → 403 (pagar sama dengan halaman).
        $polos = $this->buatUser('polos-unduh@test.local');
        $this->actingAs($polos)
            ->get($this->urlTenant($tenant, '/ai/draf-rpp/'.$draf->getKey().'/unduh'))
            ->assertForbidden();
    }
}
