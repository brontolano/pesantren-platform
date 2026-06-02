<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TenantModule extends Model
{
    protected $fillable = ['tenant_id', 'module_name', 'is_active'];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public static function isModuleActive(string $tenantId, string $moduleName): bool
    {
        return static::where('tenant_id', $tenantId)
            ->where('module_name', $moduleName)
            ->where('is_active', true)
            ->exists();
    }
}
