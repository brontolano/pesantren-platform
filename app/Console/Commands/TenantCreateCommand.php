<?php

namespace App\Console\Commands;

use App\Models\Tenant;
use App\Services\TenantProvisioner;
use Illuminate\Console\Command;

class TenantCreateCommand extends Command
{
    protected $signature = 'tenant:create {name} {subdomain}';

    protected $description = 'Create a new tenant with the given name and subdomain';

    public function handle(TenantProvisioner $provisioner): int
    {
        $name = trim((string) $this->argument('name'));
        $subdomain = strtolower(trim((string) $this->argument('subdomain')));

        if ($name === '' || $subdomain === '') {
            $this->error('Nama tenant dan subdomain wajib diisi.');

            return self::FAILURE;
        }

        if (! preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $subdomain)) {
            $this->error('Subdomain hanya boleh huruf kecil, angka, dan tanda hubung.');

            return self::FAILURE;
        }

        $existingByName = Tenant::query()->where('name', $name)->first();
        if ($existingByName && $existingByName->getKey() !== $subdomain) {
            $this->error("Nama tenant '{$name}' sudah dipakai tenant lain.");

            return self::FAILURE;
        }

        $tenant = Tenant::query()->find($subdomain);

        if ($tenant) {
            $this->warn("Tenant '{$subdomain}' sudah ada — memverifikasi data awal.");
        } else {
            // create() memicu TenantCreated -> CreateDatabase + MigrateDatabase (sync).
            $tenant = Tenant::create([
                'id' => $subdomain,
                'name' => $name,
                'is_active' => true,
            ]);
            $this->info("Tenant '{$name}' dibuat (subdomain: {$subdomain}).");
        }

        $tenant->domains()->firstOrCreate(['domain' => $subdomain]);

        // Seed roles + baris tenant lokal + user admin (DRY via service bersama).
        $creds = $provisioner->provision($tenant);

        if ($creds['password'] !== null) {
            $this->newLine();
            $this->warn('⚠ SIMPAN KREDENSIAL ADMIN (ditampilkan sekali):');
            $this->table(['Email', 'Password'], [[$creds['email'], $creds['password']]]);
            $this->error('Ganti password setelah login pertama.');
        } else {
            $this->line("User admin sudah ada: {$creds['email']}");
        }

        return self::SUCCESS;
    }
}
