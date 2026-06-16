<?php

namespace App\Services;

use App\Models\ModuleRegistry;
use App\Models\Tenant;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Sumber kebenaran visibilitas modul per tenant.
 *
 * Aturan:
 *  - Modul CORE        : selalu tampil di semua tenant.
 *  - Modul DEVELOPMENT : hanya tampil di tenant developer (demo).
 *  - Modul RELEASED    : tampil bila diaktifkan untuk tenant (tenant_modules);
 *                        tenant developer (demo) melihat semua released.
 */
class ModuleService
{
    /** Tenant yang dipakai sebagai sandbox pengembangan. */
    public const DEV_TENANTS = ['demo'];

    protected ?Collection $cache = null;

    protected function centralConnection(): string
    {
        return config('tenancy.database.central_connection', config('database.default'));
    }

    /** Registry modul (di-cache per request). */
    public function registry(): Collection
    {
        return $this->cache ??= ModuleRegistry::query()->orderBy('sort')->get()->keyBy('key');
    }

    public function isDevTenant(?Tenant $tenant): bool
    {
        return $tenant !== null && in_array($tenant->getKey(), self::DEV_TENANTS, true);
    }

    /** Apakah modul (released) diaktifkan untuk tenant tertentu. */
    public function isEnabledForTenant(string $moduleKey, string $tenantId): bool
    {
        return DB::connection($this->centralConnection())
            ->table('tenant_modules')
            ->where('tenant_id', $tenantId)
            ->where('module_name', $moduleKey)
            ->where('is_active', true)
            ->exists();
    }

    /** Apakah sebuah modul tampil untuk tenant ini. */
    public function isVisibleForTenant(string $moduleKey, ?Tenant $tenant): bool
    {
        $mod = $this->registry()->get($moduleKey);

        // Modul tak terdaftar di registry → jangan sembunyikan (mis. core/bawaan)
        if (! $mod) {
            return true;
        }
        if ($mod->is_core) {
            return true;
        }

        $isDev = $this->isDevTenant($tenant);

        if ($mod->status === 'development') {
            return $isDev; // hanya tenant developer
        }

        // released
        if ($isDev) {
            return true; // sandbox melihat semua modul released
        }

        return $tenant !== null && $this->isEnabledForTenant($moduleKey, $tenant->getKey());
    }
}
