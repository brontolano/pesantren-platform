<?php

namespace App\Services;

use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Spatie\Permission\PermissionRegistrar;

/**
 * Lengkapi tenant baru setelah database-nya dibuat & dimigrasi.
 *
 * Dipakai oleh DUA jalur pembuatan tenant (panel CreateTenant & command
 * tenant:create) agar konsisten/DRY. Menutup gap onboarding:
 *  - DB tenant baru belum punya roles  -> seed RolesAndPermissionsSeeder
 *  - Tabel `tenants` LOKAL kosong (FK)  -> sisipkan baris tenant
 *  - Belum ada user admin               -> buat super_admin + password kuat
 *
 * Idempotent: aman dipanggil ulang.
 */
class TenantProvisioner
{
    /**
     * @return array{email: string, password: ?string}
     *     password = null bila user admin sudah ada sebelumnya.
     */
    public function provision(Tenant $tenant, ?string $adminEmail = null): array
    {
        $adminEmail ??= 'admin@'.$tenant->getKey().'.localhost';

        return $tenant->run(function () use ($tenant, $adminEmail): array {
            // (a) Roles & permissions untuk DB tenant ini.
            Artisan::call('db:seed', [
                '--class' => RolesAndPermissionsSeeder::class,
                '--force' => true,
            ]);
            app(PermissionRegistrar::class)->forgetCachedPermissions();

            // (b) Baris tenant LOKAL — target FK semua tabel operasional.
            DB::table('tenants')->updateOrInsert(
                ['id' => $tenant->getKey()],
                [
                    'name' => $tenant->name,
                    'is_active' => $tenant->is_active ?? true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            );

            // (c) User admin super_admin.
            $existing = User::withoutGlobalScopes()->where('email', $adminEmail)->first();
            if ($existing) {
                $existing->assignRole('super_admin');

                return ['email' => $adminEmail, 'password' => null];
            }

            $password = Str::password(16);
            $admin = User::withoutGlobalScopes()->create([
                'email' => $adminEmail,
                'name' => 'Admin '.$tenant->name,
                'tenant_id' => $tenant->getKey(),
                'password' => Hash::make($password),
            ]);
            $admin->assignRole('super_admin');

            return ['email' => $adminEmail, 'password' => $password];
        });
    }
}
