<?php

namespace Modules\Core\Models;

use App\Models\User;
use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * Permintaan persetujuan atas satu record (polymorphic). Status:
 * menunggu → disetujui | ditolak.
 */
class ApprovalRequest extends Model
{
    use BelongsToTenant;

    protected $table = 'approval_request';

    protected $fillable = [
        'tenant_id', 'approvable_type', 'approvable_id', 'status', 'current_step',
        'approver_role', 'diminta_oleh', 'diputus_oleh', 'diputus_pada', 'alasan',
    ];

    protected $casts = [
        'current_step' => 'integer',
        'diputus_pada' => 'datetime',
    ];

    public function approvable(): MorphTo
    {
        return $this->morphTo();
    }

    public function peminta(): BelongsTo
    {
        return $this->belongsTo(User::class, 'diminta_oleh');
    }

    public function penyetuju(): BelongsTo
    {
        return $this->belongsTo(User::class, 'diputus_oleh');
    }

    public function scopeMenunggu(Builder $q): Builder
    {
        return $q->where('status', 'menunggu');
    }

    public function isMenunggu(): bool
    {
        return $this->status === 'menunggu';
    }
}
