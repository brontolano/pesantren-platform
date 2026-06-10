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
        // SUPER ADMIN melihat SEMUA modul (mengelola platform/tenant) —
        // gerbang aktivasi modul hanya berlaku untuk pengguna biasa.
        try {
            if (auth()->user()?->hasRole('super_admin')) {
                return true;
            }
        } catch (\Throwable) {
            // tabel roles belum siap → lanjut ke gerbang normal
        }

        return app(ModuleService::class)
            ->isVisibleForTenant($key, tenancy()->initialized ? tenant() : null);
    }
}
