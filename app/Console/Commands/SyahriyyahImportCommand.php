<?php

namespace App\Console\Commands;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Modules\Keuangan\Services\SyahriyyahImportService;

/**
 * Import "LAPORAN KEUANGAN SYAHRIYYAH" Asy-Syifaa (multi-sheet per bulan,
 * cicilan, kolom CASH/BSI/BCA/LAINNYA). Urutan pakai:
 *   1) --parse-only  : uji baca file (boleh di mana saja, tanpa tenant)
 *   2) --dry-run     : validasi NIS dkk. di server, tanpa tulis
 *   3) run nyata     : wajib --verifikator=email
 */
class SyahriyyahImportCommand extends Command
{
    protected $signature = 'keuangan:import-syahriyyah
        {file : Path file LAPORAN KEUANGAN SYAHRIYYAH .xlsx}
        {tenant? : ID tenant (wajib kecuali --parse-only)}
        {--verifikator= : Email user tenant yang tercatat memverifikasi pembayaran}
        {--nominal=700000 : Nominal standar tagihan Syahriyyah per bulan}
        {--dry-run : Validasi tanpa menyimpan}
        {--parse-only : Hanya uji-baca struktur file (tanpa tenant/DB)}';

    protected $description = 'Import pembayaran syahriyyah Asy-Syifaa (multi-sheet, cicilan) via service uang resmi';

    public function handle(SyahriyyahImportService $importService): int
    {
        $file = (string) $this->argument('file');
        $tenantId = $this->argument('tenant');
        $parseOnly = (bool) $this->option('parse-only');
        $dryRun = (bool) $this->option('dry-run');
        $nominal = (float) $this->option('nominal');

        if (! is_file($file)) {
            $this->error("File tidak ditemukan: {$file}");

            return self::FAILURE;
        }

        $tenant = null;
        $verifikatorId = null;

        if (! $parseOnly) {
            $tenant = Tenant::query()->find((string) $tenantId);
            if (! $tenant) {
                $this->error("Tenant '{$tenantId}' tidak ditemukan (atau pakai --parse-only).");

                return self::FAILURE;
            }

            tenancy()->initialize($tenant);

            $email = $this->option('verifikator');
            if (is_string($email) && $email !== '') {
                $verifikatorId = User::query()->where('email', $email)->value('id');
                if ($verifikatorId === null) {
                    $this->error("Verifikator '{$email}' tidak ditemukan pada tenant ini.");
                    tenancy()->end();

                    return self::FAILURE;
                }
            }
        }

        try {
            $summary = $importService->import($tenant, $file, $verifikatorId, $nominal, $dryRun, $parseOnly);
            $reportPath = $this->writeReport($summary);
        } finally {
            if (tenancy()->initialized) {
                tenancy()->end();
            }
        }

        $this->info(strtoupper($summary['mode']).' syahriyyah selesai.');
        $this->table(['Metric', 'Value'], [
            ['Sheet terbaca', (string) $summary['sheets']],
            ['Baris transaksi', (string) $summary['baris_dibaca']],
            ['Pembayaran masuk', (string) $summary['pembayaran_masuk']],
            ['Tagihan dibuat', (string) $summary['tagihan_dibuat']],
            ['Dilewati (idempoten)', (string) $summary['skipped']],
            ['Gagal', (string) $summary['failed']],
            ['Total terbayar', 'Rp '.number_format($summary['total_terbayar'], 0, ',', '.')],
            ['Laporan', $reportPath],
        ]);

        foreach (array_slice($summary['errors'], 0, 25) as $error) {
            $this->line(sprintf('- [%s] baris %d | NIS: %s | %s', $error['sheet'], $error['row'], $error['nis'] ?? '-', $error['error']));
        }
        if (count($summary['errors']) > 25) {
            $this->warn('... '.(count($summary['errors']) - 25).' error lain di laporan JSON.');
        }

        return $summary['failed'] > 0 ? self::FAILURE : self::SUCCESS;
    }

    private function writeReport(array $summary): string
    {
        $directory = storage_path('app/imports/syahriyyah');

        if (! is_dir($directory)) {
            File::makeDirectory($directory, 0777, true);
        }

        $path = $directory.DIRECTORY_SEPARATOR.now()->format('Ymd-His-u').'-'.Str::lower(Str::random(6)).'.json';

        File::put($path, json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        return $path;
    }
}
