<?php

namespace Modules\Ledger\Providers;

use Illuminate\Support\ServiceProvider;
use Modules\Keuangan\Events\PembayaranDiverifikasi;
use Modules\Keuangan\Events\TabunganTransaksiDicatat;
use Modules\Ledger\Listeners\PostingPembayaranListener;
use Modules\Ledger\Listeners\PostingTabunganListener;

class LedgerServiceProvider extends ServiceProvider
{
    protected string $moduleName = 'Ledger';

    protected string $moduleNameLower = 'ledger';

    public function boot(): void
    {
        $this->registerConfig();
        $this->loadMigrationsFrom(module_path($this->moduleName, 'database/migrations'));

        // Auto-jurnal: dengarkan event pembayaran terverifikasi dari modul Keuangan.
        // Loose coupling — bila listener tidak terpasang, modul Keuangan tetap jalan.
        $this->app['events']->listen(
            PembayaranDiverifikasi::class,
            PostingPembayaranListener::class,
        );

        $this->app['events']->listen(
            TabunganTransaksiDicatat::class,
            PostingTabunganListener::class,
        );
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
