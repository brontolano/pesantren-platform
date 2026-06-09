<?php

namespace Modules\Core\Models;

use App\Models\User;
use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * Jejak audit append-only (F2). Tidak pernah di-update/hapus.
 */
class AuditLog extends Model
{
    use BelongsToTenant;

    protected $table = 'audit_log';

    public const UPDATED_AT = null; // append-only

    protected $fillable = [
        'tenant_id', 'auditable_type', 'auditable_id', 'aksi', 'actor_id', 'perubahan', 'ip', 'created_at',
    ];

    protected $casts = [
        'perubahan' => 'array',
        'created_at' => 'datetime',
    ];

    public function auditable(): MorphTo
    {
        return $this->morphTo();
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }
}
