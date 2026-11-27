<?php

namespace App\Providers;

use App\Services\Provisioning\PostgresTenantDatabaseProvisioner;
use App\Services\Provisioning\TenantDatabaseProvisioner;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(TenantDatabaseProvisioner::class, PostgresTenantDatabaseProvisioner::class);
    }

    public function boot(): void
    {
        // App-local guards and providers are intentionally distinct from v1.
    }
}
