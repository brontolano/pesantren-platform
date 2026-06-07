<?php

namespace App\Console\Commands;

use App\Models\Tenant;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Import data wilayah Indonesia (BPS) ke dalam tenant DB.
 * Sumber: CSV dari https://github.com/cahyadsn/wilayah
 *
 * Format CSV per level:
 *   provinces.csv  → id (2 char), name
 *   cities.csv     → id (4 char), province_id (2 char), name
 *   districts.csv  → id (7 char), city_id (4 char), name
 *   villages.csv   → id (10 char), district_id (7 char), name
 *
 * Usage:
 *   php artisan wilayah:import-tenant asy-syifaa
 *   php artisan wilayah:import-tenant asy-syifaa --path=/app/storage/app/wilayah
 */
class WilayahTenantImportCommand extends Command
{
    protected $signature = 'wilayah:import-tenant
        {tenant : ID tenant (mis. asy-syifaa)}
        {--path= : Folder berisi file provinces/cities/districts/villages.csv}';

    protected $description = 'Import data wilayah Indonesia ke dalam database tenant';

    private array $levels = [
        'provinces' => ['table' => 'provinces', 'columns' => ['id', 'name']],
        'cities'    => ['table' => 'cities',    'columns' => ['id', 'province_id', 'name']],
        'districts' => ['table' => 'districts', 'columns' => ['id', 'city_id', 'name']],
        'villages'  => ['table' => 'villages',  'columns' => ['id', 'district_id', 'name']],
    ];

    public function handle(): int
    {
        $tenantId = $this->argument('tenant');
        $basePath = $this->option('path') ?: storage_path('app/wilayah');

        $tenant = Tenant::find($tenantId);
        if (! $tenant) {
            $this->error("Tenant tidak ditemukan: {$tenantId}");
            return self::FAILURE;
        }

        if (! is_dir($basePath)) {
            $this->error("Folder tidak ditemukan: {$basePath}");
            $this->line('Isi folder dengan: provinces.csv, cities.csv, districts.csv, villages.csv');
            return self::FAILURE;
        }

        tenancy()->initialize($tenant);

        try {
            foreach ($this->levels as $level => $config) {
                $csv = "{$basePath}/{$level}.csv";

                if (! file_exists($csv)) {
                    $this->warn("  File tidak ditemukan: {$csv} — skip");
                    continue;
                }

                $this->importCsv($level, $csv, $config);
            }
        } finally {
            tenancy()->end();
        }

        $this->newLine();
        $this->info("Import wilayah selesai untuk tenant: {$tenantId}");

        return self::SUCCESS;
    }

    private function importCsv(string $level, string $csvFile, array $config): void
    {
        $table   = $config['table'];
        $columns = $config['columns'];

        $handle = fopen($csvFile, 'r');
        if (! $handle) {
            $this->error("Gagal membuka: {$csvFile}");
            return;
        }

        // Baca dan normalisasi header
        $header = array_map(fn($h) => strtolower(trim($h)), fgetcsv($handle) ?? []);

        $rows      = [];
        $total     = 0;
        $chunkSize = 500;

        $this->info("Importing {$level}...");
        $bar = $this->output->createProgressBar();
        $bar->start();

        while (($line = fgetcsv($handle)) !== false) {
            if (count($line) < 2) continue;

            $row = array_combine($header, array_pad($line, count($header), null));
            if (! $row || empty($row['id'])) continue;

            $data = [];
            foreach ($columns as $col) {
                $data[$col] = trim($row[$col] ?? '');
            }

            $rows[] = $data;
            $total++;

            if (count($rows) >= $chunkSize) {
                DB::table($table)->upsert($rows, ['id'], array_diff($columns, ['id']));
                $rows = [];
                $bar->advance($chunkSize);
            }
        }

        if (! empty($rows)) {
            DB::table($table)->upsert($rows, ['id'], array_diff($columns, ['id']));
            $bar->advance(count($rows));
        }

        $bar->finish();
        $this->newLine();
        $this->line("  → {$total} record {$level}");

        fclose($handle);
    }
}
