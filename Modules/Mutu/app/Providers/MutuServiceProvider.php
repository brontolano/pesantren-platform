<?php

namespace Modules\Mutu\Providers;

use Illuminate\Support\ServiceProvider;
use Modules\Mutu\Console\HitungLampuMutuCommand;

class MutuServiceProvider extends ServiceProvider
{
    protected string $moduleName = 'Mutu';

    protected string $moduleNameLower = 'mutu';

    public function boot(): void
    {
        $this->registerConfig();
        $this->loadMigrationsFrom(module_path($this->moduleName, 'database/migrations'));

        // 2a Dasbor Mutu — job harian lampu kuning.
        $this->commands([
            HitungLampuMutuCommand::class,
        ]);
    }

    public function register(): void
    {
        $this->app->register(RouteServiceProvider::class);
    }

    protected function registerConfig(): void
    {
        $this->publishes([
            module_path($this->moduleName, 'config/config.php') => config_path($this->moduleNameLower.'.php'),
        ], 'config');
        $this->mergeConfigFrom(
            module_path($this->moduleName, 'config/config.php'),
            $this->moduleNameLower
        );
    }
}
