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

    /** Fallback daftar modul inti bila registry tak termuat (cegah fail-open utk inti). */
    public const CORE_KEYS = ['core', 'kesantrian', 'keuangan', 'notifikasi', 'cms'];

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

    /**
     * Registry modul. Di-memo di CONTAINER (bukan hanya properti instance) supaya
     * dibagikan lintas ~115 resource dalam satu request — moduleVisible memanggil
     * app(ModuleService) (instance baru) per resource, jadi cache instance saja
     * akan hangus & memicu N+1 query registry.
     */
    public function registry(): Collection
    {
        if ($this->cache !== null) {
            return $this->cache;
        }

        $cacheKey = '__module_registry';
        if (app()->bound($cacheKey)) {
            return $this->cache = app()->make($cacheKey);
        }

        try {
            $reg = ModuleRegistry::query()->orderBy('sort')->get()->keyBy('key');
        } catch (\Throwable) {
            // Tabel registry belum siap (boot awal / konteks minimal) → jangan
            // sembunyikan apa pun (anggap semua modul bawaan/terlihat). Mencegah
            // gerbang modul melempar 500 saat registry tak tersedia.
            $reg = collect();
        }

        app()->instance($cacheKey, $reg);

        return $this->cache = $reg;
    }

    public function isDevTenant(?Tenant $tenant): bool
    {
        return $tenant !== null && in_array($tenant->getKey(), self::DEV_TENANTS, true);
    }

    /** Apakah modul tergolong INTI (selalu aktif, tak bisa dipasang/dimatikan tenant). */
    public function isCore(string $moduleKey): bool
    {
        $mod = $this->registry()->get($moduleKey);
        if ($mod !== null) {
            return (bool) $mod->is_core;
        }

        // Registry tak termuat → fallback daftar inti agar keputusan "inti"
        // tak fail-open (mis. nonaktif modul inti) saat DB central terganggu.
        return in_array($moduleKey, self::CORE_KEYS, true);
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

    /**
     * Apakah modul (released) diaktifkan untuk tenant tertentu. Set modul aktif
     * di-cache per request (container, ber-tenant-key) — jalur panas gerbang nav
     * memanggil ini per resource; tanpa cache = N+1 ke tenant_modules.
     */
    public function isEnabledForTenant(string $moduleKey, string $tenantId): bool
    {
        $cacheKey = '__active_modules_'.$tenantId;

        if (! app()->bound($cacheKey)) {
            try {
                $set = DB::connection($this->centralConnection())
                    ->table('tenant_modules')
                    ->where('tenant_id', $tenantId)
                    ->where('is_active', true)
                    ->pluck('module_name')
                    ->all();
            } catch (\Throwable) {
                $set = [];
            }
            app()->instance($cacheKey, $set);
        }

        return in_array($moduleKey, app()->make($cacheKey), true);
    }

    /** Apakah sebuah modul tampil untuk tenant ini. */
    public function isVisibleForTenant(string $moduleKey, ?Tenant $tenant): bool
    {
        $mod = $this->registry()->get($moduleKey);

        // Modul tak terdaftar di registry → fail-OPEN (jangan sembunyikan). DISENGAJA:
        // bila registry belum ter-seed (deploy hanya migrate) atau tabel belum siap
        // (registry() → koleksi kosong), kita TAK ingin seluruh nav hilang / admin blank.
        // Konsekuensi sadar: key typo/lupa-seed ikut tampil. Mitigasi yang benar =
        // pastikan registry ter-seed (ModuleRegistrySeeder + backfill PR#224) & key
        // gating cocok dgn katalog — BUKAN fail-closed di sini (audit #9).
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
