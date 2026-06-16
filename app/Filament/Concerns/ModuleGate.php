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
        // mengelola lintas tenant. Super admin PESANTREN TIDAK lagi bypass —
        // ia ikut gerbang modul tenantnya agar navigasi tetap ramping (hanya
        // modul yang tersedia/aktif untuk tenant itu yang muncul).
        try {
            if (auth()->user()?->isPlatformSuperAdmin()) {
                return true;
            }
        } catch (\Throwable) {
            // tabel roles belum siap → lanjut ke gerbang normal
        }

        return app(ModuleService::class)
            ->isVisibleForTenant($key, tenancy()->initialized ? tenant() : null);
    }
}
