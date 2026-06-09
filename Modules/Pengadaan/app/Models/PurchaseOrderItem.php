<?php

namespace Modules\Pengadaan\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * G2-09 — Baris item Purchase Order.
 */
class PurchaseOrderItem extends Model
{
    use BelongsToTenant;

    protected $table = 'purchase_order_item';

    protected $fillable = [
        'tenant_id', 'purchase_order_id', 'uraian', 'qty', 'harga_satuan', 'subtotal', 'qty_diterima',
    ];

    protected $casts = [
        'qty' => 'decimal:2',
        'harga_satuan' => 'decimal:2',
        'subtotal' => 'decimal:2',
        'qty_diterima' => 'decimal:2',
    ];

    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class, 'purchase_order_id');
    }
}
