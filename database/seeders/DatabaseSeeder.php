<?php

namespace Database\Seeders;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(RolesAndPermissionsSeeder::class);
        $this->call(ModuleRegistrySeeder::class); // katalog modul CENTRAL (idempotent)
        $this->call(WilayahSeeder::class);

        $tenants = Tenant::all();

        foreach ($tenants as $tenant) {
            tenancy()->initialize($tenant);

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

            tenancy()->end();
        }
    }
}
