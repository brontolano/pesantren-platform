<?php

namespace App\Console\Commands;

use App\Models\Tenant;
use App\Models\TenantModule;
use App\Services\TenantProvisioner;
use Illuminate\Console\Command;
use Throwable;

/**
 * Backfill provisioning ke tenant lama secara IDEMPOTENT.
 *
 * Memanggil TenantProvisioner::provision() (yang firstOrCreate/updateOrInsert)
 * untuk tiap tenant sehingga branding default, modul released, roles, dan user
 * admin tersedia tanpa setup manual — termasuk untuk tenant yang lahir sebelum
 * fitur seedBranding()/seedModules() ada. Aman dijalankan berulang.
 *
 * Pakai pasca-deploy: `php artisan tenant:reprovision` (lihat checklist #17).
 */
class TenantReprovisionCommand extends Command
{
    protected $signature = 'tenant:reprovision
        {tenant? : ID/subdomain tenant tertentu; kosong = SEMUA tenant}';

    protected $description = 'Backfill provisioning (branding, modul, roles, admin) ke tenant lama — idempotent';

    public function handle(TenantProvisioner $provisioner): int
    {
        $target = $this->argument('tenant');

        $tenants = $target
            ? Tenant::query()->where('id', $target)->get()
            : Tenant::query()->orderBy('id')->get();

        if ($tenants->isEmpty()) {
            $this->error($target
                ? "Tenant '{$target}' tidak ditemukan."
                : 'Tidak ada tenant untuk di-reprovision.');

            return self::FAILURE;
        }

        $rows = [];
        $failed = 0;

        foreach ($tenants as $tenant) {
            try {
                $creds = $provisioner->provision($tenant);

                $modulAktif = TenantModule::query()
                    ->where('tenant_id', $tenant->getKey())
                    ->where('is_active', true)
                    ->count();

                $rows[] = [
                    $tenant->getKey(),
                    $modulAktif.' modul',
                    $creds['password'] !== null ? 'admin BARU' : 'admin ada',
                    'OK',
                ];
            } catch (Throwable $e) {
                $failed++;
                $rows[] = [
                    $tenant->getKey(),
                    '-',
                    '-',
                    'GAGAL: '.(string) str($e->getMessage())->limit(60),
                ];
                report($e);
            }
        }

        $this->table(['Tenant', 'Modul Aktif', 'Admin', 'Status'], $rows);

        $ok = count($rows) - $failed;
        $this->info("Selesai: {$ok} sukses, {$failed} gagal dari ".count($rows).' tenant.');

        return $failed === 0 ? self::SUCCESS : self::FAILURE;
    }
}
