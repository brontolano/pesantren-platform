<?php

namespace App\Console\Commands;

use App\Models\Tenant;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;

/**
 * Jalankan migrasi tenant (semua/satu) secara IDEMPOTENT dengan laporan per
 * tenant — supaya modul/migrasi baru tak meninggalkan tenant lama (drift skema).
 *
 * Mendelegasikan ke `tenants:migrate` (stancl) yang memakai migration_parameters
 * di config/tenancy.php (path central + tiap modul), lalu merangkum status.
 * Aman dijalankan berulang: migrasi yang sudah jalan dilewati.
 *
 * Pakai pasca-deploy bersama `tenant:reprovision` (lihat checklist #17).
 */
class TenantMigrateAllCommand extends Command
{
    protected $signature = 'tenant:migrate-all
        {tenant? : ID tenant tertentu; kosong = SEMUA tenant}';

    protected $description = 'Migrasi tenant (semua/satu) idempotent + laporan status per tenant';

    public function handle(): int
    {
        $target = $this->argument('tenant');

        $tenants = $target
            ? Tenant::query()->where('id', $target)->get()
            : Tenant::query()->orderBy('id')->get();

        if ($tenants->isEmpty()) {
            $this->error($target
                ? "Tenant '{$target}' tidak ditemukan."
                : 'Tidak ada tenant untuk dimigrasi.');

            return self::FAILURE;
        }

        $rows = [];
        $failed = 0;

        foreach ($tenants as $tenant) {
            $exit = Artisan::call('tenants:migrate', ['--tenants' => [$tenant->getKey()]]);
            $output = Artisan::output();

            $bersih = str_contains($output, 'Nothing to migrate');
            $ok = $exit === 0;

            if (! $ok) {
                $failed++;
            }

            $rows[] = [
                $tenant->getKey(),
                $bersih ? '0 pending (bersih)' : 'migrasi dijalankan',
                $ok ? 'OK' : 'FAIL',
            ];
        }

        $this->table(['Tenant', 'Migrasi', 'Status'], $rows);

        $ok = count($rows) - $failed;
        $this->info("Selesai: {$ok} sukses, {$failed} gagal dari ".count($rows).' tenant.');

        return $failed === 0 ? self::SUCCESS : self::FAILURE;
    }
}
