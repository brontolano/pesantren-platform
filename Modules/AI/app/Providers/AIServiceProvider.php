<?php

namespace Modules\AI\Providers;

use Filament\PanelRegistry;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\ServiceProvider;
use Modules\AI\Console\AiAnomaliCommand;
use Modules\AI\Console\AiBersihkanPercakapanCommand;
use Modules\AI\Console\AiEskalasiPengingatCommand;
use Modules\AI\Console\AiEvalCommand;
use Modules\AI\Console\AiFaqMiningCommand;
use Modules\AI\Console\AiHealthCommand;
use Modules\AI\Console\AiLaporanEskalasiCommand;
use Modules\AI\Console\AiPromptCommand;
use Modules\AI\Filament\Pages\EskalasiTanyaUstadz;
use Modules\AI\Filament\Pages\GeneratorRpp;
use Modules\AI\Filament\Pages\GeneratorSoal;
use Modules\AI\Filament\Pages\IkhtisarKitab;
use Modules\AI\Filament\Pages\KotakMasukWa;
use Modules\AI\Filament\Pages\PengaturanAi;
use Modules\AI\Filament\Pages\PersetujuanAi;
use Modules\AI\Filament\Pages\SuaraPasar;
use Modules\AI\Filament\Pages\SumberPengetahuan;
use Modules\AI\Filament\Pages\UsulanFaq;
use Modules\AI\Services\Gateway\AiGateway;
use Modules\AI\Services\Gateway\CircuitBreaker;
use Modules\AI\Services\Gateway\ModelRouter;
use Modules\AI\Services\Prompts\PromptRegistry;
use Modules\AI\Services\Research\ResearchTelemetry;
use Modules\AI\Services\Tools\ToolRegistry;

class AIServiceProvider extends ServiceProvider
{
    protected string $moduleName = 'AI';

    protected string $moduleNameLower = 'ai';

    public function boot(): void
    {
        $this->registerConfig();

        // Views modul (Wizard Wawancara Digital, ringkasan kebijakan, dst).
        $this->loadViewsFrom(module_path($this->moduleName, 'resources/views'), $this->moduleNameLower);

        // T-A090 — komponen Blade UI AI standar (anonim, namespace 'ai'):
        // <x-ai::badge-ai>, <x-ai::blok-sitasi>, <x-ai::editor-draf>,
        // <x-ai::indikator-kredit>, <x-ai::tombol-tanya-ustadz>,
        // <x-ai::panel-asisten>, <x-ai::rating-jawaban>.
        // Registrasi EKSPLISIT (tidak hanya menumpang loadViewsFrom) supaya
        // resolusi komponen tidak bergantung urutan namespace view lain.
        Blade::anonymousComponentPath(
            module_path($this->moduleName, 'resources/views/components'),
            $this->moduleNameLower,
        );

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
                AiBersihkanPercakapanCommand::class,
                AiEvalCommand::class,
                AiFaqMiningCommand::class,
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
        $this->app->singleton(PromptRegistry::class);

        // T-A033 — registry tools data resmi (read-only). Singleton aman:
        // definisi tool stateless; scope/peran dibaca config per panggilan,
        // setting tenant diresolusi FRESH di dalam call().
        $this->app->singleton(ToolRegistry::class);

        // T-A013 — telemetri riset opt-in. SCOPED (bukan singleton): memo
        // status opt-in benar-benar per request/job — tidak nyangkut basi
        // antar job di queue worker berumur panjang.
        $this->app->scoped(ResearchTelemetry::class);

        $this->registerHalamanPanel();
    }

    /**
     * T-A011 — daftarkan halaman Filament modul ke panel TENANT (dan sejak
     * T-A013 juga ke panel PLATFORM 'admin') tanpa menyentuh PanelProvider
     * aplikasi (Aturan Emas: file hanya Modules/AI).
     *
     * Panel Filament v5 dibangun malas (lazy) saat PanelRegistry di-resolve
     * lewat callback `resolving` (lihat Filament\Facades\Filament::registerPanel).
     * Kita menumpang `afterResolving` — DIJAMIN berjalan setelah seluruh
     * callback `resolving` selesai, sehingga panel pasti sudah ada,
     * apa pun urutan registrasi provider modul vs provider panel aplikasi.
     */
    protected function registerHalamanPanel(): void
    {
        $daftarkan = function (PanelRegistry $registry): void {
            $registry->get('tenant')?->pages([
                PengaturanAi::class,
                PersetujuanAi::class,
                SumberPengetahuan::class,
                EskalasiTanyaUstadz::class,
                GeneratorSoal::class,
                GeneratorRpp::class,
                IkhtisarKitab::class,
                KotakMasukWa::class,
                UsulanFaq::class,
            ]);

            // T-A013 — dashboard internal "Suara Pasar" di panel PLATFORM
            // (central, super_admin only — BUKAN panel tenant).
            $registry->get('admin')?->pages([
                SuaraPasar::class,
            ]);
        };

        if ($this->app->resolved(PanelRegistry::class)) {
            $daftarkan($this->app->make(PanelRegistry::class));

            return;
        }

        $this->app->afterResolving(PanelRegistry::class, $daftarkan);
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
