<?php

namespace App\Console\Commands;

use App\Models\Tenant;
use App\Services\TenantRecoveryService;
use Illuminate\Console\Command;
use Throwable;

/**
 * RESET keseluruhan data SATU tenant ke kondisi awal (kosong + fondasi).
 *
 * BERBAHAYA & TAK BISA DIBATALKAN: menghapus seluruh data + file tenant, lalu
 * reseed fondasi (peran, COA, branding, admin baru). Backup pengaman (DB + file)
 * dibuat OTOMATIS lebih dulu oleh TenantRecoveryService::reset().
 *
 * Pakai di maintenance window: `php artisan tenant:reset <id> --force`.
 * Tanpa --force, wajib mengetik ulang ID tenant sebagai konfirmasi.
 */
class TenantResetCommand extends Command
{
    protected $signature = 'tenant:reset
        {tenant : ID/subdomain tenant yang akan di-reset}
        {--admin-email= : Email admin baru (opsional; default ikut provisioner)}
        {--force : Lewati konfirmasi ketik (untuk skrip/maintenance window)}';

    protected $description = 'RESET seluruh data + file satu tenant ke kondisi awal (backup pengaman otomatis)';

    public function handle(TenantRecoveryService $recovery): int
    {
        $id = (string) $this->argument('tenant');
        $tenant = Tenant::query()->where('id', $id)->first();

        if (! $tenant) {
            $this->error("Tenant '{$id}' tidak ditemukan.");

            return self::FAILURE;
        }

        $this->warn("PERINGATAN: ini akan MENGHAPUS seluruh data + file tenant '{$id}' dan mengembalikannya ke kondisi awal.");
        $this->line('Backup pengaman (DB + file) dibuat otomatis lebih dulu.');

        if (! $this->option('force')) {
            $ketik = (string) $this->ask("Ketik persis ID tenant ('{$id}') untuk melanjutkan");
            if (trim($ketik) !== $id) {
                $this->error('Konfirmasi tidak cocok. Reset dibatalkan.');

                return self::FAILURE;
            }
        }

        try {
            $hasil = $recovery->reset($tenant, $this->option('admin-email') ?: null);
        } catch (Throwable $e) {
            report($e);
            $this->error('Reset GAGAL: '.$e->getMessage());
            $this->line('Backup pengaman mungkin sudah dibuat — cek storage/app/tenant-backups.');

            return self::FAILURE;
        }

        $this->info("Reset tenant '{$id}' selesai.");
        $this->line('Backup pengaman DB : '.$hasil['db_backup']);
        $this->line('Backup pengaman file: '.($hasil['files_backup'] ?? '(tak ada file)'));
        if (! empty($hasil['admin_password'])) {
            $this->newLine();
            $this->warn('Admin baru (CATAT SEKARANG — hanya tampil sekali):');
            $this->line('  Email   : '.$hasil['admin_email']);
            $this->line('  Password: '.$hasil['admin_password']);
        }

        return self::SUCCESS;
    }
}
