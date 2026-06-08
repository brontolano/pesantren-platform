<?php

namespace Modules\Ledger\Models;

use App\Models\User;
use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class PeriodeAkuntansi extends Model
{
    use BelongsToTenant, SoftDeletes;

    protected $table = 'periode_akuntansi';

    protected $fillable = [
        'tenant_id',
        'nama',
        'tgl_mulai',
        'tgl_selesai',
        'status',
        'closed_at',
        'closed_by',
    ];

    protected $casts = [
        'tgl_mulai' => 'date',
        'tgl_selesai' => 'date',
        'closed_at' => 'datetime',
    ];

    public function jurnal(): HasMany
    {
        return $this->hasMany(Jurnal::class, 'periode_id');
    }

    public function closedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'closed_by');
    }

    public function isClosed(): bool
    {
        return $this->status === 'closed';
    }

    public function scopeOpen($query)
    {
        return $query->where('status', 'open');
    }
}
