<?php

namespace Modules\Pengadaan\Models;

use App\Models\User;
use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Core\Concerns\Approvable;
use Modules\Core\Concerns\Auditable;
use Modules\Ledger\Models\Akun;
use Modules\Ledger\Models\DivisiUnit;
use Modules\Ledger\Models\SumberDana;

/**
 * G2-09 — Purchase Order. Approvable (F1) + Auditable (F2). Field gate approval
 * = `total`. Posting beban via Belanja saat barang diterima penuh.
 */
class PurchaseOrder extends Model
{
    use Approvable, Auditable, BelongsToTenant;

    protected $table = 'purchase_order';

    protected $fillable = [
        'tenant_id', 'nomor', 'supplier_id', 'tanggal', 'divisi_id', 'sumber_dana_id',
        'akun_beban_id', 'jenis', 'total', 'status', 'keterangan', 'belanja_id',
        'dibuat_oleh', 'disetujui_oleh', 'disetujui_pada',
    ];

    protected $casts = [
        'tanggal' => 'date',
        'total' => 'decimal:2',
        'disetujui_pada' => 'datetime',
    ];

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class, 'supplier_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(PurchaseOrderItem::class, 'purchase_order_id');
    }

    public function divisi(): BelongsTo
    {
        return $this->belongsTo(DivisiUnit::class, 'divisi_id');
    }

    public function sumberDana(): BelongsTo
    {
        return $this->belongsTo(SumberDana::class, 'sumber_dana_id');
    }

    public function akunBeban(): BelongsTo
    {
        return $this->belongsTo(Akun::class, 'akun_beban_id');
    }

    public function belanja(): BelongsTo
    {
        return $this->belongsTo(Belanja::class, 'belanja_id');
    }

    public function penyetuju(): BelongsTo
    {
        return $this->belongsTo(User::class, 'disetujui_oleh');
    }

    /** Apakah semua item sudah diterima penuh (qty_diterima >= qty)? */
    public function diterimaPenuh(): bool
    {
        $items = $this->relationLoaded('items') ? $this->items : $this->items()->get();

        return $items->isNotEmpty()
            && $items->every(fn (PurchaseOrderItem $i) => (float) $i->qty_diterima >= (float) $i->qty - 0.0001);
    }
}
