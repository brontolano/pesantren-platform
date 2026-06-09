<?php

namespace Modules\Pengadaan\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Core\Concerns\Auditable;

/**
 * G2-09 — Master Supplier/Vendor.
 */
class Supplier extends Model
{
    use Auditable, BelongsToTenant;

    protected $table = 'supplier';

    protected $fillable = [
        'tenant_id', 'nama', 'npwp', 'is_pkp', 'telepon', 'alamat',
        'bank_nama', 'bank_rekening', 'kehalalan', 'aktif',
    ];

    protected $casts = [
        'is_pkp' => 'boolean',
        'aktif' => 'boolean',
    ];

    public function purchaseOrders(): HasMany
    {
        return $this->hasMany(PurchaseOrder::class, 'supplier_id');
    }
}
