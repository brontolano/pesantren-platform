<?php

namespace App\Console\Commands;

use App\Models\Tenant;
use App\Services\TenantRecoveryService;
use Illuminate\Console\Command;
use Throwable;

/**
 * HAPUS PERMANEN satu tenant (baris central + database fisik + file).
 *
 * Berbeda dari tenant:reset (mengosongkan & reseed tenant yang TETAP ADA), ini
 * MENGHAPUS tenant sepenuhnya. Backup pengaman (DB + file) dibuat OTOMATIS lebih
 * dulu oleh TenantRecoveryService::purge(). ai_credit_ledger append-only dipurge
 * via GUC terotorisasi di dalam transaksi (lihat migrasi allow_authorized_purge).
 *
 * BERBAHAYA & TAK BISA DIBATALKAN. Tanpa --force wajib mengetik ulang ID tenant.
 * Hanya untuk platform/maintenance window: `php artisan tenant:delete <id> --force`.
 */
class TenantDeleteCommand extends Command
{
    protected $signature = 'tenant:delete
        {tenant : ID/subdomain tenant yang akan DIHAPUS PERMANEN}
        {--force : Lewati konfirmasi ketik (untuk skrip/maintenance window)}';

    protected $description = 'HAPUS PERMANEN satu tenant (DB fisik + file + baris central) — backup pengaman otomatis';

    public function handle(TenantRecoveryService $recovery): int
    {
        $id = (string) $this->argument('tenant');
        $tenant = Tenant::query()->where('id', $id)->first();

        if (! $tenant) {
            $this->error("Tenant '{$id}' tidak ditemukan.");

            return self::FAILURE;
        }

        $this->warn("PERINGATAN: ini MENGHAPUS PERMANEN tenant '{$id}' — database fisik, file, dan seluruh riwayat (termasuk ledger kredit). TAK BISA DIBATALKAN.");
        $this->line('Backup pengaman (DB + file) dibuat otomatis lebih dulu.');

        if (! $this->option('force')) {
            $ketik = (string) $this->ask("Ketik persis ID tenant ('{$id}') untuk melanjutkan");
            if (trim($ketik) !== $id) {
                $this->error('Konfirmasi tidak cocok. Penghapusan dibatalkan.');

                return self::FAILURE;
            }
        }

        try {
            $hasil = $recovery->purge($tenant);
        } catch (Throwable $e) {
            report($e);
            $this->error('Penghapusan GAGAL: '.$e->getMessage());
            $this->line('Backup pengaman mungkin sudah dibuat — cek storage/app/tenant-backups.');

            return self::FAILURE;
        }

        $this->info("Tenant '{$id}' dihapus permanen.");
        $this->line('Backup pengaman DB : '.$hasil['db_backup']);
        $this->line('Backup pengaman file: '.($hasil['files_backup'] ?? '(tak ada file)'));

        return self::SUCCESS;
    }
}
