<?php

namespace App\Console\Commands;

use App\Models\Tenant;
use Illuminate\Console\Command;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Aktivasi peran 1-aplikasi (Fase 2b/2c) di tenant EKSISTING — additive &
 * idempoten. Pengganti aman untuk re-seed manual: hanya MENAMBAH role 'jemaah'
 * + izin 'komentar'/'moderasi_komentar' dan memberi moderasi ke moderator
 * (admin/TU/kepala). TIDAK pernah men-sync/menghapus izin role lain.
 *
 * Tenant BARU mendapat ini otomatis via RolesAndPermissionsSeeder saat provision.
 * Jalankan untuk tenant lama setelah deploy:  php artisan jemaah:pasang-peran
 */
class PasangPeranJemaah extends Command
{
    protected $signature = 'jemaah:pasang-peran {--tenant= : ID tenant tertentu (default: semua tenant)}';

    protected $description = 'Pasang peran "jemaah" + izin komentar/moderasi_komentar ke tenant (idempoten, additive).';

    private const MODERATOR_ROLES = ['admin_pesantren', 'tu', 'kepala'];

    public function handle(): int
    {
        $tenants = $this->option('tenant')
            ? Tenant::query()->whereKey($this->option('tenant'))->get()
            : Tenant::all();

        if ($tenants->isEmpty()) {
            $this->warn('Tidak ada tenant yang cocok.');

            return self::SUCCESS;
        }

        $ok = 0;
        $gagal = 0;

        foreach ($tenants as $tenant) {
            try {
                $tenant->run(function (): void {
                    app(PermissionRegistrar::class)->forgetCachedPermissions();

                    foreach (['komentar', 'moderasi_komentar'] as $izin) {
                        Permission::firstOrCreate(['name' => $izin]);
                    }

                    Role::firstOrCreate(['name' => 'jemaah']);

                    // 'komentar' utk semua peran komunitas KECUALI peserta (calon
                    // santri eksternal) & super_admin (sudah semua izin). Additive.
                    Role::query()
                        ->whereNotIn('name', ['peserta', 'super_admin'])
                        ->get()
                        ->each(fn (Role $r) => $r->givePermissionTo('komentar'));

                    foreach (self::MODERATOR_ROLES as $roleName) {
                        Role::where('name', $roleName)->first()?->givePermissionTo('moderasi_komentar');
                    }

                    app(PermissionRegistrar::class)->forgetCachedPermissions();
                });

                $this->line('  ✓ '.$tenant->getTenantKey());
                $ok++;
            } catch (\Throwable $e) {
                $this->error('  ✗ '.$tenant->getTenantKey().': '.$e->getMessage());
                $gagal++;
            }
        }

        $this->info("Selesai. Sukses: {$ok}, Gagal: {$gagal}.");

        return $gagal > 0 ? self::FAILURE : self::SUCCESS;
    }
}
