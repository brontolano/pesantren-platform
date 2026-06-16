<?php

namespace App\Filament\Concerns;

use App\Services\ModuleService;

/**
 * Gerbang visibilitas modul untuk Filament Resource.
 * Resource memanggil static::moduleVisible('key') di shouldRegisterNavigation()
 * dan canAccess() agar tersembunyi bila modul tidak tersedia untuk tenant aktif
 * (mis. modul "development" hanya tampil di tenant demo).
 */
trait ModuleGate
{
    protected static function moduleVisible(string $key): bool
    {
        // Bypass HANYA untuk pemilik PLATFORM (super_admin tanpa tenant) yang
        // mengelola lintas tenant. Super admin PESANTREN ikut gerbang modul
        // tenantnya agar navigasi ramping juga untuk akun pemilik pesantren.
        try {
            if (auth()->user()?->isPlatformSuperAdmin()) {
                return true;
            }
        } catch (\Throwable) {
            // tabel roles belum siap → lanjut ke gerbang normal
        }

        $svc = app(ModuleService::class);
        $tenant = tenancy()->initialized ? tenant() : null;

        // Tidak tersedia/diizinkan untuk tenant (mis. released belum dipasang) → sembunyikan.
        if (! $svc->isVisibleForTenant($key, $tenant)) {
            return false;
        }

        // Modul INTI & modul tak-terdaftar (registry kosong/bawaan) selalu tampil —
        // tak punya siklus pasang/setup.
        if ($svc->isCore($key) || ! $svc->isRegistered($key)) {
            return true;
        }

        // Modul released (tambahan): tampil di navigasi HANYA setelah SIAP
        // (langkah wajibnya tuntas / sudah ditandai siap). Sebelum itu ia hidup
        // di "Pusat Modul" sebagai "perlu disiapkan" — inilah disclosure bertahap.
        return $svc->isReadyForTenant($key, $tenant);
    }
}
