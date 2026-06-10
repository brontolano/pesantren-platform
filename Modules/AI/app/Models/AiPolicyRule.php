<?php

namespace Modules\AI\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

/**
 * T-A020 — satu aturan guardrails (tabel TENANT).
 *
 * pola jsonb: {keyword: list<string>, regex: list<string>, param: object}.
 * Keyword dicocokkan kata utuh case-insensitive; regex dipakai apa adanya.
 * respons_template null = template default config ai.guardrails.*.
 *
 * Aturan Emas #8: isi keagamaan rule (daftar topik, dst.) HANYA dari seed
 * tinjauan ustadz (GATE-3) atau input pesantren — bukan dari kode.
 */
class AiPolicyRule extends Model
{
    public const TIPE_BLACKLIST_TOPIK = 'blacklist_topik';

    public const TIPE_LEVEL_FATWA = 'level_fatwa';

    public const TIPE_SCOPE_KANAL = 'scope_kanal';

    public const TIPE_JAM_LAYANAN = 'jam_layanan';

    public const TIPE_DISCLAIMER = 'disclaimer';

    protected $table = 'ai_policy_rule';

    protected $fillable = [
        'policy_id',
        'tipe',
        'pola',
        'respons_template',
        'prioritas',
        'aktif',
    ];

    protected $casts = [
        'policy_id' => 'integer',
        'pola' => 'array',
        'prioritas' => 'integer',
        'aktif' => 'boolean',
    ];

    /**
     * [T1] Tolak regex rusak SEBELUM tersimpan: smoke test preg_match tiap
     * pola regex. Regex yang invalid akan fail-closed di PolicyEngine
     * (memblokir SEMUA prompt) — lebih baik gagal keras saat input rule.
     */
    protected static function booted(): void
    {
        static::saving(function (self $rule): void {
            foreach ((array) (((array) $rule->pola)['regex'] ?? []) as $regex) {
                if (! is_string($regex) || @preg_match($regex, 'uji-smoke-test') === false) {
                    throw new LogicException(sprintf(
                        'AiPolicyRule: pola regex tidak valid dan ditolak disimpan: %s (%s)',
                        is_string($regex) ? $regex : gettype($regex),
                        preg_last_error_msg(),
                    ));
                }
            }
        });
    }

    public function policy(): BelongsTo
    {
        return $this->belongsTo(AiPolicy::class, 'policy_id');
    }
}
