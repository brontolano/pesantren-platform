<?php

namespace Modules\AI\Providers;

use Illuminate\Support\ServiceProvider;
use Modules\AI\Console\AiAnomaliCommand;
use Modules\AI\Console\AiEskalasiPengingatCommand;
use Modules\AI\Console\AiHealthCommand;
use Modules\AI\Console\AiLaporanEskalasiCommand;
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

        // Views modul (Wizard Wawancara Digital, ringkasan kebijakan, dst).
        $this->loadViewsFrom(module_path($this->moduleName, 'resources/views'), $this->moduleNameLower);

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
                AiEskalasiPengingatCommand::class,
                AiLaporanEskalasiCommand::class,
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

        // T-A033 — registry tools data resmi (read-only). Singleton aman:
        // definisi tool stateless; scope/peran dibaca config per panggilan,
        // setting tenant diresolusi FRESH di dalam call().
        $this->app->singleton(\Modules\AI\Services\Tools\ToolRegistry::class);

        $this->registerHalamanPanelTenant();
    }

    /**
     * T-A011 — daftarkan halaman Filament modul ke panel TENANT tanpa
     * menyentuh TenantPanelProvider (Aturan Emas: file hanya Modules/AI).
     *
     * Panel Filament v5 dibangun malas (lazy) saat PanelRegistry di-resolve
     * lewat callback `resolving` (lihat Filament\Facades\Filament::registerPanel).
     * Kita menumpang `afterResolving` — DIJAMIN berjalan setelah seluruh
     * callback `resolving` selesai, sehingga panel 'tenant' pasti sudah ada,
     * apa pun urutan registrasi provider modul vs provider panel aplikasi.
     */
    protected function registerHalamanPanelTenant(): void
    {
        $daftarkan = function (\Filament\PanelRegistry $registry): void {
            $registry->get('tenant')?->pages([
                \Modules\AI\Filament\Pages\PengaturanAi::class,
                \Modules\AI\Filament\Pages\PersetujuanAi::class,
                \Modules\AI\Filament\Pages\SumberPengetahuan::class,
                \Modules\AI\Filament\Pages\EskalasiTanyaUstadz::class,
            ]);
        };

        if ($this->app->resolved(\Filament\PanelRegistry::class)) {
            $daftarkan($this->app->make(\Filament\PanelRegistry::class));

            return;
        }

        $this->app->afterResolving(\Filament\PanelRegistry::class, $daftarkan);
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
