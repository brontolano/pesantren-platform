<?php

namespace App\Console\Commands;

use App\Models\Tenant;
use Illuminate\Console\Command;
use Modules\Notifikasi\Database\Seeders\TemplateNotifikasiSeeder;

class SeedTenantTemplatesCommand extends Command
{
    protected $signature = 'tenant:seed-templates
        {tenant : ID tenant, misalnya asy-syifaa}
        {--force : Jalankan tanpa konfirmasi}';

    protected $description = 'Seed template notifikasi WA default ke tenant tertentu';

    public function handle(): int
    {
        $tenantId = (string) $this->argument('tenant');
        $tenant   = Tenant::find($tenantId);

        if (! $tenant) {
            $this->error("Tenant '{$tenantId}' tidak ditemukan.");
            return self::FAILURE;
        }

        if (! $this->option('force')) {
            if (! $this->confirm("Seed template notifikasi ke tenant '{$tenant->name}'?")) {
                $this->info('Dibatalkan.');
                return self::SUCCESS;
            }
        }

        tenancy()->initialize($tenant);

        try {
            (new TemplateNotifikasiSeeder())->run();
            $this->info("✅ Template notifikasi berhasil di-seed ke '{$tenant->name}'.");
        } finally {
            tenancy()->end();
        }

        return self::SUCCESS;
    }
}
