<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Plan extends Model
{
    protected $fillable = [
        'name',
        'billing_type',
        'default_price',
        'default_modules',
        'description',
        'is_active',
    ];

    protected $casts = [
        'default_price' => 'decimal:2',
        'default_modules' => 'array',
        'is_active' => 'boolean',
    ];

    public function subscriptions(): HasMany
    {
        return $this->hasMany(TenantSubscription::class, 'plan_id');
    }

    /** Paksa koneksi CENTRAL (tabel billing hanya ada di DB central). */
    public function getConnectionName(): ?string
    {
        return config('tenancy.database.central_connection', parent::getConnectionName());
    }
}
