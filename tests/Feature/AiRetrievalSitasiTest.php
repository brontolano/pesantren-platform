<?php

namespace Tests\Feature;

use App\Services\StorageQuotaService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Modules\AI\Models\AiCitation;
use Modules\AI\Models\AiEscalation;
use Modules\AI\Models\AiKnowledgeSource;
use Modules\AI\Services\Gateway\AiGenerateRequest;
use Modules\AI\Services\Rag\IngestService;
use Modules\AI\Services\Rag\RagAnswerer;
use Modules\AI\Services\Rag\Retriever;
use Modules\AI\Services\Rag\RetrieverSource;
use Modules\AI\Services\Settings\AiSettingProvisioner;
use Tests\Concerns\InteractsWithPpdbTestDatabase;
use Tests\TestCase;

/**
 * T-A031 — retrieval hybrid ber-tenant + sitasi deterministik:
 *  (a) cari(): dokumen target masuk top-3 (presisi, embedding fake
 *      deterministik bag-of-words per teks);
 *  (b) FTS menemukan keyword persis walau vektor fake buta (bukti fusi);
 *  (c) ISOLASI TENANT: korpus tenant A tidak pernah bocor ke tenant B;
 *  (d) sumber nonaktif tersembunyi + filter kategori; sumber shared = stub;
 *  (e) jawab() tanpa korpus → template tanpa-rujukan TANPA HTTP + eskalasi
 *      idempoten; dengan korpus → generate terpanggil + ai_citation + sitasi
 *      readable + konteks terbungkus delimiter DATA;
 *  (f) sitasi DETERMINISTIK: prompt ke provider TIDAK memuat instruksi sitasi.
 *
 * Catatan lingkungan: PG test lokal tanpa pgvector — jalur vektor memakai
 * fallback cosine PHP atas embedding_json (jalur yang memang dirancang).
 */
class AiRetrievalSitasiTest extends TestCase
{
    use InteractsWithPpdbTestDatabase;

    private const DIM = 1024;

    protected function setUp(): void
    {
        parent::setUp();

        ini_set('memory_limit', '512M');

        $this->ensurePpdbTestingSchemaReady(['ai_knowledge_source', 'ai_chunk', 'ai_citation']);
        $this->truncatePpdbTables(['domains', 'tenants']);

        \Illuminate\Support\Facades\DB::table('module_registry')->delete();
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

    private function siapkanTenant(string $prefix): \App\Models\Tenant
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

    /**
     * Vektor deterministik per teks: bag-of-words di-hash ke 1024 dimensi,
     * dinormalisasi — teks yang berbagi kata punya cosine lebih tinggi
     * (semantik kasar tapi deterministik untuk uji presisi).
     *
     * @return list<float>
     */
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

    /**
     * Fake HTTP Ollama: embed deterministik per teks + generate sukses.
     * $vektorButa=true → semua embedding konstan (cosine seragam, jalur
     * vektor tidak diskriminatif) untuk membuktikan kontribusi FTS.
     */
    private function fakeOllama(bool $vektorButa = false): void
    {
        Http::fake([
            '*/api/embed' => function ($request) use ($vektorButa) {
                $inputs = (array) ($request['input'] ?? []);

                return Http::response([
                    'embeddings' => array_map(
                        static fn ($t): array => $vektorButa
                            ? array_fill(0, self::DIM, 0.03125)
                            : self::vektorTeks((string) $t),
                        $inputs,
                    ),
                ]);
            },
            '*/api/generate' => Http::response([
                // Jawaban BERAKAR KONTEKS korpus standar — sejak T-A022,
                // CitationEnforcer memverifikasi overlap jawaban × konteks.
                'response' => 'Pendaftaran santri baru dibuka bulan Juni melalui formulir pendaftaran daring.',
                'prompt_eval_count' => 25,
                'eval_count' => 12,
            ]),
        ]);
    }

    private function ingestTeks(string $judul, string $kategori, string $teks): AiKnowledgeSource
    {
        $service = app(IngestService::class);
        $source = $service->daftarkanSumber($judul, $kategori, $teks);

        return $service->prosesSumber((int) $source->getKey());
    }

    /** Lima dokumen kecil bertopik berbeda (TANPA konten keagamaan). */
    private function ingestKorpusStandar(): array
    {
        return [
            'ppdb' => $this->ingestTeks(
                'Panduan PPDB',
                'ppdb',
                'Pendaftaran santri baru dibuka bulan Juni. Calon santri mengisi formulir pendaftaran daring lalu mengunggah akta kelahiran dan kartu keluarga. Lihat halaman 7 untuk rincian biaya pendaftaran santri baru.',
            ),
            'sop_tamu' => $this->ingestTeks(
                'SOP Kunjungan Tamu',
                'sop',
                'Tamu wajib lapor ke pos keamanan dan mengisi buku kunjungan. Jam kunjungan wali santri adalah hari Ahad pukul sembilan pagi sampai tiga sore.',
            ),
            'kurikulum' => $this->ingestTeks(
                'Struktur Kurikulum',
                'kurikulum',
                'Struktur kurikulum memuat pelajaran bahasa Arab, matematika, sains, dan keterampilan komputer. Setiap semester diakhiri ujian evaluasi belajar.',
            ),
            'faq_seragam' => $this->ingestTeks(
                'FAQ Seragam',
                'faq',
                'Seragam santri terdiri dari baju koko putih dan sarung untuk putra, serta gamis untuk putri. Seragam olahraga dibagikan saat orientasi.',
            ),
            'sop_kesehatan' => $this->ingestTeks(
                'SOP Layanan Kesehatan',
                'sop',
                'Santri yang sakit melapor ke musyrif kamar lalu dibawa ke poskestren. Obat hanya diberikan oleh petugas kesehatan yang bertugas.',
            ),
        ];
    }

    private function retriever(): Retriever
    {
        return app(Retriever::class);
    }

    private function request(string $prompt, ?string $key = null): AiGenerateRequest
    {
        return new AiGenerateRequest(
            prompt: $prompt,
            agent: 'uji',
            tier: 'ekonomis',
            channel: 'web',
            idempotencyKey: $key,
        );
    }

    // ------------------------------------------------------------------
    // (a) Presisi top-K
    // ------------------------------------------------------------------

    public function test_cari_menempatkan_dokumen_relevan_di_top_3(): void
    {
        $this->siapkanTenant('ragr1');
        $this->fakeOllama();
        $sumber = $this->ingestKorpusStandar();

        $hasil = $this->retriever()->cari('Bagaimana cara pendaftaran santri baru dan formulir PPDB?');

        $this->assertTrue($hasil->adaSumber);
        $this->assertLessThanOrEqual(5, count($hasil->chunks), 'top-K default = 5 (Blueprint §7.4)');

        $top3SourceIds = array_map(
            static fn (array $c): int => (int) $c['sumber']->getKey(),
            array_slice($hasil->chunks, 0, 3),
        );

        $this->assertContains(
            (int) $sumber['ppdb']->getKey(),
            $top3SourceIds,
            'Dokumen PPDB harus berada di top-3 untuk kueri pendaftaran',
        );

        // Skor terurut menurun.
        $skor = array_map(static fn (array $c): float => $c['skor'], $hasil->chunks);
        $terurut = $skor;
        rsort($terurut);
        $this->assertSame($terurut, $skor);
    }

    // ------------------------------------------------------------------
    // (b) FTS menemukan keyword persis walau vektor buta (bukti fusi)
    // ------------------------------------------------------------------

    public function test_fts_menemukan_keyword_persis_walau_vektor_tidak_diskriminatif(): void
    {
        $this->siapkanTenant('ragr2');
        $this->fakeOllama(vektorButa: true); // semua embedding identik → hanya FTS yang membedakan
        $this->ingestKorpusStandar();

        $target = $this->ingestTeks(
            'Tata Tertib Laboratorium',
            'sop',
            'Penggunaan laboratorium komputer mengikuti kode inventaris LABKOM-X91 yang tercatat di buku aset.',
        );

        $hasil = $this->retriever()->cari('LABKOM-X91');

        $this->assertTrue($hasil->adaSumber);
        $this->assertSame(
            (int) $target->getKey(),
            (int) $hasil->chunks[0]['sumber']->getKey(),
            'Keyword persis harus menang lewat jalur FTS meski vektor seragam',
        );
    }

    // ------------------------------------------------------------------
    // (c) Isolasi tenant — test keamanan #1
    // ------------------------------------------------------------------

    public function test_isolasi_tenant_korpus_tenant_lain_tidak_bocor(): void
    {
        $this->siapkanTenant('ragra');
        $this->fakeOllama();
        $this->ingestTeks(
            'Dokumen Rahasia Tenant A',
            'lainnya',
            'Kode inventaris khusus RAHASIA-TENANT-A-77 hanya milik pesantren A dan tidak boleh terlihat tenant lain.',
        );

        $this->assertTrue($this->retriever()->cari('RAHASIA-TENANT-A-77')->adaSumber);

        tenancy()->end();
        $this->siapkanTenant('ragrb');

        $hasil = $this->retriever()->cari('RAHASIA-TENANT-A-77');

        $this->assertFalse($hasil->adaSumber);
        $this->assertSame([], $hasil->chunks, 'Tenant B TIDAK boleh melihat chunk tenant A');
    }

    // ------------------------------------------------------------------
    // (d) Sumber nonaktif, filter kategori, stub shared
    // ------------------------------------------------------------------

    public function test_sumber_nonaktif_tersembunyi_dan_filter_kategori_berlaku(): void
    {
        $this->siapkanTenant('ragr3');
        $this->fakeOllama();
        $sumber = $this->ingestKorpusStandar();

        // Nonaktifkan dokumen PPDB → kueri pendaftaran tak boleh memuatnya.
        $sumber['ppdb']->update(['aktif' => false]);

        $hasil = $this->retriever()->cari('pendaftaran santri baru formulir PPDB');
        $sourceIds = array_map(static fn (array $c): int => (int) $c['sumber']->getKey(), $hasil->chunks);

        $this->assertNotContains((int) $sumber['ppdb']->getKey(), $sourceIds);

        // Filter kategori: hanya sumber kategori 'sop' yang boleh muncul.
        $hasilSop = $this->retriever()->cari('santri jam kunjungan kesehatan', ['kategori' => 'sop']);

        $this->assertTrue($hasilSop->adaSumber);

        foreach ($hasilSop->chunks as $c) {
            $this->assertSame('sop', $c['sumber']->kategori);
        }

        // Namespace shared = stub kosong sampai T-A032.
        $shared = $this->retriever()->cari('pendaftaran', [], RetrieverSource::SHARED);
        $this->assertFalse($shared->adaSumber);
        $this->assertSame([], $shared->chunks);
    }

    // ------------------------------------------------------------------
    // (e1) jawab() tanpa korpus → template + eskalasi, TANPA HTTP
    // ------------------------------------------------------------------

    public function test_jawab_tanpa_korpus_template_tanpa_rujukan_tanpa_http_dan_eskalasi_idempoten(): void
    {
        $this->siapkanTenant('ragr4');
        $this->fakeOllama();

        $key = 'uji-rag-kosong-1';
        $hasil = app(RagAnswerer::class)->jawab($this->request('Apa jadwal kunjungan tamu?', $key));

        $this->assertSame((string) config('ai.rag.pesan.tanpa_rujukan'), $hasil->text);
        $this->assertSame('null', $hasil->provider);
        $this->assertSame([], $hasil->sitasi);

        // TANPA LLM: nol HTTP — baik generate maupun embed (korpus kosong
        // terdeteksi sebelum embed).
        Http::assertNothingSent();

        // Eskalasi Tanya Ustadz tercatat, idempoten per idempotency_key.
        app(RagAnswerer::class)->jawab($this->request('Apa jadwal kunjungan tamu?', $key));

        $this->assertSame(
            1,
            AiEscalation::query()->where('idempotency_key', $key)->count(),
        );
        $this->assertSame(
            'tanpa_rujukan',
            AiEscalation::query()->where('idempotency_key', $key)->value('kategori'),
        );
    }

    // ------------------------------------------------------------------
    // (e2+f) jawab() dengan korpus → generate + sitasi deterministik
    // ------------------------------------------------------------------

    public function test_jawab_dengan_korpus_generate_terpanggil_sitasi_tersimpan_dan_deterministik(): void
    {
        $this->siapkanTenant('ragr5');
        $this->fakeOllama();
        $this->ingestKorpusStandar();

        $key = 'uji-rag-jawab-1';
        $hasil = app(RagAnswerer::class)->jawab(
            $this->request('Bagaimana cara pendaftaran santri baru PPDB?', $key),
        );

        // Generate benar-benar terpanggil + jawaban model kembali.
        Http::assertSent(static fn ($req): bool => str_contains($req->url(), '/api/generate'));
        // Kategori default 'umum' (agen 'uji' tak terpetakan) → tanpa
        // disclaimer/sumber tambahan: teks model lolos enforcer apa adanya.
        $this->assertSame('Pendaftaran santri baru dibuka bulan Juni melalui formulir pendaftaran daring.', $hasil->text);
        $this->assertNotNull($hasil->verdictSitasi);
        $this->assertTrue($hasil->verdictSitasi->lolos);
        $this->assertFalse($hasil->degraded);

        // Konteks ke provider terbungkus delimiter blok DATA (gateway T-A005)
        // dan memuat header sumber; prompt TIDAK memuat instruksi sitasi —
        // sitasi murni urusan pipeline (deterministik).
        Http::assertSent(static function ($req): bool {
            if (! str_contains($req->url(), '/api/generate')) {
                return false;
            }

            $prompt = (string) ($req['prompt'] ?? '');

            return str_contains($prompt, '<<<DATA:konteks>>>')
                && str_contains($prompt, '<<<AKHIR-DATA>>>')
                && str_contains($prompt, '[Sumber 1: ')
                && ! str_contains(mb_strtolower($prompt), 'sitasi')
                && ! str_contains(mb_strtolower($prompt), 'citation')
                && ! str_contains(mb_strtolower($prompt), 'kutip sumber');
        });

        // result.sitasi terisi readable "Judul — ...".
        $this->assertNotSame([], $hasil->sitasi);
        $this->assertArrayHasKey('label', $hasil->sitasi[0]);
        $this->assertStringContainsString($hasil->sitasi[0]['judul'], $hasil->sitasi[0]['label']);

        // ai_citation tersimpan + tertaut baris audit via idempotency_key.
        $citations = AiCitation::query()->orderBy('urutan')->get();

        $this->assertSame(count($hasil->sitasi), $citations->count());
        $this->assertSame(range(1, $citations->count()), $citations->pluck('urutan')->all());
        $this->assertNotNull($citations->first()->audit_log_id);
        $this->assertSame(
            $key,
            \Modules\AI\Models\AiAuditLog::query()
                ->whereKey($citations->first()->audit_log_id)
                ->value('idempotency_key'),
        );
    }
}
