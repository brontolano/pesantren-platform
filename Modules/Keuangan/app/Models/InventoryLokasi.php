<?php

namespace Modules\Keuangan\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Core\Concerns\Auditable;

/**
 * G2-14 — Lokasi penyimpanan inventory (gudang/unit/etalase).
 */
class InventoryLokasi extends Model
{
    use Auditable, BelongsToTenant;

    protected $table = 'inventory_lokasi';

    public const TIPE = [
        'gudang' => 'Gudang',
        'unit' => 'Unit/Kelas',
        'etalase' => 'Etalase/Toko',
    ];

    protected $fillable = [
        'tenant_id', 'kode', 'nama', 'tipe', 'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function scopeAktif($query)
    {
        return $query->where('is_active', true);
    }

    public function stok(): HasMany
    {
        return $this->hasMany(InventoryStokLokasi::class, 'lokasi_id');
    }
}
