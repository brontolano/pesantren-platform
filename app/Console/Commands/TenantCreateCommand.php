<?php

namespace App\Console\Commands;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;

class TenantCreateCommand extends Command
{
    protected $signature = 'tenant:create {name} {subdomain}';

    protected $description = 'Create a new tenant with the given name and subdomain';

    public function handle(): int
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

        if (Tenant::query()->where('name', $name)->exists()) {
            $existingTenant = Tenant::query()->where('name', $name)->first();

            if ($existingTenant?->id !== $subdomain) {
                $this->error("Nama tenant '{$name}' sudah dipakai tenant lain.");

                return self::FAILURE;
            }
        }

        $existingTenant = Tenant::query()->find($subdomain);

        if ($existingTenant) {
            $existingTenant->domains()->firstOrCreate([
                'domain' => $subdomain,
            ]);

            $this->bootstrapUsers($existingTenant);

            $this->warn("Tenant dengan subdomain '{$subdomain}' sudah ada. Data awal diverifikasi.");

            return self::SUCCESS;
        }

        $tenant = Tenant::create([
            'id' => $subdomain,
            'name' => $name,
            'is_active' => true,
        ]);

        $tenant->domains()->firstOrCreate([
            'domain' => $subdomain,
        ]);

        $this->bootstrapUsers($tenant);

        $this->info("Tenant '{$name}' created with subdomain '{$subdomain}'");

        return self::SUCCESS;
    }

    protected function bootstrapUsers(Tenant $tenant): void
    {
        $admin = User::withoutGlobalScopes()->firstOrCreate(
            ['email' => 'admin@'.$tenant->id.'.localhost'],
            [
                'name' => 'Admin '.$tenant->name,
                'password' => Hash::make('password'),
                'tenant_id' => $tenant->id,
            ]
        );
        $admin->assignRole('super_admin');

        $tu = User::withoutGlobalScopes()->firstOrCreate(
            ['email' => 'tu@'.$tenant->id.'.localhost'],
            [
                'name' => 'TU '.$tenant->name,
                'password' => Hash::make('password'),
                'tenant_id' => $tenant->id,
            ]
        );
        $tu->assignRole('tu');
    }
}
