<?php

namespace Modules\Keuangan\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * G2-14 — Stok per (item, lokasi). Total stok tetap di inventory_item.stok
 * (sumber kebenaran valuasi); baris ini hanya rincian kuantitas per lokasi.
 */
class InventoryStokLokasi extends Model
{
    use BelongsToTenant;

    protected $table = 'inventory_stok_lokasi';

    protected $fillable = [
        'tenant_id', 'item_id', 'lokasi_id', 'stok',
    ];

    protected $casts = [
        'stok' => 'decimal:3',
    ];

    public function item(): BelongsTo
    {
        return $this->belongsTo(InventoryItem::class, 'item_id');
    }

    public function lokasi(): BelongsTo
    {
        return $this->belongsTo(InventoryLokasi::class, 'lokasi_id');
    }
}
