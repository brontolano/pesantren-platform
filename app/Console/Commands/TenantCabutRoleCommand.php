<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Console\Command;
use Spatie\Permission\PermissionRegistrar;
use Throwable;

/**
 * Cabut sebuah role dari user di SATU tenant — IDEMPOTENT & aman dijalankan ulang.
 *
 * Berguna untuk membereskan salah-assign role (mis. akun admin yang tak sengaja
 * diberi role `peserta`/`wali` sehingga terkunci dari panel /admin — lihat
 * User::isTenantStaff yang mengecualikan pemilik role peserta/wali, dan
 * App\Support\AppLauncher yang merutekan peserta/wali ke portalnya).
 *
 * Tenant boleh ditunjuk via ID tenant ATAU salah satu domainnya.
 * Contoh:
 *   php artisan tenant:cabut-role pesantren.asy-syifaa.com pesantren@asy-syifaa.com peserta
 */
class TenantCabutRoleCommand extends Command
{
    protected $signature = 'tenant:cabut-role
        {tenant : ID tenant ATAU salah satu domainnya (mis. pesantren.asy-syifaa.com)}
        {email : Email user yang akan dicabut rolenya}
        {role=peserta : Nama role yang dicabut (default: peserta)}';

    protected $description = 'Cabut sebuah role dari user pada satu tenant (idempotent)';

    public function handle(): int
    {
        $tenantArg = (string) $this->argument('tenant');
        $email = (string) $this->argument('email');
        $role = (string) $this->argument('role');

        $tenant = $this->resolveTenant($tenantArg);
        if ($tenant === null) {
            $this->error("Tenant '{$tenantArg}' tidak ditemukan (cek ID atau domain).");

            return self::FAILURE;
        }

        try {
            tenancy()->initialize($tenant);
            app(PermissionRegistrar::class)->forgetCachedPermissions();

            $user = User::query()->where('email', $email)->first();
            if ($user === null) {
                $this->error("User '{$email}' tidak ditemukan di tenant '{$tenant->getKey()}'.");

                return self::FAILURE;
            }

            $sebelum = $user->getRoleNames()->implode(', ') ?: '(tanpa role)';

            if ($user->hasRole($role)) {
                $user->removeRole($role);
                $this->info("Role '{$role}' DICABUT dari {$email}.");
            } else {
                $this->warn("User {$email} memang tidak memiliki role '{$role}' — tidak ada perubahan.");
            }

            $user = $user->fresh();
            app(PermissionRegistrar::class)->forgetCachedPermissions();

            $sesudah = $user->getRoleNames()->implode(', ') ?: '(tanpa role)';

            $this->table(
                ['Field', 'Nilai'],
                [
                    ['Tenant', (string) $tenant->getKey()],
                    ['User', $email],
                    ['Role sebelum', $sebelum],
                    ['Role sesudah', $sesudah],
                    ['Bisa akses /admin (isTenantStaff)', $user->isTenantStaff() ? 'YA' : 'TIDAK'],
                ]
            );

            if (! $user->isTenantStaff()) {
                $this->warn('Akun ini masih BUKAN staf (mungkin masih punya role peserta/wali, atau tenant_id null). '
                    .'Jika seharusnya admin, pastikan role staf seperti admin_pesantren tetap terpasang.');
            } elseif ($user->getRoleNames()->isEmpty()) {
                $this->warn('Akun kini TANPA role apa pun — bisa masuk /admin tetapi tanpa izin. '
                    .'Pertimbangkan assign role staf (mis. admin_pesantren).');
            }

            return self::SUCCESS;
        } catch (Throwable $e) {
            report($e);
            $this->error('Gagal: '.$e->getMessage());

            return self::FAILURE;
        } finally {
            tenancy()->end();
        }
    }

    private function resolveTenant(string $arg): ?Tenant
    {
        $byId = Tenant::query()->where('id', $arg)->first();
        if ($byId !== null) {
            return $byId;
        }

        return Tenant::query()
            ->whereHas('domains', fn ($q) => $q->where('domain', $arg))
            ->first();
    }
}
