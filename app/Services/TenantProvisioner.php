<?php

namespace App\Services;

use App\Models\ModuleRegistry;
use App\Models\Setting;
use App\Models\Tenant;
use App\Models\TenantModule;
use App\Models\TenantStorage;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Modules\Ledger\Database\Seeders\LedgerSeeder;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use App\Services\StorageQuotaService;
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

        // Kuota penyimpanan default (CENTRAL) — seperti OS memberi disk default.
        TenantStorage::firstOrCreate(
            ['tenant_id' => $tenant->getKey()],
            ['quota_gb' => StorageQuotaService::DEFAULT_GB],
        );

        // Aktivasi modul "released" non-core (CENTRAL) agar nav modul langsung
        // tampil tanpa setup manual — menutup error "modul tak tampil".
        $this->seedModules($tenant);

        return $tenant->run(function () use ($tenant, $adminEmail): array {
            // (a0) Extension pgvector untuk Modul AI (RAG/embeddings).
            // Idempoten; toleran bila server PG belum menyediakan extension
            // (mis. dev lokal tanpa pgvector) — provisioning tidak boleh gagal
            // karena fitur add-on.
            $this->ensurePgvectorExtension();

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

            // (b2) Branding default non-destruktif (tenant DB) — agar tema &
            // jadwal sholat punya nilai sejak lahir (sumber error tema).
            $this->seedBranding($tenant);

            // (b3) Bagan Akun (COA) standar + sumber dana + kebijakan kehalalan.
            // Idempoten (updateOrCreate by kode) — memastikan akun inti SELALU ada
            // di tiap tenant (mis. penyusutan 5-7000/1-2900, pelepasan 4-6000/5-8000)
            // sehingga modul keuangan/aset tidak gagal karena COA belum di-seed.
            Artisan::call('db:seed', [
                '--class' => LedgerSeeder::class,
                '--force' => true,
            ]);

            // (c) User admin super_admin.
            $existing = User::withoutGlobalScopes()->where('email', $adminEmail)->first();
            if ($existing) {
                $existing->assignRole('super_admin');

                return ['email' => $adminEmail, 'password' => null];
            }

            // Alfanumerik (tanpa simbol) — mudah diketik & aman ditampilkan.
            $password = Str::password(14, letters: true, numbers: true, symbols: false);
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

    /**
     * CREATE EXTENSION vector di DB tenant aktif (konteks $tenant->run()).
     * Dipisah agar bisa dipanggil ulang oleh tenant:reprovision untuk
     * tenant eksisting setelah image postgres diganti ke pgvector.
     */
    private function ensurePgvectorExtension(): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            return;
        }

        try {
            DB::statement('CREATE EXTENSION IF NOT EXISTS vector');
        } catch (\Throwable $e) {
            logger()->warning('pgvector belum tersedia di server PostgreSQL — fitur AI butuh image pgvector/pgvector:pg16.', [
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Default branding non-destruktif untuk tenant aktif (di DB tenant).
     * firstOrCreate per key → TIDAK menimpa pengaturan yang sudah diisi admin.
     * Bentuk value JSON mengikuti PengaturanBranding (kunci text/color/logo_path).
     *
     * Harus dipanggil dari dalam konteks tenant ($tenant->run()).
     */
    private function seedBranding(Tenant $tenant): void
    {
        $defaults = [
            'branding_name' => ['text' => $tenant->name],
            'branding_tagline' => ['text' => null],
            'branding_primary_color' => ['color' => '#14532d'],
            'branding_secondary_color' => ['color' => '#c2410c'],
            'branding_font' => ['text' => 'default'],
            // Placeholder: path null → TenantBranding::logoUrl() balik null → UI fallback.
            'branding_logo' => ['logo_path' => null],
            'branding_latitude' => ['text' => '-6.85'],
            'branding_longitude' => ['text' => '107.49'],
        ];

        foreach ($defaults as $key => $value) {
            Setting::firstOrCreate(['key' => $key], ['value' => $value]);
        }
    }

    /**
     * Aktifkan modul "released" non-core untuk tenant di tabel CENTRAL
     * tenant_modules (idempotent). Modul core selalu aktif (tak perlu baris);
     * modul development hanya untuk tenant demo (tak diaktifkan di sini).
     *
     * Harus dipanggil dari konteks CENTRAL (di luar $tenant->run()).
     */
    private function seedModules(Tenant $tenant): void
    {
        $released = ModuleRegistry::query()
            ->where('status', 'released')
            ->where('is_core', false)
            ->pluck('key');

        foreach ($released as $key) {
            TenantModule::firstOrCreate(
                ['tenant_id' => $tenant->getKey(), 'module_name' => $key],
                ['is_active' => true],
            );
        }
    }
}
