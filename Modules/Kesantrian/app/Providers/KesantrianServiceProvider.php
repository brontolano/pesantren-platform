<?php

namespace Modules\Kesantrian\Providers;

use Illuminate\Support\ServiceProvider;

class KesantrianServiceProvider extends ServiceProvider
{
    protected string $moduleName = 'Kesantrian';

    protected string $moduleNameLower = 'kesantrian';

    public function boot(): void
    {
        $this->registerConfig();
        $this->loadMigrationsFrom(module_path($this->moduleName, 'database/migrations'));

        if ($this->app->runningInConsole()) {
            $this->commands([
                \Modules\Kesantrian\Console\ProvisionAkunSantriCommand::class,
                \Modules\Kesantrian\Console\AuditPenempatanCommand::class,
            ]);
        }
    }

    public function register(): void
    {
        $this->app->register(RouteServiceProvider::class);
        $this->app->register(EventServiceProvider::class);
    }

    protected function registerConfig(): void
    {
        $this->publishes([
            module_path($this->moduleName, 'config/config.php') => config_path($this->moduleNameLower . '.php'),
        ], 'config');
        $this->mergeConfigFrom(
            module_path($this->moduleName, 'config/config.php'),
            $this->moduleNameLower
        );
    }
}
