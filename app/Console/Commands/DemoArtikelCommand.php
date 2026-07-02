<?php

namespace App\Console\Commands;

use App\Models\Tenant;
use Database\Seeders\DemoArtikelSeeder;
use Illuminate\Console\Command;

/**
 * Isi artikel demo pada tenant demo (etalase). Idempoten & aman: hanya menyentuh
 * tenant demo (config demo.autologin_tenant). Jalankan setelah deploy:
 *   php artisan demo:artikel
 */
class DemoArtikelCommand extends Command
{
    protected $signature = 'demo:artikel';

    protected $description = 'Isi artikel demo (6 artikel pesantren) pada tenant demo — idempoten.';

    public function handle(): int
    {
        $tenantId = config('demo.autologin_tenant', 'demo');
        $tenant = Tenant::find($tenantId);

        if ($tenant === null) {
            $this->error("Tenant demo '{$tenantId}' tidak ditemukan.");

            return self::FAILURE;
        }

        $tenant->run(function (): void {
            (new DemoArtikelSeeder)->run();
        });

        $this->info("Artikel demo terisi pada tenant '{$tenantId}'.");

        return self::SUCCESS;
    }
}
