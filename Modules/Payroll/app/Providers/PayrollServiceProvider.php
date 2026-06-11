<?php

namespace Modules\Payroll\Providers;

use Illuminate\Support\ServiceProvider;
use Modules\Payroll\Console\CekKontrakHabisCommand;

class PayrollServiceProvider extends ServiceProvider
{
    protected string $moduleName = 'Payroll';

    protected string $moduleNameLower = 'payroll';

    public function boot(): void
    {
        $this->registerConfig();
        $this->loadMigrationsFrom(module_path($this->moduleName, 'database/migrations'));

        // WP-16 — pengingat kontrak/SK habis (terjadwal harian).
        $this->commands([
            CekKontrakHabisCommand::class,
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
