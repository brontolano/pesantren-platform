<?php

namespace App\Console\Commands;

use App\Models\Tenant;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Modules\Kesantrian\Services\SantriImportService;

class SantriImportCommand extends Command
{
    protected $signature = 'santri:import
        {tenant : ID tenant, misalnya asy-syifaa}
        {file : Path file .xlsx/.csv/.ods sumber data santri}
        {--dry-run : Validasi dulu tanpa menyimpan ke database}';

    protected $description = 'Import data santri tenant-aware dari file spreadsheet ke database baru';

    public function handle(SantriImportService $importService): int
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

        $modeLabel = $dryRun ? 'DRY-RUN' : 'IMPORT';

        $this->info("{$modeLabel} selesai untuk tenant {$tenantId}.");
        $this->table(
            ['Metric', 'Value'],
            [
                ['Imported', (string) $summary['imported']],
                ['Skipped', (string) $summary['skipped']],
                ['Failed', (string) $summary['failed']],
                ['Created Kelas', (string) $summary['created_kelas']],
                ['Report', $reportPath],
            ]
        );

        if ($summary['errors'] !== []) {
            $this->warn('Baris gagal / skip:');

            foreach ($summary['errors'] as $error) {
                $this->line(sprintf(
                    '- baris %d | NIS: %s | Nama: %s | %s',
                    $error['row'],
                    $error['nis'] ?? '-',
                    $error['nama'] ?? '-',
                    $error['error']
                ));
            }
        }

        return $summary['failed'] > 0 ? self::FAILURE : self::SUCCESS;
    }

    /**
     * @param  array{
     *   tenant_id:string,
     *   source:string,
     *   imported:int,
     *   skipped:int,
     *   failed:int,
     *   errors:list<array{row:int,nis:?string,nama:?string,error:string}>,
     *   created_kelas:int
     * }  $summary
     */
    private function writeReport(string $tenantId, array $summary): string
    {
        $directory = storage_path('app/imports/santri');

        if (! is_dir($directory)) {
            File::makeDirectory($directory, 0777, true);
        }

        $path = $directory.DIRECTORY_SEPARATOR.now()->format('Ymd-His-u')."-{$tenantId}-".Str::lower(Str::random(6)).'.json';

        File::put($path, json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        return $path;
    }
}
