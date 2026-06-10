<?php

namespace Modules\AI\Services\Guardrails;

/**
 * T-A020 — hasil evaluasi PolicyEngine atas satu permintaan.
 *
 * SEMUA keputusan (boleh maupun blokir) membawa info rule/kategori yang
 * menyala supaya audit (ai_audit_log.keputusan_kebijakan) selalu bisa
 * menjelaskan "kenapa".
 */
final readonly class PolicyDecision
{
    /**
     * @param  bool  $boleh  false = permintaan diblokir SEBELUM provider.
     * @param  string  $alasan  Penjelasan teknis singkat (untuk audit, bukan pengguna).
     * @param  string|null  $responsTemplate  Teks santun untuk pengguna saat blokir.
     * @param  int|null  $ruleId  ai_policy_rule.id yang menyala (null = gerbang config/setting).
     * @param  string|null  $kategori  belum_dikonfigurasi|jam_layanan|fitur_per_peran|blacklist_topik|level_fatwa.
     * @param  bool  $eskalasi  true = baris ai_escalation ditulis (antrean Tanya Ustadz).
     */
    public function __construct(
        public bool $boleh,
        public string $alasan = '',
        public ?string $responsTemplate = null,
        public ?int $ruleId = null,
        public ?string $kategori = null,
        public bool $eskalasi = false,
    ) {}

    public static function lolos(string $alasan = 'lolos semua gerbang kebijakan'): self
    {
        return new self(boleh: true, alasan: $alasan);
    }
}
