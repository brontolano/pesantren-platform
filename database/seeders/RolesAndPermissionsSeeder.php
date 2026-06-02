<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class RolesAndPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        $permissions = [
            'view_dashboard',
            'manage_users',
            'manage_roles',
            'manage_tenant_settings',
            'manage_modules',
            'view_santri',
            'manage_santri',
            'view_keuangan',
            'manage_keuangan',
            'view_akademik',
            'manage_akademik',
            'view_own_children',
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission]);
        }

        Role::firstOrCreate(['name' => 'super_admin'])
            ->givePermissionTo(Permission::all());

        Role::firstOrCreate(['name' => 'tu'])
            ->givePermissionTo([
                'view_dashboard', 'manage_users', 'view_santri', 'manage_santri',
            ]);

        Role::firstOrCreate(['name' => 'keuangan'])
            ->givePermissionTo([
                'view_dashboard', 'view_keuangan', 'manage_keuangan', 'view_santri',
            ]);

        Role::firstOrCreate(['name' => 'guru'])
            ->givePermissionTo([
                'view_dashboard', 'view_santri', 'view_akademik', 'manage_akademik',
            ]);

        Role::firstOrCreate(['name' => 'wali'])
            ->givePermissionTo([
                'view_own_children',
            ]);
    }
}
