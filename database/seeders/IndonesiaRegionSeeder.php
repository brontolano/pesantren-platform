<?php

namespace Database\Seeders;

use App\Services\IndonesiaRegionImporter;
use Illuminate\Database\Seeder;

class IndonesiaRegionSeeder extends Seeder
{
    public function run(): void
    {
        $counts = app(IndonesiaRegionImporter::class)->import();

        if ($this->command !== null) {
            $this->command->info('Import wilayah Indonesia selesai.');

            foreach ($counts as $level => $count) {
                $this->command->line(sprintf('- %s: %s baris', $level, number_format($count)));
            }
        }
    }
}
