<?php

namespace Modules\Keuangan\Models;

use App\Models\User;
use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SetorKelilingSesi extends Model
{
    use BelongsToTenant;

    protected $table = 'setor_keliling_sesi';

    protected $fillable = [
        'tenant_id', 'petugas_id', 'tanggal', 'lokasi', 'status',
        'total', 'approved_by', 'approved_at', 'alasan_tolak',
    ];

    protected $casts = [
        'tanggal' => 'date',
        'total' => 'decimal:2',
        'approved_at' => 'datetime',
    ];

    public function items(): HasMany
    {
        return $this->hasMany(SetorKelilingItem::class, 'sesi_id');
    }

    public function petugas(): BelongsTo
    {
        return $this->belongsTo(User::class, 'petugas_id');
    }
}
