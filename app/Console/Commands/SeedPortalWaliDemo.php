<?php

namespace App\Console\Commands;

use Database\Seeders\PortalWaliDemoSeeder;
use Database\Seeders\TenantDemoSeeder;
use Illuminate\Console\Command;

class SeedPortalWaliDemo extends Command
{
    protected $signature = 'demo:seed-portal-wali {tenant=demo}';

    protected $description = 'Seed Portal Wali demo data (hafalan, rapor, kesehatan, izin) for a tenant';

    public function handle(): int
    {
        $tenantId = $this->argument('tenant');
        $tenant = \App\Models\Tenant::find($tenantId);

        if (! $tenant) {
            $this->error("Tenant '{$tenantId}' not found.");
            return 1;
        }

        $tenant->run(function () {
            $seeder = new TenantDemoSeeder();
            $seeder->setCommand($this);
            $seeder->run();

            $waliSeeder = new PortalWaliDemoSeeder();
            $waliSeeder->setCommand($this);
            $waliSeeder->run();
        });

        $this->info("Done — Portal Wali demo data seeded for tenant '{$tenantId}'.");
        return 0;
    }
}
