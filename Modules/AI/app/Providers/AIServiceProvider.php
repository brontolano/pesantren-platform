<?php

namespace Modules\AI\Providers;

use Illuminate\Support\ServiceProvider;
use Modules\AI\Console\AiAnomaliCommand;
use Modules\AI\Console\AiHealthCommand;
use Modules\AI\Console\AiPromptCommand;
use Modules\AI\Services\Gateway\AiGateway;
use Modules\AI\Services\Gateway\CircuitBreaker;
use Modules\AI\Services\Gateway\ModelRouter;

class AIServiceProvider extends ServiceProvider
{
    protected string $moduleName = 'AI';

    protected string $moduleNameLower = 'ai';

    public function boot(): void
    {
        $this->registerConfig();

        // Migrasi TENANT (tabel ai_* per pesantren — mulai T-A003: ai_audit_log).
        // Path ini SUDAH terdaftar di config/tenancy.php 'migration_parameters'
        // sehingga ikut jalan di tenants:migrate.
        // CATATAN (pola platform, sama dgn Modules/Ledger): karena terdaftar ke
        // migrator biasa, migrasi tenant di sini JUGA ikut jalan saat `php artisan
        // migrate` di DB central — tabel ai_* tenant ikut tercipta di central.
        // Ini disengaja/diterima, bukan bug isolasi.
        $this->loadMigrationsFrom(module_path($this->moduleName, 'database/migrations'));

        // Migrasi CENTRAL (ai_model_config, dst) — dimuat loader migrasi biasa
        // (php artisan migrate di DB central). Path ini SENGAJA tidak terdaftar
        // di config/tenancy.php sehingga TIDAK ikut termigrasi ke DB tenant.
        $this->loadMigrationsFrom(module_path($this->moduleName, 'database/migrations/central'));

        if ($this->app->runningInConsole()) {
            $this->commands([
                AiHealthCommand::class,
                AiAnomaliCommand::class,
                AiPromptCommand::class,
            ]);
        }
    }

    public function register(): void
    {
        $this->app->register(RouteServiceProvider::class);

        // Komponen gateway sebagai singleton — satu circuit breaker & satu
        // cache router per proses.
        $this->app->singleton(CircuitBreaker::class, fn () => CircuitBreaker::fromConfig());
        $this->app->singleton(ModelRouter::class);
        $this->app->singleton(AiGateway::class);
        $this->app->singleton(\Modules\AI\Services\Prompts\PromptRegistry::class);
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
