<?php

namespace Modules\Kesehatan\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Core\Concerns\Auditable;

/**
 * WP-28 — Master obat UKS (tanpa batch/expiry — keputusan G2-14).
 * Kolom `stok` HANYA diubah lewat ObatService (lockForUpdate) agar
 * konsisten dengan jejak obat_mutasi.
 */
class Obat extends Model
{
    use Auditable, BelongsToTenant;

    protected $table = 'obat';

    protected $fillable = [
        'tenant_id',
        'nama',
        'satuan',
        'stok',
        'ambang_menipis',
        'aktif',
    ];

    protected $casts = [
        'stok' => 'integer',
        'ambang_menipis' => 'integer',
        'aktif' => 'boolean',
    ];

    public function mutasi(): HasMany
    {
        return $this->hasMany(ObatMutasi::class, 'obat_id');
    }

    /** Stok di ambang atau di bawah ambang menipis. */
    public function scopeMenipis(Builder $query): Builder
    {
        return $query->where('aktif', true)->whereColumn('stok', '<=', 'ambang_menipis');
    }

    public function isMenipis(): bool
    {
        return $this->stok <= $this->ambang_menipis;
    }
}
