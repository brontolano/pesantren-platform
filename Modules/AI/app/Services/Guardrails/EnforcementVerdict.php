<?php

namespace Modules\AI\Services\Guardrails;

/**
 * T-A022 — hasil verifikasi CitationEnforcer atas satu jawaban.
 *
 * skorOverlap = rata-rata proporsi token konten kalimat jawaban yang juga
 * muncul di gabungan chunk konteks (0..1). lolos=false berarti pemanggil
 * (RagAnswerer) WAJIB regenerasi 1× atau menolak santun — jawaban yang
 * gagal verifikasi TIDAK PERNAH boleh sampai ke pengguna.
 */
final readonly class EnforcementVerdict
{
    public function __construct(
        public bool $lolos,
        public float $skorOverlap,
        public string $alasan,
    ) {}

    /** @return array{lolos: bool, skor_overlap: float, alasan: string} */
    public function keArray(): array
    {
        return [
            'lolos' => $this->lolos,
            'skor_overlap' => round($this->skorOverlap, 4),
            'alasan' => $this->alasan,
        ];
    }
}
