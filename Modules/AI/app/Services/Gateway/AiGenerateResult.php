<?php

namespace Modules\AI\Services\Gateway;

/**
 * Hasil generate dari gateway.
 *
 * degraded=true berarti jawaban berasal dari NullProvider (semua provider
 * nyata gagal/sirkuit terbuka) — pemanggil TIDAK menerima exception, cukup
 * menampilkan teks santun ini dan tidak memotong kredit penuh.
 */
final readonly class AiGenerateResult
{
    /**
     * @param  array<string, mixed>  $raw  Payload mentah provider (untuk audit/debug).
     * @param  bool  $ditolakPolicy  T-A020: true = diblokir PolicyEngine SEBELUM provider
     *                               (BUKAN degradasi — metering pakai namespace 'policy-'
     *                               dan audit berstatus ditolak_policy; jangan dicampur
     *                               dengan degraded supaya keduanya bisa dibedakan).
     * @param  list<array{judul: string, bab: string|null, halaman: int|null, label: string}>  $sitasi
     *                               T-A031: daftar sumber DETERMINISTIK yang dipakai jawaban —
     *                               dilampirkan RagAnswerer dari chunk retrieval terpakai (model
     *                               TIDAK pernah diminta menulis sitasi). label = format readable
     *                               "Judul — bab/halaman" untuk UI (T-A090) & enforcer (T-A022).
     * @param  \Modules\AI\Services\Guardrails\EnforcementVerdict|null  $verdictSitasi
     *                               T-A022: verdict CitationEnforcer atas jawaban ini (null =
     *                               jalur tanpa enforcer, mis. non-RAG/tanpa-rujukan). Audit per
     *                               panggilan generate sudah ditulis gateway — verdict cukup
     *                               dibawa di DTO untuk pemanggil/UI.
     */
    public function __construct(
        public string $text,
        public string $provider,
        public string $model,
        public bool $degraded = false,
        public ?int $promptTokens = null,
        public ?int $completionTokens = null,
        public ?int $latencyMs = null,
        public array $raw = [],
        public bool $ditolakPolicy = false,
        public array $sitasi = [],
        public ?\Modules\AI\Services\Guardrails\EnforcementVerdict $verdictSitasi = null,
    ) {}
}
