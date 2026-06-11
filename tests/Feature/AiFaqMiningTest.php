<?php

namespace Tests\Feature;

use App\Models\Tenant;
use App\Models\User;
use App\Services\StorageQuotaService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Filament\Facades\Filament;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Modules\AI\Filament\Pages\UsulanFaq;
use Modules\AI\Jobs\IngestDocumentJob;
use Modules\AI\Models\AiChunk;
use Modules\AI\Models\AiConversation;
use Modules\AI\Models\AiEscalation;
use Modules\AI\Models\AiFaqDraf;
use Modules\AI\Models\AiKnowledgeSource;
use Modules\AI\Models\AiMessage;
use Modules\AI\Services\Rag\IngestService;
use Modules\AI\Services\Research\FaqMiner;
use Modules\Notifikasi\Models\LogNotifikasi;
use Tests\Concerns\InteractsWithPpdbTestDatabase;
use Tests\TestCase;

/**
 * FAQ-Mining (permintaan owner 2026-06-11) — chatbot "belajar dari kebiasaan
 * pengguna" secara HUMAN-IN-THE-LOOP, tanpa training model:
 *  (a) tambang mengelompokkan pertanyaan mirip (≥ min frekuensi) jadi SATU
 *      usulan + variasi; pertanyaan langka tidak diusulkan;
 *  (b) kategori eskalasi sensitif (blacklist_topik/level_fatwa — wilayah
 *      ustadz) & percakapan handoff TIDAK ikut ditambang;
 *  (c) idempoten: tambang 2× tidak menggandakan baris; data baru →
 *      frekuensi di-update pada baris yang sama;
 *  (d) sahkan tanpa jawaban DITOLAK; tanpa permission DITOLAK;
 *  (e) sahkan → Sumber Pengetahuan kategori 'faq' terbuat (diunggah → siap
 *      via fake embed) + draf disahkan + source_id terisi; sahkan ulang DITOLAK;
 *  (f) abaikan → diabaikan; keputusan dihormati (tidak diusulkan ulang);
 *  (g) RBAC halaman UsulanFaq (ai.pengetahuan.kelola) + badge usulan;
 *  (h) isolasi tenant;
 *  (i) PRIVASI: seed pesan ber-nomor HP mentah → usulan tetap terredaksi
 *      (sabuk pengaman redactPaksa di FaqMiner);
 *  (j) perintah ai:faq-mining → tambang per tenant + LogNotifikasi ke
 *      pemegang ai.pengetahuan.kelola HANYA bila ada usulan baru.
 */
class AiFaqMiningTest extends TestCase
{
    use InteractsWithPpdbTestDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->ensurePpdbTestingSchemaReady([
            'ai_faq_draf', 'ai_escalation', 'ai_conversation', 'ai_message',
            'ai_knowledge_source', 'ai_chunk', 'log_notifikasi',
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

        return $tenant;
    }

    private function miner(): FaqMiner
    {
        return app(FaqMiner::class);
    }

    private function buatEskalasi(string $teks, string $kategori = 'tanpa_rujukan'): void
    {
        AiEscalation::create([
            'kanal' => 'wa',
            'pertanyaan_ringkas' => $teks,
            'kategori' => $kategori,
            'status' => AiEscalation::STATUS_BARU,
        ]);
    }

    private function buatPercakapan(string $nomor, string $status = AiConversation::STATUS_AKTIF): AiConversation
    {
        return AiConversation::create([
            'kanal' => 'wa',
            'nomor_wa_hash' => AiConversation::hashNomor($nomor),
            'nomor_wa_terenkripsi' => 'terenkripsi-uji',
            'status' => $status,
        ]);
    }

    private function buatPesanMasuk(AiConversation $percakapan, string $teks): void
    {
        AiMessage::create([
            'conversation_id' => $percakapan->getKey(),
            'arah' => AiMessage::ARAH_MASUK,
            'isi_terredaksi' => $teks,
        ]);
    }

    private function buatPengelola(string $email = 'pengelola-faq@test.local'): User
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        $user = User::create([
            'name' => 'Pengelola Pengetahuan',
            'email' => $email,
            'password' => 'password',
        ]);
        $user->givePermissionTo('ai.pengetahuan.kelola');

        return $user;
    }

    /** Fake endpoint embed Ollama (pola AiIngestRagTest). */
    private function fakeEmbedSukses(): void
    {
        Http::fake([
            '*/api/embed' => function ($request) {
                $inputs = (array) ($request['input'] ?? []);

                return Http::response([
                    'embeddings' => array_map(
                        static fn () => array_fill(0, 1024, 0.01),
                        $inputs,
                    ),
                ]);
            },
        ]);
    }

    // ------------------------------------------------------------------
    // (a) Tambang mengelompokkan pertanyaan mirip jadi satu usulan + variasi
    // ------------------------------------------------------------------

    public function test_tambang_mengelompokkan_pertanyaan_mirip_jadi_satu_usulan_dengan_variasi(): void
    {
        $this->siapkanTenant('faqa');

        // Tiga pertanyaan serupa lintas-sumber (1 eskalasi + 2 pesan WA).
        $this->buatEskalasi('Berapa biaya pendaftaran santri baru?');
        $percakapan = $this->buatPercakapan('628111000001');
        $this->buatPesanMasuk($percakapan, 'Biaya pendaftaran berapa ya?');
        $this->buatPesanMasuk($percakapan, 'mau tanya biaya pendaftaran');

        // Pertanyaan langka (1×) — tidak boleh jadi usulan.
        $this->buatPesanMasuk($percakapan, 'Kapan jadwal libur semester?');

        $stat = $this->miner()->tambang();

        $this->assertSame(1, $stat['usulan_baru']);
        $this->assertSame(1, AiFaqDraf::query()->count(), 'Tiga pertanyaan mirip wajib jadi SATU usulan');

        /** @var AiFaqDraf $draf */
        $draf = AiFaqDraf::query()->firstOrFail();

        $this->assertSame(AiFaqDraf::STATUS_USULAN, $draf->status);
        $this->assertSame(3, $draf->frekuensi);
        $this->assertCount(2, $draf->variasi, 'Dua varian lain tersimpan sebagai variasi');
        $this->assertStringContainsStringIgnoringCase('biaya pendaftaran', $draf->pertanyaan_perwakilan);
        // Ada anggota dari eskalasi → sumber tercatat 'eskalasi'.
        $this->assertSame(AiFaqDraf::SUMBER_ESKALASI, $draf->sumber);
        $this->assertNotNull($draf->dibuat_periode);
        $this->assertNull($draf->jawaban);
        $this->assertNull($draf->source_id, 'Belum disahkan = belum menyentuh Sumber Pengetahuan');
    }

    // ------------------------------------------------------------------
    // (b) Kategori sensitif & percakapan handoff dilewati
    // ------------------------------------------------------------------

    public function test_kategori_hukum_blacklist_dan_percakapan_handoff_tidak_ditambang(): void
    {
        $this->siapkanTenant('faqb');

        // Wilayah ustadz: pertanyaan hukum & topik terlarang ≥ 3× — SKIP.
        foreach (range(1, 3) as $i) {
            $this->buatEskalasi('Apa hukum zakat fitrah dibayar dengan uang?', 'level_fatwa');
            $this->buatEskalasi('Pertanyaan topik terlarang pesantren ujian', 'blacklist_topik');
        }

        // Percakapan handoff (ditangani manusia) ≥ 3× — SKIP.
        $handoff = $this->buatPercakapan('628111000002', AiConversation::STATUS_HANDOFF);
        foreach (range(1, 3) as $i) {
            $this->buatPesanMasuk($handoff, 'Komplain pembayaran belum tercatat sistem');
        }

        // Pembanding sehat: kategori non-sensitif ≥ 3× — DIUSULKAN.
        foreach (range(1, 3) as $i) {
            $this->buatEskalasi('Bagaimana prosedur izin pulang santri?');
        }

        $stat = $this->miner()->tambang();

        $this->assertSame(1, $stat['usulan_baru']);
        $this->assertSame(1, AiFaqDraf::query()->count());
        $this->assertStringContainsStringIgnoringCase(
            'prosedur izin pulang',
            AiFaqDraf::query()->firstOrFail()->pertanyaan_perwakilan,
        );
    }

    // ------------------------------------------------------------------
    // (c) Idempoten: tambang 2× tidak dobel; frekuensi di-update
    // ------------------------------------------------------------------

    public function test_tambang_idempoten_dan_memperbarui_frekuensi(): void
    {
        $this->siapkanTenant('faqc');

        $percakapan = $this->buatPercakapan('628111000003');
        $this->buatPesanMasuk($percakapan, 'Berapa biaya pendaftaran?');
        $this->buatPesanMasuk($percakapan, 'biaya pendaftaran berapa ya');
        $this->buatPesanMasuk($percakapan, 'Mohon info biaya pendaftaran santri baru');

        $statPertama = $this->miner()->tambang();
        $this->assertSame(1, $statPertama['usulan_baru']);
        $this->assertSame(3, AiFaqDraf::query()->firstOrFail()->frekuensi);

        // Tambang ulang TANPA data baru: tidak ada baris baru.
        $statKedua = $this->miner()->tambang();
        $this->assertSame(0, $statKedua['usulan_baru']);
        $this->assertSame(1, $statKedua['diperbarui']);
        $this->assertSame(1, AiFaqDraf::query()->count(), 'Tambang ulang tidak boleh menggandakan usulan');

        // Data baru masuk → frekuensi baris yang SAMA di-update.
        $this->buatPesanMasuk($percakapan, 'berapa ya biaya pendaftaran');
        $this->miner()->tambang();

        $this->assertSame(1, AiFaqDraf::query()->count());
        $this->assertSame(4, AiFaqDraf::query()->firstOrFail()->frekuensi);
    }

    // ------------------------------------------------------------------
    // (d) Sahkan tanpa jawaban / tanpa izin ditolak
    // ------------------------------------------------------------------

    public function test_sahkan_tanpa_jawaban_ditolak(): void
    {
        $this->siapkanTenant('faqd');
        $pengelola = $this->buatPengelola();

        $draf = AiFaqDraf::create([
            'pertanyaan_perwakilan' => 'Berapa biaya pendaftaran?',
            'pertanyaan_hash' => hash('sha256', 'biaya pendaftaran'),
            'variasi' => [],
            'frekuensi' => 3,
            'sumber' => AiFaqDraf::SUMBER_PERCAKAPAN,
            'status' => AiFaqDraf::STATUS_USULAN,
            'dibuat_periode' => now()->startOfMonth()->toDateString(),
        ]);

        try {
            $this->miner()->sahkan((int) $draf->getKey(), $pengelola);
            $this->fail('Sahkan tanpa jawaban seharusnya ditolak.');
        } catch (\InvalidArgumentException $e) {
            $this->assertStringContainsString('jawaban', mb_strtolower($e->getMessage()));
        }

        $this->assertSame(AiFaqDraf::STATUS_USULAN, $draf->refresh()->status);
        $this->assertSame(0, AiKnowledgeSource::query()->count(), 'RAG tidak boleh tersentuh');
    }

    public function test_sahkan_tanpa_permission_ditolak(): void
    {
        $this->siapkanTenant('faqe');
        $this->seed(RolesAndPermissionsSeeder::class);

        $tanpaIzin = User::create([
            'name' => 'Tanpa Izin',
            'email' => 'tanpa-izin-faq@test.local',
            'password' => 'password',
        ]);

        $draf = AiFaqDraf::create([
            'pertanyaan_perwakilan' => 'Berapa biaya pendaftaran?',
            'pertanyaan_hash' => hash('sha256', 'biaya pendaftaran'),
            'variasi' => [],
            'frekuensi' => 3,
            'sumber' => AiFaqDraf::SUMBER_PERCAKAPAN,
            'jawaban' => 'Biaya pendaftaran Rp500.000.',
            'status' => AiFaqDraf::STATUS_DIJAWAB,
            'dibuat_periode' => now()->startOfMonth()->toDateString(),
        ]);

        $this->expectException(AuthorizationException::class);

        $this->miner()->sahkan((int) $draf->getKey(), $tanpaIzin);
    }

    // ------------------------------------------------------------------
    // (e) Sahkan → Sumber Pengetahuan kategori faq + status disahkan
    // ------------------------------------------------------------------

    public function test_sahkan_membuat_sumber_pengetahuan_faq_dan_menandai_disahkan(): void
    {
        $this->siapkanTenant('faqf');
        Storage::fake(StorageQuotaService::diskName());
        Queue::fake();
        $this->fakeEmbedSukses();

        $pengelola = $this->buatPengelola();

        $draf = AiFaqDraf::create([
            'pertanyaan_perwakilan' => 'Berapa biaya pendaftaran santri baru?',
            'pertanyaan_hash' => hash('sha256', 'baru biaya pendaftaran santri'),
            'variasi' => ['biaya pendaftaran berapa ya', 'mau tanya biaya pendaftaran'],
            'frekuensi' => 5,
            'sumber' => AiFaqDraf::SUMBER_ESKALASI,
            'status' => AiFaqDraf::STATUS_USULAN,
            'dibuat_periode' => now()->startOfMonth()->toDateString(),
        ]);

        // Alur halaman: isi jawaban dulu, lalu sahkan.
        $this->miner()->isiJawaban((int) $draf->getKey(), $pengelola, 'Biaya pendaftaran santri baru Rp500.000, sudah termasuk seragam.');
        $this->assertSame(AiFaqDraf::STATUS_DIJAWAB, $draf->refresh()->status);

        $draf = $this->miner()->sahkan((int) $draf->getKey(), $pengelola);

        // Draf tersahkan + tertaut ke sumber pengetahuan.
        $this->assertSame(AiFaqDraf::STATUS_DISAHKAN, $draf->status);
        $this->assertSame($pengelola->getKey(), $draf->disahkan_oleh);
        $this->assertNotNull($draf->disahkan_pada);
        $this->assertNotNull($draf->source_id);

        /** @var AiKnowledgeSource $source */
        $source = AiKnowledgeSource::query()->findOrFail($draf->source_id);
        $this->assertSame('faq', $source->kategori);
        $this->assertStringStartsWith('FAQ:', $source->judul);
        $this->assertSame(AiKnowledgeSource::STATUS_DIUNGGAH, $source->status);
        Queue::assertPushed(IngestDocumentJob::class);

        // Proses ingest (fake embed) → siap; chunk memuat T/J + variasi.
        app(IngestService::class)->prosesSumber((int) $source->getKey());
        $this->assertSame(AiKnowledgeSource::STATUS_SIAP, $source->refresh()->status);

        $konten = AiChunk::query()->where('source_id', $source->getKey())
            ->orderBy('urutan')->pluck('konten')->implode("\n");
        $this->assertStringContainsString('T: Berapa biaya pendaftaran santri baru?', $konten);
        $this->assertStringContainsString('Rp500.000', $konten);
        $this->assertStringContainsString('biaya pendaftaran berapa ya', $konten);

        // Sahkan ulang DITOLAK — tidak ada ingest ganda.
        try {
            $this->miner()->sahkan((int) $draf->getKey(), $pengelola);
            $this->fail('Sahkan ulang seharusnya ditolak.');
        } catch (\InvalidArgumentException) {
            $this->assertSame(1, AiKnowledgeSource::query()->count());
        }
    }

    // ------------------------------------------------------------------
    // (f) Abaikan + keputusan manusia dihormati
    // ------------------------------------------------------------------

    public function test_abaikan_dan_tidak_diusulkan_ulang(): void
    {
        $this->siapkanTenant('faqg');
        $pengelola = $this->buatPengelola();

        $percakapan = $this->buatPercakapan('628111000004');
        foreach (range(1, 3) as $i) {
            $this->buatPesanMasuk($percakapan, 'Apakah pesantren menerima pindahan semester ganjil?');
        }

        $this->miner()->tambang();
        /** @var AiFaqDraf $draf */
        $draf = AiFaqDraf::query()->firstOrFail();

        $draf = $this->miner()->abaikan((int) $draf->getKey(), $pengelola);
        $this->assertSame(AiFaqDraf::STATUS_DIABAIKAN, $draf->status);

        // Tambang ulang: keputusan dihormati — tidak ada usulan baru,
        // status tetap diabaikan, frekuensi tidak disentuh kembali.
        $stat = $this->miner()->tambang();
        $this->assertSame(0, $stat['usulan_baru']);
        $this->assertSame(1, AiFaqDraf::query()->count());
        $this->assertSame(AiFaqDraf::STATUS_DIABAIKAN, $draf->refresh()->status);
    }

    // ------------------------------------------------------------------
    // (g) RBAC halaman + badge
    // ------------------------------------------------------------------

    public function test_halaman_usulan_faq_tertutup_tanpa_permission(): void
    {
        $this->siapkanTenant('faqh');
        $this->seed(RolesAndPermissionsSeeder::class);
        Filament::setCurrentPanel(Filament::getPanel('tenant'));

        $tanpa = User::create([
            'name' => 'Tanpa Izin',
            'email' => 'tanpa-izin-halaman-faq@test.local',
            'password' => 'password',
        ]);

        $this->actingAs($tanpa);
        $this->assertFalse(UsulanFaq::canAccess());

        $dengan = User::create([
            'name' => 'Pengelola Pengetahuan',
            'email' => 'pengelola-halaman-faq@test.local',
            'password' => 'password',
        ]);
        $dengan->givePermissionTo('ai.pengetahuan.kelola');

        $this->actingAs($dengan);
        $this->assertTrue(UsulanFaq::canAccess());

        // Badge = jumlah usulan menunggu tinjauan.
        $this->assertNull(UsulanFaq::getNavigationBadge());

        AiFaqDraf::create([
            'pertanyaan_perwakilan' => 'Berapa biaya pendaftaran?',
            'pertanyaan_hash' => hash('sha256', 'biaya pendaftaran'),
            'variasi' => [],
            'frekuensi' => 3,
            'sumber' => AiFaqDraf::SUMBER_PERCAKAPAN,
            'status' => AiFaqDraf::STATUS_USULAN,
            'dibuat_periode' => now()->startOfMonth()->toDateString(),
        ]);

        $this->assertSame('1', UsulanFaq::getNavigationBadge());
    }

    // ------------------------------------------------------------------
    // (h) Isolasi tenant
    // ------------------------------------------------------------------

    public function test_usulan_faq_terisolasi_antar_tenant(): void
    {
        $tenantA = $this->createRuntimeTenant('faqia', 'Pesantren Isolasi A');
        $tenantB = $this->createRuntimeTenant('faqib', 'Pesantren Isolasi B');

        tenancy()->initialize($tenantA);
        $this->seedTenantRow($tenantA);
        $percakapan = $this->buatPercakapan('628111000005');
        foreach (range(1, 3) as $i) {
            $this->buatPesanMasuk($percakapan, 'Bagaimana cara melihat tagihan bulanan?');
        }
        $this->miner()->tambang();
        $this->assertSame(1, AiFaqDraf::query()->count());
        tenancy()->end();

        tenancy()->initialize($tenantB);
        $this->seedTenantRow($tenantB);
        $this->assertSame(0, AiFaqDraf::query()->count(), 'Usulan tenant A tak boleh terbaca dari B');
        $this->assertSame(0, $this->miner()->tambang()['usulan_baru']);
        tenancy()->end();

        tenancy()->initialize($tenantA);
        $this->assertSame(1, AiFaqDraf::query()->count());
    }

    // ------------------------------------------------------------------
    // (i) Privasi: usulan bebas PII walau sumber kebobolan nomor mentah
    // ------------------------------------------------------------------

    public function test_usulan_tetap_terredaksi_walau_pesan_memuat_nomor_hp(): void
    {
        $this->siapkanTenant('faqp');

        // Simulasi terburuk: nomor HP MENTAH lolos ke isi_terredaksi
        // (seharusnya tak terjadi — by-construction WaChatService meredaksi).
        // Sabuk pengaman FaqMiner wajib menangkapnya sebelum jadi draf.
        $percakapan = $this->buatPercakapan('628111000006');
        foreach (range(1, 3) as $i) {
            $this->buatPesanMasuk($percakapan, 'Tolong cek status tagihan ananda, nomor saya 08123456789');
        }

        $this->miner()->tambang();

        /** @var AiFaqDraf $draf */
        $draf = AiFaqDraf::query()->firstOrFail();

        $semuaTeks = $draf->pertanyaan_perwakilan.' '.implode(' ', (array) $draf->variasi);

        $this->assertStringNotContainsString('08123456789', $semuaTeks, 'Nomor HP mentah tidak boleh hidup di usulan');
        $this->assertStringContainsString('[PII:HP:', $draf->pertanyaan_perwakilan, 'Nomor wajib tergantikan token redaksi');
    }

    // ------------------------------------------------------------------
    // (j) Perintah terjadwal ai:faq-mining + notifikasi pengelola
    // ------------------------------------------------------------------

    public function test_perintah_faq_mining_menambang_dan_memberi_tahu_pengelola(): void
    {
        $tenant = $this->siapkanTenant('faqcm');
        $pengelola = $this->buatPengelola('pengelola-cmd-faq@test.local');

        $percakapan = $this->buatPercakapan('628111000007');
        foreach (range(1, 3) as $i) {
            $this->buatPesanMasuk($percakapan, 'Kapan gelombang dua PPDB dibuka?');
        }

        tenancy()->end();

        Artisan::call('ai:faq-mining', ['tenant' => $tenant->id]);

        tenancy()->initialize($tenant);

        $this->assertSame(1, AiFaqDraf::query()->count());

        // Notifikasi ke pemegang ai.pengetahuan.kelola (target user:ID).
        $notif = LogNotifikasi::query()->where('jenis', 'ai_faq_usulan')->get();
        $this->assertCount(1, $notif);
        $this->assertSame('user:'.$pengelola->getKey(), $notif->first()->target);
        $this->assertSame(1, $notif->first()->payload['usulan_baru']);

        tenancy()->end();

        // Jalan ulang tanpa data baru: tidak ada usulan baru → TIDAK ada
        // notifikasi tambahan (jangan menyepam pengelola tiap pekan).
        Artisan::call('ai:faq-mining', ['tenant' => $tenant->id]);

        tenancy()->initialize($tenant);
        $this->assertSame(1, AiFaqDraf::query()->count());
        $this->assertSame(1, LogNotifikasi::query()->where('jenis', 'ai_faq_usulan')->count());
    }
}
