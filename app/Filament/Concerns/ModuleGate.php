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
        return app(ModuleService::class)
            ->isVisibleForTenant($key, tenancy()->initialized ? tenant() : null);
    }
}
