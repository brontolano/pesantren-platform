<?php

namespace App\Console\Commands;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Modules\Keuangan\Services\KeuanganImportService;

/**
 * Import tagihan historis (+ pelunasan terverifikasi) — migrasi data.
 * UANG: semua lewat TagihanService/PembayaranService; dry-run dulu!
 */
class KeuanganImportCommand extends Command
{
    protected $signature = 'keuangan:import-tagihan
        {tenant : ID tenant, misalnya asy-syifaa}
        {file : Path file .xlsx/.csv/.ods (kolom: nis, jenis_tagihan, nominal, jatuh_tempo, [diskon, bulan, status_bayar, tanggal_pembayaran, metode, nomor_bukti])}
        {--verifikator= : Email user tenant yang tercatat memverifikasi pelunasan historis}
        {--dry-run : Validasi dulu tanpa menyimpan ke database}';

    protected $description = 'Import tagihan historis + pelunasan tenant-aware dari spreadsheet (idempoten, via service uang resmi)';

    public function handle(KeuanganImportService $importService): int
    {
        $tenantId = (string) $this->argument('tenant');
        $file = (string) $this->argument('file');
        $dryRun = (bool) $this->option('dry-run');
        $verifikatorEmail = $this->option('verifikator');

        $tenant = Tenant::query()->find($tenantId);

        if (! $tenant) {
            $this->error("Tenant '{$tenantId}' tidak ditemukan.");

            return self::FAILURE;
        }

        if (! is_file($file)) {
            $this->error("File tidak ditemukan: {$file}");

            return self::FAILURE;
        }

        tenancy()->initialize($tenant);

        try {
            $verifikatorId = null;
            if (is_string($verifikatorEmail) && $verifikatorEmail !== '') {
                $verifikatorId = User::query()->where('email', $verifikatorEmail)->value('id');

                if ($verifikatorId === null) {
                    $this->error("Verifikator '{$verifikatorEmail}' tidak ditemukan pada tenant ini.");

                    return self::FAILURE;
                }
            }

            $summary = $importService->import($tenant, $file, $verifikatorId, $dryRun);
            $reportPath = $this->writeReport($tenantId, $summary);
        } finally {
            tenancy()->end();
        }

        $this->info(($dryRun ? 'DRY-RUN' : 'IMPORT')." tagihan selesai untuk tenant {$tenantId}.");
        $this->table(['Metric', 'Value'], [
            ['Tagihan dibuat', (string) $summary['tagihan_dibuat']],
            ['Pelunasan diverifikasi', (string) $summary['pembayaran_diverifikasi']],
            ['Dilewati (idempoten/duplikat)', (string) $summary['skipped']],
            ['Gagal', (string) $summary['failed']],
            ['Total tagihan (akhir)', 'Rp '.number_format($summary['total_tagihan'], 0, ',', '.')],
            ['Total terbayar', 'Rp '.number_format($summary['total_terbayar'], 0, ',', '.')],
            ['Laporan', $reportPath],
        ]);

        foreach ($summary['errors'] as $error) {
            $this->line(sprintf('- baris %d | NIS: %s | %s', $error['row'], $error['nis'] ?? '-', $error['error']));
        }

        return $summary['failed'] > 0 ? self::FAILURE : self::SUCCESS;
    }

    private function writeReport(string $tenantId, array $summary): string
    {
        $directory = storage_path('app/imports/keuangan');

        if (! is_dir($directory)) {
            File::makeDirectory($directory, 0777, true);
        }

        $path = $directory.DIRECTORY_SEPARATOR.now()->format('Ymd-His-u')."-{$tenantId}-".Str::lower(Str::random(6)).'.json';

        File::put($path, json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        return $path;
    }
}
