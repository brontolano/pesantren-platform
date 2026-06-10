<?php

namespace Modules\AI\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

/**
 * T-A003 — jejak forensik AI per tenant. APPEND-ONLY: trigger PostgreSQL
 * menolak UPDATE/DELETE (lihat migrasi), maka model ini hanya untuk INSERT
 * dan baca. JANGAN tambahkan fitur edit/hapus di atasnya.
 *
 * Koneksi: tenant default (tabel hidup di DB tenant; juga tercipta di central
 * mengikuti pola migrator platform — lihat AIServiceProvider).
 */
class AiAuditLog extends Model
{
    use BelongsToTenant, HasUlids;

    public const STATUS_SUKSES = 'sukses';

    public const STATUS_GAGAL = 'gagal';

    public const STATUS_DITOLAK_POLICY = 'ditolak_policy';

    public const STATUS_DEGRADASI = 'degradasi';

    protected $table = 'ai_audit_log';

    /** Append-only: hanya created_at (diisi manual, tanpa updated_at). */
    public $timestamps = false;

    protected $fillable = [
        'tenant_id',
        'user_id',
        'agent_key',
        'channel',
        'prompt_version_id',
        'ringkasan_konteks',
        'keputusan_kebijakan',
        'sumber_sitasi',
        'provider',
        'model',
        'tier',
        'degraded',
        'token_masuk',
        'token_keluar',
        'biaya_kredit',
        'latency',
        'status',
        'error_ringkas',
        'idempotency_key',
        'created_at',
    ];

    protected $casts = [
        'keputusan_kebijakan' => 'array',
        'sumber_sitasi' => 'array',
        'degraded' => 'boolean',
        'token_masuk' => 'integer',
        'token_keluar' => 'integer',
        'biaya_kredit' => 'decimal:4',
        'latency' => 'array',
        'created_at' => 'datetime',
    ];
}
