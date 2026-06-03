<?php

namespace App\Models;

use Stancl\Tenancy\Database\Models\Tenant as BaseTenant;
use Stancl\Tenancy\Database\Concerns\HasDomains;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Tenant extends BaseTenant
{
    use HasDomains;

    public static function getCustomColumns(): array
    {
        return [
            'id',
            'name',
            'is_active',
        ];
    }

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function settings(): HasMany
    {
        return $this->hasMany(Setting::class);
    }
}
