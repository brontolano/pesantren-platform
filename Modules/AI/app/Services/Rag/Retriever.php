<?php

namespace Modules\AI\Services\Rag;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Modules\AI\Models\AiChunk;
use Modules\AI\Services\Gateway\AiGateway;

/**
 * T-A031 — Retriever hybrid ber-tenant (Blueprint §4.2 langkah 3, §5, §7.4).
 *
 * Dua jalur peringkat digabung Reciprocal Rank Fusion (RRF, k=60) di PHP:
 *  - VEKTOR: embed query via AiGateway::embed (SATU-SATUNYA pintu — pagar
 *    blacklist embed T-A020/K2 ikut aktif) → cosine distance `embedding <=> v`
 *    bila kolom pgvector ada; lingkungan TANPA pgvector (PG lokal dev/test)
 *    jatuh ke cosine PHP atas embedding_json dari kandidat FTS top-N
 *    (+ pengisi chunk terbaru bila kandidat kurang) — fusi tetap teruji lokal;
 *  - FTS: plainto_tsquery 'simple' atas kolom generated konten_fts + ts_rank.
 *
 * Kedua jalur HANYA melihat sumber aktif=true, status=siap (+ filter kategori
 * bila diminta) — isolasi tenant ditegakkan struktur DB-per-tenant: seluruh
 * query berjalan di koneksi tenant aktif.
 *
 * Aturan 2-koneksi (Blueprint §5): RetrieverSource::SHARED = korpus kitab di
 * central — STUB kosong sampai T-A032 (gerbang Q3 lisensi). Titik sambungnya
 * adalah cabang match di cari(); pemanggil tidak perlu berubah kelak.
 */
class Retriever
{
    /** Banyak kandidat per jalur sebelum fusi (Blueprint §7.4: top-3–5 final). */
    private const KANDIDAT = 50;

    public function __construct(private readonly AiGateway $gateway) {}

    /**
     * @param  array{kategori?: string|null, topK?: int, minSkor?: float|null}  $opsi
     */
    public function cari(
        string $query,
        array $opsi = [],
        RetrieverSource $sumber = RetrieverSource::TENANT,
    ): RetrievalResult {
        if ($sumber === RetrieverSource::SHARED) {
            // TODO(T-A032): korpus kitab shared di DB central — menunggu
            // gerbang Q3 (lisensi terbuka). Sampai itu: kosong, BUKAN error.
            return RetrievalResult::kosong();
        }

        $query = trim($query);

        if ($query === '' || ! tenancy()->initialized) {
            return RetrievalResult::kosong();
        }

        $kategori = isset($opsi['kategori']) && $opsi['kategori'] !== null
            ? (string) $opsi['kategori'] : null;
        $topK = max(1, (int) ($opsi['topK'] ?? config('ai.rag.top_k', 5)));
        $minSkor = isset($opsi['minSkor']) && $opsi['minSkor'] !== null
            ? (float) $opsi['minSkor'] : null;

        // Korpus kosong (tidak ada chunk dari sumber aktif+siap dalam filter)
        // → berhenti SEBELUM embed: jalur tanpa-rujukan tidak boleh memicu
        // HTTP apa pun ke provider.
        if (! $this->dasarQuery($kategori)->exists()) {
            return RetrievalResult::kosong();
        }

        // Jalur 1: FTS keyword (juga menyuplai kandidat fallback jalur vektor).
        $peringkatFts = $this->peringkatFts($query, $kategori);

        // Jalur 2: vektor semantik.
        $peringkatVektor = $this->peringkatVektor($query, $kategori, array_keys($peringkatFts));

        $skorFusi = $this->fusiRrf([$peringkatFts, $peringkatVektor]);

        if ($skorFusi === []) {
            return RetrievalResult::kosong();
        }

        arsort($skorFusi);

        $hasil = [];

        // Muat chunk + sumber sekali; jaga urutan skor fusi.
        $chunks = AiChunk::query()
            ->with('source')
            ->whereIn('id', array_keys($skorFusi))
            ->get()
            ->keyBy('id');

        foreach ($skorFusi as $chunkId => $skor) {
            if (count($hasil) >= $topK) {
                break;
            }

            if ($minSkor !== null && $skor < $minSkor) {
                continue;
            }

            $chunk = $chunks->get($chunkId);

            if ($chunk === null || $chunk->source === null) {
                continue;
            }

            $hasil[] = ['chunk' => $chunk, 'skor' => $skor, 'sumber' => $chunk->source];
        }

        return new RetrievalResult($hasil, $hasil !== []);
    }

    // ------------------------------------------------------------------
    // Jalur FTS
    // ------------------------------------------------------------------

    /**
     * Peringkat keyword: konten_fts @@ tsquery OR ('kata1 | kata2 | ...')
     * + ts_rank. OR (bukan plainto yang meng-AND seluruh kata): kueri
     * natural-language hampir tidak pernah memuat SEMUA katanya di satu
     * chunk — AND membuat jalur FTS kosong; OR + ts_rank tetap menaikkan
     * chunk yang memuat lebih banyak kata kueri.
     *
     * @return array<int, int> chunk_id => peringkat (0 = terbaik)
     */
    private function peringkatFts(string $query, ?string $kategori): array
    {
        if (! $this->punyaKolom('konten_fts')) {
            return []; // non-pgsql / kolom belum termigrasi — jalur FTS mati anggun
        }

        $tsquery = $this->tsqueryOr($query);

        if ($tsquery === null) {
            return [];
        }

        try {
            $rows = $this->dasarQuery($kategori)
                ->whereRaw("ai_chunk.konten_fts @@ to_tsquery('simple', ?)", [$tsquery])
                ->orderByRaw("ts_rank(ai_chunk.konten_fts, to_tsquery('simple', ?)) DESC", [$tsquery])
                ->orderBy('ai_chunk.id')
                ->limit(self::KANDIDAT)
                ->pluck('ai_chunk.id');
        } catch (\Throwable $e) {
            Log::warning('Retriever: jalur FTS gagal — lanjut jalur vektor saja.', [
                'message' => $e->getMessage(),
            ]);

            return [];
        }

        return array_flip($rows->map(static fn ($id): int => (int) $id)->all());
    }

    // ------------------------------------------------------------------
    // Jalur vektor
    // ------------------------------------------------------------------

    /**
     * Peringkat semantik. Vektor query via gateway (kosong = degradasi /
     * diblokir kebijakan → jalur vektor mati anggun, FTS tetap hidup).
     *
     * @param  list<int>  $kandidatFts
     * @return array<int, int> chunk_id => peringkat (0 = terbaik)
     */
    private function peringkatVektor(string $query, ?string $kategori, array $kandidatFts): array
    {
        $vektor = $this->gateway->embed($query)[0] ?? null;

        if ($vektor === null || $vektor === []) {
            return [];
        }

        if ($this->punyaKolom('embedding')) {
            return $this->peringkatPgvector($vektor, $kategori);
        }

        return $this->peringkatCosinePhp($vektor, $kategori, $kandidatFts);
    }

    /**
     * pgvector ANN: ORDER BY embedding <=> query (cosine distance).
     *
     * @param  list<float>  $vektor
     * @return array<int, int>
     */
    private function peringkatPgvector(array $vektor, ?string $kategori): array
    {
        $literal = '['.implode(',', array_map(static fn ($v): float => (float) $v, $vektor)).']';

        try {
            $rows = $this->dasarQuery($kategori)
                ->whereNotNull('ai_chunk.embedding')
                ->orderByRaw('ai_chunk.embedding <=> ?::vector', [$literal])
                ->limit(self::KANDIDAT)
                ->pluck('ai_chunk.id');
        } catch (\Throwable $e) {
            Log::warning('Retriever: jalur pgvector gagal — fusi memakai FTS saja.', [
                'message' => $e->getMessage(),
            ]);

            return [];
        }

        return array_flip($rows->map(static fn ($id): int => (int) $id)->all());
    }

    /**
     * FALLBACK TERDOKUMENTASI (lingkungan tanpa pgvector — PG lokal dev/test):
     * cosine dihitung di PHP atas embedding_json untuk kandidat = hasil FTS
     * top-N, ditambah chunk terbaru sumber aktif sebagai pengisi bila kandidat
     * kurang dari N — supaya fusi dua jalur tetap teruji di lokal. Produksi
     * (pgvector tersedia) tidak pernah lewat sini.
     *
     * @param  list<float>  $vektor
     * @param  list<int>  $kandidatFts
     * @return array<int, int>
     */
    private function peringkatCosinePhp(array $vektor, ?string $kategori, array $kandidatFts): array
    {
        $ids = $kandidatFts;

        if (count($ids) < self::KANDIDAT) {
            $tambahan = $this->dasarQuery($kategori)
                ->when($ids !== [], fn ($q) => $q->whereNotIn('ai_chunk.id', $ids))
                ->orderByDesc('ai_chunk.id')
                ->limit(self::KANDIDAT - count($ids))
                ->pluck('ai_chunk.id')
                ->map(static fn ($id): int => (int) $id)
                ->all();

            $ids = array_merge($ids, $tambahan);
        }

        if ($ids === []) {
            return [];
        }

        $skor = [];

        AiChunk::query()
            ->whereIn('id', $ids)
            ->whereNotNull('embedding_json')
            ->get(['id', 'embedding_json'])
            ->each(function (AiChunk $chunk) use ($vektor, &$skor): void {
                $cos = $this->cosine($vektor, (array) $chunk->embedding_json);

                if ($cos !== null) {
                    $skor[(int) $chunk->getKey()] = $cos;
                }
            });

        arsort($skor);

        $peringkat = [];

        foreach (array_keys($skor) as $i => $chunkId) {
            $peringkat[$chunkId] = $i;
        }

        return $peringkat;
    }

    /**
     * tsquery OR dari kata-kata kueri: token huruf/angka unik, di-quote
     * sebagai literal tsquery ('kata') supaya aman dari sintaks operator.
     * null = tidak ada token (kueri simbol semua).
     */
    private function tsqueryOr(string $query): ?string
    {
        $kata = preg_split('/[^\p{L}\p{N}]+/u', mb_strtolower($query), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $kata = array_slice(array_values(array_unique($kata)), 0, 24);

        if ($kata === []) {
            return null;
        }

        return implode(' | ', array_map(
            static fn (string $k): string => "'".str_replace("'", "''", $k)."'",
            $kata,
        ));
    }

    /** @param  list<float>  $a */
    private function cosine(array $a, array $b): ?float
    {
        if (count($a) !== count($b) || $a === []) {
            return null;
        }

        $dot = $na = $nb = 0.0;

        foreach ($a as $i => $va) {
            $vb = (float) $b[$i];
            $dot += $va * $vb;
            $na += $va * $va;
            $nb += $vb * $vb;
        }

        if ($na <= 0.0 || $nb <= 0.0) {
            return null;
        }

        return $dot / (sqrt($na) * sqrt($nb));
    }

    // ------------------------------------------------------------------
    // Fusi & pendukung
    // ------------------------------------------------------------------

    /**
     * Reciprocal Rank Fusion: skor(chunk) = Σ 1/(k + peringkat + 1) atas
     * semua daftar yang memuatnya (k=60, konstanta standar literatur RRF).
     *
     * @param  list<array<int, int>>  $daftarPeringkat
     * @return array<int, float>
     */
    private function fusiRrf(array $daftarPeringkat): array
    {
        $k = max(1, (int) config('ai.rag.rrf_k', 60));
        $skor = [];

        foreach ($daftarPeringkat as $peringkat) {
            foreach ($peringkat as $chunkId => $posisi) {
                $skor[$chunkId] = ($skor[$chunkId] ?? 0.0) + 1.0 / ($k + $posisi + 1);
            }
        }

        return $skor;
    }

    /** Join chunk × sumber AKTIF + siap (+ kategori) — pagar kedua jalur. */
    private function dasarQuery(?string $kategori)
    {
        return DB::table('ai_chunk')
            ->join('ai_knowledge_source', 'ai_knowledge_source.id', '=', 'ai_chunk.source_id')
            ->where('ai_knowledge_source.aktif', true)
            ->where('ai_knowledge_source.status', 'siap')
            ->when($kategori !== null, fn ($q) => $q->where('ai_knowledge_source.kategori', $kategori))
            ->select('ai_chunk.id');
    }

    private function punyaKolom(string $kolom): bool
    {
        try {
            return Schema::hasColumn('ai_chunk', $kolom);
        } catch (\Throwable) {
            return false;
        }
    }
}
