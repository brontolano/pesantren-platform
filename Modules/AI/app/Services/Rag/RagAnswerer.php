<?php

namespace Modules\AI\Services\Rag;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Modules\AI\Models\AiAuditLog;
use Modules\AI\Models\AiChunk;
use Modules\AI\Models\AiCitation;
use Modules\AI\Models\AiEscalation;
use Modules\AI\Models\AiKnowledgeSource;
use Modules\AI\Services\Gateway\AiGateway;
use Modules\AI\Services\Gateway\AiGenerateRequest;
use Modules\AI\Services\Gateway\AiGenerateResult;
use Modules\AI\Services\Privacy\PiiRedactor;

/**
 * T-A031 — jembatan RAG: retrieval → generate → sitasi DETERMINISTIK
 * (Blueprint §4.2 langkah 3+6). Dipakai fitur T-A040+ dan chatbot.
 *
 * Kontrak inti:
 *  - retrieval kosong → respons template "tidak ada rujukan" santun +
 *    tawaran Tanya Ustadz + baris ai_escalation (idempoten per
 *    idempotency_key, pola PolicyEngine [M2]) — TANPA menyentuh LLM;
 *  - ada sumber → context = gabungan chunk per sumber (sudah tersanitasi
 *    saat ingest) → AiGateway::generate (gateway-lah yang mensanitasi ulang
 *    + membungkus context sebagai blok DATA berdelimiter, T-A005);
 *  - sitasi dilampirkan PIPELINE dari chunk yang dipakai — model TIDAK
 *    PERNAH diminta menulis sitasi (tidak ada instruksi sitasi di prompt);
 *    disimpan ke ai_citation (tautan audit via idempotency_key) + field
 *    AiGenerateResult::sitasi readable "Judul — bab/halaman".
 *
 * Catatan delimiter: pembungkusan blok DATA SENGAJA diserahkan ke gateway
 * (wrapAsData 'konteks') — bila kelas ini ikut membungkus per-sumber,
 * sanitasi gateway (anti delimiter-escape) justru men-defang delimiter
 * dalamnya. Pemisah antar sumber di sini = header teks biasa.
 */
class RagAnswerer
{
    public function __construct(
        private readonly Retriever $retriever,
        private readonly AiGateway $gateway,
        private readonly PiiRedactor $redactor,
    ) {}

    /**
     * @param  array{kategori?: string|null, topK?: int, minSkor?: float|null}  $opsi
     */
    public function jawab(AiGenerateRequest $request, array $opsi = []): AiGenerateResult
    {
        $retrieval = $this->retriever->cari($request->prompt, $opsi);

        if (! $retrieval->adaSumber) {
            return $this->jawabTanpaRujukan($request);
        }

        // Kunci idempotensi pasti — penaut deterministik sitasi → baris audit
        // gateway (AuditRecorder tidak mengembalikan id ke pemanggil gateway).
        $key = $request->idempotencyKey ?? 'rag-'.Str::ulid();
        $request = $request->idempotencyKey === null
            ? $request->withIdempotencyKey($key)
            : $request;

        $hasil = $this->gateway->generate(
            $request->withContext($this->susunKonteks($retrieval)),
        );

        // Jawaban degradasi/tolakan kebijakan TIDAK memakai chunk apa pun —
        // melampirkan sitasi di situ menyesatkan pembaca & enforcer T-A022.
        if ($hasil->degraded || $hasil->ditolakPolicy) {
            return $hasil;
        }

        $sitasi = $this->lampirkanSitasi($retrieval, $key);

        return new AiGenerateResult(
            text: $hasil->text,
            provider: $hasil->provider,
            model: $hasil->model,
            degraded: $hasil->degraded,
            promptTokens: $hasil->promptTokens,
            completionTokens: $hasil->completionTokens,
            latencyMs: $hasil->latencyMs,
            raw: $hasil->raw,
            ditolakPolicy: $hasil->ditolakPolicy,
            sitasi: $sitasi,
        );
    }

    // ------------------------------------------------------------------
    // Jalur tanpa rujukan (TANPA LLM)
    // ------------------------------------------------------------------

    private function jawabTanpaRujukan(AiGenerateRequest $request): AiGenerateResult
    {
        $this->tulisEskalasi($request);

        return new AiGenerateResult(
            text: (string) config(
                'ai.rag.pesan.tanpa_rujukan',
                'Mohon maaf, kami belum menemukan rujukan dokumen pesantren untuk pertanyaan ini, '
                .'sehingga asisten tidak dapat menjawabnya. Pertanyaan Anda telah kami teruskan '
                .'ke antrean Tanya Ustadz agar dijawab langsung oleh pihak pesantren.',
            ),
            provider: 'null',
            model: 'null',
            degraded: false, // bukan degradasi provider — keputusan pipeline RAG
        );
    }

    /**
     * Eskalasi Tanya Ustadz idempoten (pola PolicyEngine [M2]): firstOrCreate
     * per idempotency_key; pertanyaan disimpan RINGKAS + TERREDAKSI PAKSA.
     * Best-effort — kegagalan tulis tidak menggagalkan respons.
     */
    private function tulisEskalasi(AiGenerateRequest $request): void
    {
        try {
            $maks = (int) config('ai.guardrails.eskalasi_ringkas_max', 300);
            $terredaksi = $this->redactor->redactPaksa($request->prompt)->text;

            $atribut = [
                'conversation_ref' => $request->idempotencyKey,
                'kanal' => $request->channel,
                'pertanyaan_ringkas' => mb_substr($terredaksi, 0, $maks),
                'kategori' => 'tanpa_rujukan',
                'status' => AiEscalation::STATUS_BARU,
            ];

            if ($request->idempotencyKey !== null) {
                AiEscalation::query()->firstOrCreate(
                    ['idempotency_key' => $request->idempotencyKey],
                    $atribut,
                );
            } else {
                AiEscalation::query()->create($atribut);
            }
        } catch (\Throwable $e) {
            Log::error('RagAnswerer: gagal menulis eskalasi tanpa-rujukan — respons tetap dilanjutkan.', [
                'exception' => $e::class,
                'message' => $e->getMessage(),
            ]);
        }
    }

    // ------------------------------------------------------------------
    // Konteks & sitasi
    // ------------------------------------------------------------------

    /**
     * Gabungan chunk terurut skor, dikelompokkan per sumber dengan header
     * teks readable. Konten chunk SUDAH tersanitasi saat ingest; gateway
     * mensanitasi ulang + membungkus seluruhnya sebagai blok DATA (T-A005).
     * TIDAK ada instruksi sitasi untuk model — sitasi urusan pipeline.
     */
    private function susunKonteks(RetrievalResult $retrieval): string
    {
        $bagian = [];

        foreach ($retrieval->chunks as $i => $item) {
            /** @var AiChunk $chunk */
            $chunk = $item['chunk'];
            /** @var AiKnowledgeSource $sumber */
            $sumber = $item['sumber'];

            $bagian[] = '[Sumber '.($i + 1).': '.$this->labelSitasi(
                (string) $sumber->judul,
                $chunk->metadata['bab'] ?? null,
                isset($chunk->metadata['halaman']) ? (int) $chunk->metadata['halaman'] : null,
            )."]\n".$chunk->konten;
        }

        return implode("\n\n", $bagian);
    }

    /**
     * Simpan ai_citation dari chunk TERPAKAI + kembalikan daftar readable.
     * Tautan audit: baris ai_audit_log gateway dicari via idempotency_key
     * (unik). Idempoten per audit_log_id — retry/dedup tidak menggandakan.
     *
     * @return list<array{judul: string, bab: string|null, halaman: int|null, label: string}>
     */
    private function lampirkanSitasi(RetrievalResult $retrieval, string $key): array
    {
        $auditId = AiAuditLog::query()->where('idempotency_key', $key)->value('id');

        $sitasi = [];

        try {
            $sudahAda = $auditId !== null
                && AiCitation::query()->where('audit_log_id', $auditId)->exists();

            foreach ($retrieval->chunks as $i => $item) {
                /** @var AiChunk $chunk */
                $chunk = $item['chunk'];
                /** @var AiKnowledgeSource $sumber */
                $sumber = $item['sumber'];

                $bab = isset($chunk->metadata['bab']) ? (string) $chunk->metadata['bab'] : null;
                $halaman = isset($chunk->metadata['halaman']) ? (int) $chunk->metadata['halaman'] : null;

                if (! $sudahAda) {
                    AiCitation::query()->create([
                        'audit_log_id' => $auditId !== null ? (string) $auditId : null, // ULID
                        'source_id' => (int) $sumber->getKey(),
                        'chunk_id' => (int) $chunk->getKey(),
                        'urutan' => $i + 1,
                        'judul' => (string) $sumber->judul,
                        'bab' => $bab,
                        'halaman' => $halaman,
                        'skor' => (float) $item['skor'],
                        'created_at' => now(),
                    ]);
                }

                $sitasi[] = [
                    'judul' => (string) $sumber->judul,
                    'bab' => $bab,
                    'halaman' => $halaman,
                    'label' => $this->labelSitasi((string) $sumber->judul, $bab, $halaman),
                ];
            }
        } catch (\Throwable $e) {
            // Best-effort: kegagalan persist sitasi tidak boleh menelan
            // jawaban — tapi wajib ter-log keras untuk tindak lanjut.
            Log::error('RagAnswerer: gagal menyimpan ai_citation — jawaban tetap dikembalikan.', [
                'exception' => $e::class,
                'message' => $e->getMessage(),
            ]);
        }

        return $sitasi;
    }

    /** Format readable "Judul — bab, hlm. N" (bagian kosong dilewati). */
    private function labelSitasi(string $judul, ?string $bab, ?int $halaman): string
    {
        $detail = array_filter([
            $bab,
            $halaman !== null ? 'hlm. '.$halaman : null,
        ]);

        return $detail === [] ? $judul : $judul.' — '.implode(', ', $detail);
    }
}
