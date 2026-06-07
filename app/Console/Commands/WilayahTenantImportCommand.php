<?php

namespace App\Console\Commands;

use App\Models\Tenant;
use App\Services\IndonesiaRegionImporter;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Import data wilayah Indonesia ke dalam tenant DB.
 *
 * Memakai ulang App\Services\IndonesiaRegionImporter (sumber & normalisasi
 * SAMA PERSIS dengan central: ihsaninh/wilayah-indonesia, format id 2/4/7/10),
 * lalu menulis hasilnya ke database tenant. Dengan begitu format wilayah di
 * tenant dijamin identik dengan central — penting untuk dropdown bertingkat.
 *
 * Usage:
 *   php artisan wilayah:import-tenant asy-syifaa
 *   php artisan wilayah:import-tenant asy-syifaa --only=provinces,cities
 */
class WilayahTenantImportCommand extends Command
{
    protected $signature = 'wilayah:import-tenant
        {tenant : ID tenant (mis. asy-syifaa)}
        {--only= : Import hanya level tertentu: provinces,cities,districts,villages}';

    protected $description = 'Import data wilayah Indonesia ke dalam database tenant (sumber sama dengan central)';

    /**
     * Mapping level → kolom yang di-update saat upsert (selain id).
     */
    private array $levels = [
        'provinces' => ['name'],
        'cities'    => ['province_id', 'name'],
        'districts' => ['city_id', 'name'],
        'villages'  => ['district_id', 'name'],
    ];

    public function handle(IndonesiaRegionImporter $importer): int
    {
        $tenantId = $this->argument('tenant');

        $tenant = Tenant::find($tenantId);
        if (! $tenant) {
            $this->error("Tenant tidak ditemukan: {$tenantId}");
            return self::FAILURE;
        }

        $onlyLevels = $this->option('only')
            ? array_map('trim', explode(',', $this->option('only')))
            : array_keys($this->levels);

        tenancy()->initialize($tenant);

        try {
            foreach ($onlyLevels as $level) {
                if (! isset($this->levels[$level])) {
                    $this->warn("Level tidak dikenal: {$level} — skip");
                    continue;
                }

                $this->importLevel($importer, $level);
            }

            $this->newLine();
            $this->info("Import wilayah selesai untuk tenant: {$tenantId}");
            $this->table(
                ['Tabel', 'Jumlah Record'],
                collect($onlyLevels)
                    ->filter(fn ($l) => isset($this->levels[$l]))
                    ->map(fn ($l) => [$l, DB::table($l)->count()])
                    ->toArray()
            );
        } finally {
            tenancy()->end();
        }

        return self::SUCCESS;
    }

    private function importLevel(IndonesiaRegionImporter $importer, string $level): void
    {
        $updateColumns = $this->levels[$level];

        $this->info("Importing {$level}...");
        $bar = $this->output->createProgressBar();
        $bar->start();

        $payload = [];
        $total   = 0;

        foreach ($importer->rowsFor($level) as $row) {
            if (empty($row['id'])) {
                continue;
            }

            $payload[] = $row;
            $total++;

            if (count($payload) >= 1000) {
                DB::table($level)->upsert($payload, ['id'], $updateColumns);
                $payload = [];
                $bar->advance(1000);
            }
        }

        if ($payload !== []) {
            DB::table($level)->upsert($payload, ['id'], $updateColumns);
            $bar->advance(count($payload));
        }

        $bar->finish();
        $this->newLine();
        $this->line("  → {$total} record {$level}");
    }
}
