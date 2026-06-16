<?php

namespace App\Services;

use App\Models\ModuleRegistry;
use App\Models\Tenant;
use App\Support\ModuleSetupMap;
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

    /** Status siklus hidup modul untuk tenant (dipakai Pusat Modul). */
    public const STATUS_CORE = 'core';        // inti, selalu siap

    public const STATUS_LOCKED = 'locked';    // tak diizinkan (development utk non-dev / di luar paket)

    public const STATUS_AVAILABLE = 'available'; // boleh dipasang, belum dipasang

    public const STATUS_INSTALLED = 'installed'; // dipasang, perlu setup (belum siap)

    public const STATUS_READY = 'ready';      // dipasang & siap → muncul di nav

    protected ?Collection $cache = null;

    protected function centralConnection(): string
    {
        return config('tenancy.database.central_connection', config('database.default'));
    }

    /** Registry modul (di-cache per request). */
    public function registry(): Collection
    {
        if ($this->cache !== null) {
            return $this->cache;
        }

        try {
            return $this->cache = ModuleRegistry::query()->orderBy('sort')->get()->keyBy('key');
        } catch (\Throwable) {
            // Tabel registry belum siap (boot awal / konteks minimal) → jangan
            // sembunyikan apa pun (anggap semua modul bawaan/terlihat). Mencegah
            // gerbang modul melempar 500 saat registry tak tersedia.
            return $this->cache = collect();
        }
    }

    public function isDevTenant(?Tenant $tenant): bool
    {
        return $tenant !== null && in_array($tenant->getKey(), self::DEV_TENANTS, true);
    }

    /** Apakah modul tergolong INTI (selalu aktif, tak bisa dipasang/dimatikan tenant). */
    public function isCore(string $moduleKey): bool
    {
        $mod = $this->registry()->get($moduleKey);

        return $mod !== null && (bool) $mod->is_core;
    }

    /** Apakah modul terdaftar di registry (registry kosong/tabel hilang → false → tak digerbang). */
    public function isRegistered(string $moduleKey): bool
    {
        return $this->registry()->has($moduleKey);
    }

    /**
     * Apakah modul sudah ditandai SIAP (configured_at terisi) untuk tenant.
     * Set modul terkonfigurasi di-cache per request (container) — jalur panas
     * gerbang nav memanggil ini per resource.
     */
    public function isConfigured(string $moduleKey, ?Tenant $tenant): bool
    {
        if ($tenant === null) {
            return false;
        }

        $tid = $tenant->getKey();
        $cacheKey = '__configured_modules_'.$tid;

        if (! app()->bound($cacheKey)) {
            try {
                $set = DB::connection($this->centralConnection())
                    ->table('tenant_modules')
                    ->where('tenant_id', $tid)
                    ->whereNotNull('configured_at')
                    ->pluck('module_name')
                    ->all();
            } catch (\Throwable) {
                $set = [];
            }
            app()->instance($cacheKey, $set);
        }

        return in_array($moduleKey, app()->make($cacheKey), true);
    }

    /**
     * Apakah modul SIAP untuk tenant: sudah ditandai siap (persisten, anti-kedip)
     * ATAU langkah wajibnya tuntas live (ModuleSetupMap). Hanya relevan untuk modul
     * released ber-registry; inti selalu siap (dicek di pemanggil).
     */
    public function isReadyForTenant(string $moduleKey, ?Tenant $tenant): bool
    {
        if ($this->isConfigured($moduleKey, $tenant)) {
            return true;
        }

        return ModuleSetupMap::ready($moduleKey);
    }

    /**
     * Status siklus hidup modul untuk tenant — dipakai Pusat Modul untuk
     * menentukan kartu & tombol.
     */
    public function moduleStatus(string $moduleKey, ?Tenant $tenant): string
    {
        if ($this->isCore($moduleKey)) {
            return self::STATUS_CORE;
        }

        $mod = $this->registry()->get($moduleKey);

        // development utk tenant non-dev → terkunci (belum dirilis untuk tenant nyata).
        if ($mod !== null && $mod->status === 'development' && ! $this->isDevTenant($tenant)) {
            return self::STATUS_LOCKED;
        }

        $dipasang = $this->isDevTenant($tenant)
            || ($tenant !== null && $this->isEnabledForTenant($moduleKey, $tenant->getKey()));

        if (! $dipasang) {
            return self::STATUS_AVAILABLE;
        }

        return $this->isReadyForTenant($moduleKey, $tenant) ? self::STATUS_READY : self::STATUS_INSTALLED;
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
