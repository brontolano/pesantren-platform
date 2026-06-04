<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TenantSubscription extends Model
{
    protected $fillable = [
        'tenant_id',
        'plan_id',
        'override',
        'billing_cycle',
        'starts_at',
        'ends_at',
        'status',
    ];

    protected $casts = [
        'override'   => 'array',
        'starts_at'  => 'date',
        'ends_at'    => 'date',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class, 'tenant_id');
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class, 'plan_id');
    }

    /** Harga efektif: override menang jika ada, fallback ke plan */
    public function effectivePrice(): float
    {
        return (float) ($this->override['price'] ?? $this->plan->default_price ?? 0);
    }

    /** Modul efektif: override menang jika ada */
    public function effectiveModules(): array
    {
        return $this->override['modules'] ?? $this->plan->default_modules ?? [];
    }
}
