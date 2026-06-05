<?php

namespace Modules\PPDB\Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * F0.6 — Role Spatie `peserta` untuk portal PWA Peserta SPMB.
 *
 * Patuh Hukum #2 Blueprint (SATU KEAMANAN): peserta = record `users` di DB
 * tenant + role `peserta` — BUKAN guard/sistem auth baru. Role ini TIDAK
 * diberi satu pun permission panel Filament; hanya 3 permission self-service
 * atas data pendaftarannya sendiri.
 *
 * Berjalan di DATABASE TENANT (konteks tenancy aktif), idempotent.
 *
 * Sumber kebenaran definisi ini juga tercermin di
 * `Database\Seeders\RolesAndPermissionsSeeder` (di-seed `TenantProvisioner`
 * saat tenant baru dibuat). Seeder modul ini melengkapi untuk:
 *  - backfill tenant LAMA yang dibuat sebelum role `peserta` ada
 *    (jalankan `php artisan tenants:seed --class="Modules\\PPDB\\Database\\Seeders\\PesertaRoleSeeder"`),
 *  - independensi modul (Blueprint §4): `module:seed PPDB` cukup untuk
 *    menyiapkan kebutuhan role portal peserta.
 * JAGA SINKRON bila daftar permission berubah.
 */
class PesertaRoleSeeder extends Seeder
{
    public const ROLE = 'peserta';

    /** Permission self-service peserta — TANPA permission Filament apa pun. */
    public const PERMISSIONS = [
        'view_own_ppdb_status',
        'complete_own_ppdb',
        'upload_own_ppdb_docs',
    ];

    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (self::PERMISSIONS as $permission) {
            Permission::firstOrCreate(['name' => $permission]);
        }

        $role = Role::firstOrCreate(['name' => self::ROLE]);

        // syncPermissions (bukan givePermissionTo): role peserta HANYA boleh
        // memegang 3 permission ini — permission lain yang nyasar dicabut.
        $role->syncPermissions(self::PERMISSIONS);

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
