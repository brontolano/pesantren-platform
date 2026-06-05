<?php

namespace App\Console\Commands;

use App\Models\Tenant;
use Illuminate\Console\Command;

/**
 * Set mode website tenant.
 *   builder = pakai website bawaan platform (menu Website Builder/Profil/Pengaturan tampil)
 *   custom  = pakai website custom/statis sendiri (menu-menu itu DISEMBUNYIKAN)
 *
 *   php artisan tenant:website-mode asy-syifaa custom
 */
class TenantWebsiteMode extends Command
{
    protected $signature = 'tenant:website-mode {tenant : ID tenant} {mode : builder|custom}';

    protected $description = 'Set mode website tenant (builder|custom). custom menyembunyikan menu website bawaan.';

    public function handle(): int
    {
        $mode = $this->argument('mode');
        if (! in_array($mode, ['builder', 'custom'], true)) {
            $this->error("mode harus 'builder' atau 'custom'.");

            return self::FAILURE;
        }

        $tenant = Tenant::find($this->argument('tenant'));
        if (! $tenant) {
            $this->error('Tenant tidak ditemukan: '.$this->argument('tenant'));

            return self::FAILURE;
        }

        $tenant->website_mode = $mode;
        $tenant->save();

        $this->info(sprintf('Tenant %s → website_mode=%s', $tenant->getKey(), $mode));

        return self::SUCCESS;
    }
}
