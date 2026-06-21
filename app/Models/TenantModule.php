<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TenantModule extends Model
{
    protected $fillable = ['tenant_id', 'module_name', 'is_active', 'installed_at', 'configured_at'];

    protected $casts = [
        'is_active' => 'boolean',
        'installed_at' => 'datetime',
        'configured_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        // Buang cache set modul (ModuleService) saat aktivasi berubah, agar baca
        // SETELAH tulis konsisten dalam request yang sama (tak bergantung redirect).
        $bust = static function (self $m): void {
            app()->forgetInstance('__active_modules_'.$m->tenant_id);
            app()->forgetInstance('__configured_modules_'.$m->tenant_id);
        };
        static::saved($bust);
        static::deleted($bust);
    }

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

    /**
     * Paksa koneksi CENTRAL — tabel tenant_modules hanya ada di DB central.
     * Tanpa ini, pemanggilan dari konteks tenant (mis. CheckModuleActive)
     * jatuh ke DB tenant → "relation does not exist". Seragam dgn ModuleRegistry.
     */
    public function getConnectionName(): ?string
    {
        return config('tenancy.database.central_connection', parent::getConnectionName());
    }
}
