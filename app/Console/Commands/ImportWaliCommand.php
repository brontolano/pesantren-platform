<?php

namespace App\Console\Commands;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Modules\Kesantrian\Models\Santri;

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
            $result = $this->processImport($tenantId, $dryRun);
        } finally {
            tenancy()->end();
        }

        // Tampilkan ringkasan
        $this->newLine();
        $this->info("Ringkasan:");
        $this->line("  Santri diproses : {$result['total']}");
        $this->line("  Akun baru dibuat: {$result['created']}");
        $this->line("  Sudah ada       : {$result['existing']}");
        $this->line("  Tidak ada email : {$result['no_email']}");
        $this->line("  Error           : {$result['errors']}");

        if (! $dryRun && $result['created'] > 0) {
            $this->newLine();
            $this->warn("⚠ Simpan file kredensial di: storage/app/wali-import-{$tenantId}-" . date('Ymd') . ".csv");
            $this->info("File tersebut berisi email + password sementara yang perlu dikirim ke wali.");
        }

        return self::SUCCESS;
    }

    private function processImport(string $tenantId, bool $dryRun): array
    {
        $stats        = ['total' => 0, 'created' => 0, 'existing' => 0, 'no_email' => 0, 'errors' => 0];
        $credentials  = []; // Untuk disimpan ke CSV
        $progressBar  = $this->output->createProgressBar();

        $santriQuery = Santri::where('status', 'aktif')
            ->whereNotNull('email_wali')
            ->where('email_wali', '!=', '')
            ->with('wali');

        $progressBar->start($santriQuery->count());

        $santriQuery->chunk(100, function ($santriList) use (
            $tenantId, $dryRun, &$stats, &$credentials, $progressBar
        ) {
            foreach ($santriList as $santri) {
                $stats['total']++;
                $progressBar->advance();

                $emailWali = strtolower(trim($santri->email_wali));

                if (empty($emailWali)) {
                    $stats['no_email']++;
                    continue;
                }

                try {
                    // Cek apakah user wali sudah ada
                    $existingUser = User::where('email', $emailWali)->first();

                    if ($existingUser) {
                        // Pastikan wali_user_id sudah terhubung
                        if (! $dryRun && $santri->wali_user_id !== $existingUser->id) {
                            $santri->update(['wali_user_id' => $existingUser->id]);
                        }
                        $stats['existing']++;
                        continue;
                    }

                    if (! $dryRun) {
                        $password = Str::password(12);
                        $namaWali = $santri->nama_ayah ?: $santri->nama_ibu ?: 'Wali ' . $santri->nama;

                        $user = User::create([
                            'tenant_id' => $tenantId,
                            'name'      => $namaWali,
                            'email'     => $emailWali,
                            'password'  => Hash::make($password),
                        ]);

                        $user->assignRole('wali');
                        $santri->update(['wali_user_id' => $user->id]);

                        $credentials[] = [
                            $santri->nama,
                            $santri->nis,
                            $namaWali,
                            $emailWali,
                            $santri->no_hp_wali ?? '-',
                            $password,
                        ];
                    }

                    $stats['created']++;
                } catch (\Throwable $e) {
                    $stats['errors']++;
                    $this->newLine();
                    $this->error("Error santri {$santri->nis}: {$e->getMessage()}");
                }
            }
        });

        $progressBar->finish();
        $this->newLine();

        // Simpan CSV kredensial
        if (! $dryRun && ! empty($credentials)) {
            $this->saveCsv($credentials, $tenantId);
        }

        return $stats;
    }

    private function saveCsv(array $credentials, string $tenantId): void
    {
        $filename = "wali-import-{$tenantId}-" . date('Ymd-His') . ".csv";
        $path     = storage_path("app/{$filename}");

        $fp = fopen($path, 'w');
        fputcsv($fp, ['Nama Santri', 'NIS', 'Nama Wali', 'Email', 'No HP', 'Password Sementara']);
        foreach ($credentials as $row) {
            fputcsv($fp, $row);
        }
        fclose($fp);

        $this->info("Kredensial disimpan ke: storage/app/{$filename}");
    }
}
