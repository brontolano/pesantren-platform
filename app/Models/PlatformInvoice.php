<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PlatformInvoice extends Model
{
    protected $fillable = [
        'tenant_id',
        'period',
        'amount',
        'status',
        'payment_proof',
        'paid_at',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'paid_at' => 'datetime',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class, 'tenant_id');
    }

    public function markPaid(string $proofPath): self
    {
        $this->update([
            'status' => 'paid',
            'payment_proof' => $proofPath,
            'paid_at' => now(),
        ]);

        return $this->fresh();
    }

    /** Paksa koneksi CENTRAL (tabel billing hanya ada di DB central). */
    public function getConnectionName(): ?string
    {
        return config('tenancy.database.central_connection', parent::getConnectionName());
    }
}
