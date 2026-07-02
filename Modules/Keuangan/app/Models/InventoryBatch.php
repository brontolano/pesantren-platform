<?php

namespace Modules\Keuangan\Models;

use App\Traits\BelongsToTenant;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Core\Concerns\Auditable;

/**
 * G2-14 — Batch/lot inventory dengan tanggal kedaluwarsa (FEFO).
 */
class InventoryBatch extends Model
{
    use Auditable, BelongsToTenant;

    protected $table = 'inventory_batch';

    protected $fillable = [
        'tenant_id', 'item_id', 'lokasi_id', 'nomor_batch', 'tanggal_kedaluwarsa', 'qty', 'is_active',
    ];

    protected $casts = [
        'tanggal_kedaluwarsa' => 'date',
        'qty' => 'decimal:3',
        'is_active' => 'boolean',
    ];

    public function item(): BelongsTo
    {
        return $this->belongsTo(InventoryItem::class, 'item_id');
    }

    public function lokasi(): BelongsTo
    {
        return $this->belongsTo(InventoryLokasi::class, 'lokasi_id');
    }

    /** Apakah batch sudah kedaluwarsa per tanggal acuan. */
    public function kedaluwarsa(?CarbonInterface $acuan = null): bool
    {
        if ($this->tanggal_kedaluwarsa === null) {
            return false;
        }

        return $this->tanggal_kedaluwarsa->lt($acuan ?? now());
    }
}
