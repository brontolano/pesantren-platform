<?php

namespace App\Console\Commands;

use App\Models\Tenant;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Modules\Asrama\Services\AsramaImportService;

/**
 * Import penempatan asrama (blok/kamar dibuat otomatis) — migrasi data
 * Asy-Syifaa dkk. Pola sama dengan santri:import: dry-run default-kan
 * dulu sebelum import nyata, laporan JSON tersimpan.
 */
class AsramaImportCommand extends Command
{
    protected $signature = 'asrama:import
        {tenant : ID tenant, misalnya asy-syifaa}
        {file : Path file .xlsx/.csv/.ods (kolom: nis, blok, kamar, [kapasitas, gender_blok, tanggal_masuk, keterangan])}
        {--dry-run : Validasi dulu tanpa menyimpan ke database}';

    protected $description = 'Import penempatan asrama tenant-aware dari spreadsheet (blok & kamar otomatis dibuat)';

    public function handle(AsramaImportService $importService): int
    {
        $tenantId = (string) $this->argument('tenant');
        $file = (string) $this->argument('file');
        $dryRun = (bool) $this->option('dry-run');

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
            $summary = $importService->import($tenant, $file, $dryRun);
            $reportPath = $this->writeReport($tenantId, $summary);
        } finally {
            tenancy()->end();
        }

        $this->info(($dryRun ? 'DRY-RUN' : 'IMPORT')." penempatan asrama selesai untuk tenant {$tenantId}.");
        $this->table(['Metric', 'Value'], [
            ['Ditempatkan', (string) $summary['imported']],
            ['Dilewati', (string) $summary['skipped']],
            ['Gagal', (string) $summary['failed']],
            ['Blok baru', (string) $summary['created_blok']],
            ['Kamar baru', (string) $summary['created_kamar']],
            ['Laporan', $reportPath],
        ]);

        foreach ($summary['errors'] as $error) {
            $this->line(sprintf('- baris %d | NIS: %s | %s', $error['row'], $error['nis'] ?? '-', $error['error']));
        }

        return $summary['failed'] > 0 ? self::FAILURE : self::SUCCESS;
    }

    private function writeReport(string $tenantId, array $summary): string
    {
        $directory = storage_path('app/imports/asrama');

        if (! is_dir($directory)) {
            File::makeDirectory($directory, 0777, true);
        }

        $path = $directory.DIRECTORY_SEPARATOR.now()->format('Ymd-His-u')."-{$tenantId}-".Str::lower(Str::random(6)).'.json';

        File::put($path, json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        return $path;
    }
}
