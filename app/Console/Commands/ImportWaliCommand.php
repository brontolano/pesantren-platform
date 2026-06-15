<?php

namespace App\Console\Commands;

use App\Models\Tenant;
use Illuminate\Console\Command;
use Modules\Kesantrian\Services\WaliImportService;

/**
 * Bulk import akun wali dari data santri yang sudah ada di database.
 *
 * Penggunaan:
 *   php artisan wali:import asy-syifaa
 *   php artisan wali:import asy-syifaa --dry-run       (preview tanpa simpan)
 *   php artisan wali:import asy-syifaa --update-only   (hanya update link, tidak buat user baru)
 */
class ImportWaliCommand extends Command
{
    protected $signature = 'wali:import
        {tenant : ID tenant (misal: asy-syifaa)}
        {--dry-run : Preview akun yang akan dibuat tanpa menyimpan}
        {--update-only : Hanya update wali_user_id di santri, tidak buat akun baru}
        {--send-wa : Kirim notifikasi WA ke wali setelah akun dibuat (butuh WAHA aktif)}';

    protected $description = 'Bulk create akun Portal Wali dari data santri (email_wali + no_hp_wali)';

    public function handle(): int
    {
        $tenantId = (string) $this->argument('tenant');
        $dryRun   = (bool)   $this->option('dry-run');

        $tenant = Tenant::find($tenantId);
        if (! $tenant) {
            $this->error("Tenant '{$tenantId}' tidak ditemukan.");
            return self::FAILURE;
        }

        $mode = $dryRun ? '<fg=yellow>[DRY-RUN]</>' : '<fg=green>[LIVE]</>';
        $this->info("{$mode} Import akun wali — tenant: {$tenant->name}");
        $this->newLine();

        tenancy()->initialize($tenant);

        try {
            $result = app(WaliImportService::class)->generate($tenantId, $dryRun);
        } finally {
            tenancy()->end();
        }

        // Tampilkan ringkasan
        $this->newLine();
        $this->info('Ringkasan:');
        $this->line("  Santri diproses : {$result['total']}");
        $this->line("  Akun baru dibuat: {$result['created']}");
        $this->line("  Sudah ada       : {$result['existing']}");
        $this->line("  Tidak ada email : {$result['no_email']}");
        $this->line('  Error           : '.count($result['errors']));

        if (! $dryRun && $result['csv_path'] !== null) {
            $this->newLine();
            $this->warn('⚠ Kredensial (email + password sementara) tersimpan di: '.$result['csv_path']);
            $this->info('File tersebut perlu dikirim ke wali.');
        }

        return self::SUCCESS;
    }
}
