<?php

namespace Modules\AI\Services\Rag;

use App\Models\User;
use App\Services\StorageQuotaService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Modules\AI\Jobs\IngestDocumentJob;
use Modules\AI\Models\AiChunk;
use Modules\AI\Models\AiKnowledgeSource;
use Modules\AI\Services\Gateway\AiGateway;
use Modules\AI\Services\Privacy\ContentSanitizer;
use Smalot\PdfParser\Parser;
use ZipArchive;

/**
 * T-A030 — pipeline ingest dokumen tenant untuk RAG (Blueprint §4.1).
 *
 * Alur: daftarkanSumber (simpan file + dispatch job queue 'ai') →
 * prosesSumber (parsing → chunking ~500 token → SANITASI per chunk →
 * embedding batch via AiGateway::embed → simpan ai_chunk) → siap.
 *
 * Aturan Emas yang ditegakkan di sini:
 *  - embedding HANYA via AiGateway (pagar blacklist embed T-A020/K2 ikut aktif);
 *  - tiap chunk WAJIB melewati ContentSanitizer::sanitize — konten dokumen
 *    adalah DATA, bukan perintah (anti prompt-injection via dokumen);
 *  - hapusTuntas = right to erasure (ADR-6): source + chunk + vektor + file fisik.
 *
 * Parser yang tersedia TANPA dependency composer baru:
 *  - teks polos / markdown (txt, md, csv);
 *  - DOCX via ZipArchive (ekstrak word/document.xml);
 *  - PDF: TIDAK ada parser di vendor (cek 2026-06-10) → status gagal dengan
 *    pesan santun "belum didukung" (jangan diam-diam meng-index kosong).
 */
class IngestService
{
    /** ~500 token ≈ 1800 karakter (Blueprint §7.4 chunk ramping). */
    private const CHUNK_CHARS = 1800;

    private const OVERLAP_CHARS = 200;

    /** Batas batch embed per panggilan gateway (jaga payload & memori). */
    private const EMBED_BATCH = 16;

    public function __construct(
        private readonly AiGateway $gateway,
        private readonly ContentSanitizer $sanitizer,
        private readonly StorageQuotaService $kuota,
    ) {}

    /**
     * Daftarkan sumber baru: simpan file ke storage tenant (atau simpan teks
     * langsung sebagai file .txt), buat baris status `diunggah`, lalu dispatch
     * IngestDocumentJob ke queue 'ai'.
     *
     * @param  UploadedFile|string  $isi  file unggahan ATAU teks mentah
     *
     * @throws \InvalidArgumentException kategori tak dikenal / kuota penuh / isi kosong
     */
    public function daftarkanSumber(
        string $judul,
        string $kategori,
        UploadedFile|string $isi,
        ?User $user = null,
    ): AiKnowledgeSource {
        if (! in_array($kategori, AiKnowledgeSource::KATEGORI, true)) {
            throw new \InvalidArgumentException("Kategori sumber pengetahuan tidak dikenal: '{$kategori}'.");
        }

        $disk = StorageQuotaService::diskName();
        $dir = 'ai-pengetahuan';

        if ($isi instanceof UploadedFile) {
            $ukuran = (int) $isi->getSize();
            $this->jagaKuota($ukuran);

            $path = $isi->storeAs(
                $dir,
                Str::ulid().'-'.Str::slug(pathinfo($isi->getClientOriginalName(), PATHINFO_FILENAME))
                    .'.'.strtolower((string) $isi->getClientOriginalExtension()),
                ['disk' => $disk],
            );
            $mime = $isi->getClientMimeType() ?: null;
        } else {
            $teks = trim($isi);

            if ($teks === '') {
                throw new \InvalidArgumentException('Teks sumber pengetahuan tidak boleh kosong.');
            }

            $this->jagaKuota(strlen($teks));

            $path = $dir.'/'.Str::ulid().'-teks.txt';
            Storage::disk($disk)->put($path, $teks);
            $mime = 'text/plain';
        }

        $source = AiKnowledgeSource::create([
            'judul' => $judul,
            'kategori' => $kategori,
            'path_file' => $path,
            'mime' => $mime,
            'status' => AiKnowledgeSource::STATUS_DIUNGGAH,
            'versi_ingest' => 1,
            'diunggah_oleh' => $user?->getKey(),
        ]);

        IngestDocumentJob::dispatch(
            (int) $source->getKey(),
            (int) $source->versi_ingest,
            tenancy()->initialized ? (string) tenant()?->getTenantKey() : null,
        );

        return $source;
    }

    /**
     * Proses satu sumber: parsing → chunking → sanitasi → embedding → simpan.
     * Idempoten terhadap chunk: chunk lama sumber ini SELALU dihapus dulu
     * (retry job / re-ingest tidak menggandakan baris).
     *
     * @throws \RuntimeException kegagalan parsing/IO — pemanggil (job) yang
     *                           menandai status gagal + error_ringkas.
     */
    public function prosesSumber(int $sourceId): AiKnowledgeSource
    {
        /** @var AiKnowledgeSource $source */
        $source = AiKnowledgeSource::query()->findOrFail($sourceId);

        $source->update([
            'status' => AiKnowledgeSource::STATUS_MEMPROSES,
            'error_ringkas' => null,
        ]);

        $teks = $this->ekstrakTeks($source);
        $potongan = $this->chunk($teks);

        if ($potongan === []) {
            throw new \RuntimeException('Dokumen tidak menghasilkan konten yang dapat di-index (kosong setelah parsing).');
        }

        // SANITASI tiap chunk SEBELUM embed maupun simpan — vektor dan baris
        // DB hanya pernah melihat versi tersanitasi (data, bukan perintah).
        $bersih = array_map(
            fn (array $c): array => [...$c, 'konten' => $this->sanitizer->sanitize($c['konten'])],
            $potongan,
        );

        $vektor = $this->embedBatch(array_column($bersih, 'konten'));

        DB::transaction(function () use ($source, $bersih, $vektor): void {
            AiChunk::query()->where('source_id', $source->getKey())->delete();

            $punyaKolomVector = $this->punyaKolomVector();

            foreach ($bersih as $i => $c) {
                $chunk = AiChunk::create([
                    'source_id' => $source->getKey(),
                    'urutan' => $i,
                    'konten' => $c['konten'],
                    'metadata' => array_filter([
                        'judul' => $source->judul,
                        'bab' => $c['bab'] ?? null,
                        'halaman' => $c['halaman'] ?? null,
                    ], static fn ($v) => $v !== null),
                    'embedding_json' => $vektor[$i] ?? null,
                ]);

                if ($punyaKolomVector && isset($vektor[$i])) {
                    DB::table('ai_chunk')
                        ->where('id', $chunk->getKey())
                        ->update(['embedding' => '['.implode(',', $vektor[$i]).']']);
                }
            }

            $source->update([
                'status' => AiKnowledgeSource::STATUS_SIAP,
                'jumlah_chunk' => count($bersih),
                'error_ringkas' => null,
            ]);
        });

        return $source->refresh();
    }

    /**
     * Re-ingest: naikkan versi_ingest lalu proses ulang (chunk lama diganti
     * atomik di prosesSumber). Job baru di-dispatch dengan versi baru —
     * kunci unik job per (source, versi) sehingga re-ingest tak tertelan
     * dedup ShouldBeUnique versi sebelumnya.
     */
    public function reIngest(int $sourceId): AiKnowledgeSource
    {
        /** @var AiKnowledgeSource $source */
        $source = AiKnowledgeSource::query()->findOrFail($sourceId);

        $source->update([
            'versi_ingest' => $source->versi_ingest + 1,
            'status' => AiKnowledgeSource::STATUS_DIUNGGAH,
            'error_ringkas' => null,
        ]);

        IngestDocumentJob::dispatch(
            (int) $source->getKey(),
            (int) $source->versi_ingest,
            tenancy()->initialized ? (string) tenant()?->getTenantKey() : null,
        );

        return $source->refresh();
    }

    /**
     * Right to erasure (ADR-6): hard delete sumber + SEMUA chunk & vektornya
     * (FK cascade — termasuk kolom embedding & embedding_json) + file fisik.
     */
    public function hapusTuntas(int $sourceId): void
    {
        /** @var AiKnowledgeSource|null $source */
        $source = AiKnowledgeSource::query()->find($sourceId);

        if ($source === null) {
            return; // sudah tiada — erasure idempoten
        }

        $path = $source->path_file;

        DB::transaction(static function () use ($source): void {
            AiChunk::query()->where('source_id', $source->getKey())->delete();
            $source->delete();
        });

        if ($path !== null) {
            try {
                Storage::disk(StorageQuotaService::diskName())->delete($path);
            } catch (\Throwable $e) {
                // File fisik gagal terhapus TIDAK membatalkan erasure data —
                // tapi wajib ter-log keras untuk tindak lanjut operator.
                Log::error('IngestService: gagal menghapus file fisik sumber pengetahuan.', [
                    'path' => $path,
                    'message' => $e->getMessage(),
                ]);
            }
        }
    }

    // ------------------------------------------------------------------
    // Parsing
    // ------------------------------------------------------------------

    /** @throws \RuntimeException format tak didukung / file hilang */
    private function ekstrakTeks(AiKnowledgeSource $source): string
    {
        $disk = Storage::disk(StorageQuotaService::diskName());

        if ($source->path_file === null || ! $disk->exists($source->path_file)) {
            throw new \RuntimeException('Berkas sumber tidak ditemukan di penyimpanan tenant.');
        }

        $ekstensi = strtolower(pathinfo($source->path_file, PATHINFO_EXTENSION));

        return match (true) {
            in_array($ekstensi, ['txt', 'md', 'markdown', 'csv'], true) => (string) $disk->get($source->path_file),
            $ekstensi === 'docx' => $this->ekstrakDocx($disk->path($source->path_file)),
            $ekstensi === 'pdf' => $this->ekstrakPdf($disk->path($source->path_file)),
            default => throw new \RuntimeException(
                "Mohon maaf, format berkas .{$ekstensi} belum didukung. Format yang didukung: .txt, .md, .docx, .pdf.",
            ),
        };
    }

    /** DOCX = zip berisi word/document.xml — ekstrak teks via ZipArchive (tanpa dependency baru). */
    private function ekstrakDocx(string $absolutePath): string
    {
        $zip = new ZipArchive;

        if ($zip->open($absolutePath) !== true) {
            throw new \RuntimeException('Berkas .docx tidak dapat dibuka (arsip rusak).');
        }

        try {
            $xml = $zip->getFromName('word/document.xml');
        } finally {
            $zip->close();
        }

        if ($xml === false || $xml === '') {
            throw new \RuntimeException('Berkas .docx tidak memuat dokumen yang dapat dibaca.');
        }

        // Akhir paragraf & baris → newline supaya batas kalimat/halaman heuristik tetap hidup.
        $xml = (string) preg_replace('/<w:(p|br|tab)\b[^>]*\/?>/', "\n", $xml);
        $teks = html_entity_decode(strip_tags($xml), ENT_QUOTES | ENT_XML1, 'UTF-8');

        return trim((string) preg_replace("/\n{3,}/", "\n\n", $teks));
    }

    /**
     * PDF = ekstrak teks via smalot/pdfparser (pustaka pure-PHP, tanpa
     * ekstensi sistem). PDF hasil pindai/gambar (tanpa lapisan teks) atau
     * terenkripsi → ditolak dengan pesan jelas (bukan chunk kosong).
     */
    private function ekstrakPdf(string $absolutePath): string
    {
        try {
            $teks = (new Parser)->parseFile($absolutePath)->getText();
        } catch (\Throwable $e) {
            throw new \RuntimeException(
                'Berkas PDF tidak dapat dibaca (mungkin terenkripsi/berpassword atau rusak). '
                .'Silakan unggah versi teks (.txt/.md) atau Word (.docx) dari dokumen ini.',
            );
        }

        $teks = trim((string) preg_replace("/\n{3,}/", "\n\n", str_replace("\r\n", "\n", $teks)));

        if ($teks === '') {
            throw new \RuntimeException(
                'PDF ini tidak memuat lapisan teks yang dapat dibaca (kemungkinan hasil pindai/foto). '
                .'Silakan unggah versi teks (.txt/.md/.docx) atau ketik ulang isinya di kotak teks.',
            );
        }

        return $teks;
    }

    // ------------------------------------------------------------------
    // Chunking (~500 token ≈ 1800 char, overlap 200, potong di batas kalimat)
    // ------------------------------------------------------------------

    /**
     * @return list<array{konten: string, bab: string|null, halaman: int|null}>
     */
    private function chunk(string $teks): array
    {
        $teks = trim(str_replace("\r\n", "\n", $teks));

        if ($teks === '') {
            return [];
        }

        $hasil = [];
        $panjang = mb_strlen($teks);
        $mulai = 0;

        while ($mulai < $panjang) {
            $ambil = mb_substr($teks, $mulai, self::CHUNK_CHARS);

            // Potong di batas kalimat terakhir (./!/?/newline) bila bukan potongan terakhir.
            if ($mulai + self::CHUNK_CHARS < $panjang) {
                if (preg_match('/^.*[.!?؟\n]/su', $ambil, $m)) {
                    $kandidat = $m[0];
                    // Hindari chunk mengecil ekstrem bila kalimat pertama sangat panjang.
                    if (mb_strlen($kandidat) >= (int) (self::CHUNK_CHARS / 3)) {
                        $ambil = $kandidat;
                    }
                }
            }

            $konten = trim($ambil);

            if ($konten !== '') {
                $hasil[] = [
                    'konten' => $konten,
                    'bab' => $this->deteksiBab($konten),
                    'halaman' => $this->deteksiHalaman($konten),
                ];
            }

            // Potongan ini sudah mencapai akhir teks → selesai. Tanpa guard
            // ini, langkah (panjang - overlap) merayap di ekor teks dan
            // menghasilkan banyak chunk duplikat untuk teks pendek.
            if ($mulai + mb_strlen($ambil) >= $panjang) {
                break;
            }

            $langkah = max(1, mb_strlen($ambil) - self::OVERLAP_CHARS);
            $mulai += $langkah;
        }

        return $hasil;
    }

    /** Heuristik bab: heading markdown (#...) atau baris "BAB X ..." pertama dalam chunk. */
    private function deteksiBab(string $konten): ?string
    {
        if (preg_match('/^#{1,3}\s+(.{3,120})$/mu', $konten, $m)) {
            return trim($m[1]);
        }

        if (preg_match('/^\s*(BAB\s+[IVXLC\d]+\b.{0,100})$/miu', $konten, $m)) {
            return trim($m[1]);
        }

        return null;
    }

    /** Heuristik halaman: penanda "Halaman N" / "hal. N" pertama dalam chunk. */
    private function deteksiHalaman(string $konten): ?int
    {
        if (preg_match('/\b(?:halaman|hal\.?)\s+(\d{1,4})\b/iu', $konten, $m)) {
            return (int) $m[1];
        }

        return null;
    }

    // ------------------------------------------------------------------
    // Embedding
    // ------------------------------------------------------------------

    /**
     * Embedding batch via AiGateway::embed (SATU-SATUNYA pintu — pagar
     * blacklist embed ikut aktif). Gateway kembali kosong = degradasi
     * provider ATAU diblokir kebijakan → ingest GAGAL eksplisit (jangan
     * menyimpan chunk tanpa vektor diam-diam).
     *
     * @param  list<string>  $kontens
     * @return list<list<float>>
     *
     * @throws \RuntimeException embedding tidak tersedia / jumlah tak sinkron
     */
    private function embedBatch(array $kontens): array
    {
        $semua = [];

        foreach (array_chunk($kontens, self::EMBED_BATCH) as $batch) {
            $vektor = $this->gateway->embed($batch);

            if (count($vektor) !== count($batch)) {
                throw new \RuntimeException(
                    'Layanan embedding tidak tersedia atau konten ditolak kebijakan — ingest dihentikan. Silakan coba lagi nanti.',
                );
            }

            array_push($semua, ...$vektor);
        }

        return $semua;
    }

    private function punyaKolomVector(): bool
    {
        try {
            return Schema::hasColumn('ai_chunk', 'embedding');
        } catch (\Throwable) {
            return false;
        }
    }

    /** Gerbang kuota penyimpanan tenant (pola TenantStorage platform). */
    private function jagaKuota(int $sizeBytes): void
    {
        if (! tenancy()->initialized) {
            return;
        }

        $tenantId = (string) tenant()->getTenantKey();

        if (! $this->kuota->canUpload($tenantId, $sizeBytes)) {
            throw new \InvalidArgumentException(
                'Mohon maaf, kuota penyimpanan pesantren Anda tidak mencukupi untuk berkas ini. Silakan tinjau pemakaian penyimpanan atau hubungi admin platform.',
            );
        }
    }
}
