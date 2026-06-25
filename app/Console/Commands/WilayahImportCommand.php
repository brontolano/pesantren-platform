<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class WilayahImportCommand extends Command
{
    protected $signature = 'wilayah:import
        {--path= : Path ke folder berisi CSV (default: database/data/wilayah)}
        {--only= : Import hanya level tertentu: provinces,cities,districts,villages}';

    protected $description = 'Import data wilayah Indonesia dari file CSV (format BPS: id,province_id/city_id/district_id,name)';

    /**
     * Mapping level → tabel + kolom parent.
     */
    private array $levels = [
        'provinces' => ['table' => 'provinces',  'parent_col' => null,          'parent_key' => null],
        'cities' => ['table' => 'cities',     'parent_col' => 'province_id', 'parent_key' => 'id'],
        'districts' => ['table' => 'districts',  'parent_col' => 'city_id',     'parent_key' => 'id'],
        'villages' => ['table' => 'villages',   'parent_col' => 'district_id', 'parent_key' => 'id'],
    ];

    public function handle(): int
    {
        $basePath = $this->option('path') ?: database_path('data/wilayah');

        if (! is_dir($basePath)) {
            $this->error("Folder tidak ditemukan: {$basePath}");
            $this->line('');
            $this->info('Cara pakai:');
            $this->line('1. Buat folder database/data/wilayah/');
            $this->line('2. Taruh file CSV di sana:');
            $this->line('   - provinces.csv  (kolom: id,name)');
            $this->line('   - cities.csv     (kolom: id,province_id,name)');
            $this->line('   - districts.csv  (kolom: id,city_id,name)');
            $this->line('   - villages.csv   (kolom: id,district_id,name)');
            $this->line('3. Jalankan: php artisan wilayah:import');
            $this->line('');
            $this->line('Sumber CSV: https://github.com/cahyadsn/wilayah');

            return Command::FAILURE;
        }

        $onlyLevels = $this->option('only')
            ? explode(',', $this->option('only'))
            : array_keys($this->levels);

        foreach ($onlyLevels as $level) {
            $level = trim($level);
            if (! isset($this->levels[$level])) {
                $this->warn("Level tidak dikenal: {$level} — skip");

                continue;
            }

            $csvFile = "{$basePath}/{$level}.csv";
            if (! file_exists($csvFile)) {
                $this->warn("File tidak ditemukan: {$csvFile} — skip");

                continue;
            }

            $this->importCsv($level, $csvFile);
        }

        $this->newLine();
        $this->info('Import wilayah selesai.');
        $this->table(
            ['Tabel', 'Jumlah Record'],
            collect($onlyLevels)->map(fn ($l) => [
                $l,
                DB::table($this->levels[trim($l)]['table'] ?? $l)->count(),
            ])->toArray()
        );

        return Command::SUCCESS;
    }

    private function importCsv(string $level, string $csvFile): void
    {
        $config = $this->levels[$level];
        $table = $config['table'];

        $handle = fopen($csvFile, 'r');
        if (! $handle) {
            $this->error("Gagal membuka: {$csvFile}");

            return;
        }

        // Read header
        $header = fgetcsv($handle);
        if (! $header) {
            $this->error("CSV kosong: {$csvFile}");
            fclose($handle);

            return;
        }

        // Normalize header
        $header = array_map(fn ($h) => strtolower(trim($h)), $header);

        $rows = [];
        $total = 0;
        $chunkSize = 500;

        $this->info("Importing {$level}...");
        $bar = $this->output->createProgressBar();
        $bar->start();

        while (($line = fgetcsv($handle)) !== false) {
            if (count($line) < 2) {
                continue;
            }

            $row = array_combine($header, array_pad($line, count($header), null));
            if (! $row || empty($row['id'])) {
                continue;
            }

            // Build insert data
            $data = ['id' => trim($row['id']), 'name' => trim($row['name'] ?? '')];

            // Add parent FK if applicable
            if ($config['parent_col'] && isset($row[$config['parent_col']])) {
                $data[$config['parent_col']] = trim($row[$config['parent_col']]);
            }

            $rows[] = $data;
            $total++;

            if (count($rows) >= $chunkSize) {
                DB::table($table)->upsert($rows, ['id'], ['name']);
                $rows = [];
                $bar->advance($chunkSize);
            }
        }

        // Flush remaining
        if (! empty($rows)) {
            DB::table($table)->upsert($rows, ['id'], ['name']);
            $bar->advance(count($rows));
        }

        $bar->finish();
        $this->newLine();
        $this->info("  → {$total} record {$level} diimport.");

        fclose($handle);
    }
}
