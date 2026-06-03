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
            'view_notifikasi',
            'manage_notifikasi',
            'view_akademik',
            'manage_akademik',
            'view_own_children',
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission]);
        }

        $rolePermissions = [
            'super_admin' => Permission::query()->pluck('name')->all(),
            'admin_pesantren' => [
                'view_dashboard',
                'manage_users',
                'manage_roles',
                'manage_tenant_settings',
                'manage_modules',
                'view_santri',
                'manage_santri',
                'view_keuangan',
                'manage_keuangan',
                'view_notifikasi',
                'manage_notifikasi',
                'view_akademik',
                'manage_akademik',
            ],
            'tu' => [
                'view_dashboard',
                'manage_users',
                'view_santri',
                'manage_santri',
                'view_akademik',
            ],
            'keuangan' => [
                'view_dashboard',
                'view_keuangan',
                'manage_keuangan',
                'view_notifikasi',
                'view_santri',
            ],
            'guru' => [
                'view_dashboard',
                'view_santri',
                'view_akademik',
                'manage_akademik',
            ],
            'guru_tahfidz' => [
                'view_dashboard',
                'view_santri',
                'view_akademik',
                'manage_akademik',
            ],
            'konselor' => [
                'view_dashboard',
                'view_santri',
            ],
            'pengelola_uks' => [
                'view_dashboard',
                'view_santri',
            ],
            'pengelola_asrama' => [
                'view_dashboard',
                'view_santri',
                'manage_santri',
            ],
            'kepala' => [
                'view_dashboard',
                'view_santri',
                'view_keuangan',
                'view_notifikasi',
                'view_akademik',
                'manage_tenant_settings',
            ],
            'wali' => [
                'view_own_children',
            ],
        ];

        foreach ($rolePermissions as $roleName => $grants) {
            $role = Role::firstOrCreate(['name' => $roleName]);
            $role->syncPermissions($grants);
        }
    }
}
