<?php

namespace Modules\Keuangan\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Satu pergerakan stok item (masuk/keluar/penyesuaian) — jejak audit + HPP.
 */
class InventoryMutasi extends Model
{
    use BelongsToTenant;

    protected $table = 'inventory_mutasi';

    protected $fillable = [
        'tenant_id', 'item_id', 'jenis', 'qty', 'harga_satuan',
        'stok_sesudah', 'ref_type', 'ref_id', 'keterangan', 'dibuat_oleh',
    ];

    protected $casts = [
        'qty' => 'decimal:3',
        'harga_satuan' => 'decimal:2',
        'stok_sesudah' => 'decimal:3',
    ];

    public function item(): BelongsTo
    {
        return $this->belongsTo(InventoryItem::class, 'item_id');
    }
}
