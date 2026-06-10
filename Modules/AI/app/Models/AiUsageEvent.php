<?php

namespace Modules\AI\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

/**
 * T-A002 — meter pemakaian AI, KOPI PER-TENANT untuk dashboard admin tenant
 * (sumber kebenaran billing = ai_credit_ledger di central). APPEND-ONLY:
 * trigger PostgreSQL menolak UPDATE/DELETE — hanya INSERT dan baca.
 *
 * Koneksi: tenant default (tabel hidup di DB tenant, pola ai_audit_log).
 */
class AiUsageEvent extends Model
{
    use HasUlids;

    protected $table = 'ai_usage_event';

    /** Append-only: hanya created_at, tanpa updated_at. */
    public $timestamps = false;

    protected $fillable = [
        'agent_key',
        'channel',
        'provider',
        'model',
        'tier',
        'token_masuk',
        'token_keluar',
        'kredit',
        'degraded',
        'idempotency_key',
        'created_at',
    ];

    protected $casts = [
        'token_masuk' => 'integer',
        'token_keluar' => 'integer',
        'kredit' => 'decimal:4',
        'degraded' => 'boolean',
        'created_at' => 'datetime',
    ];
}
