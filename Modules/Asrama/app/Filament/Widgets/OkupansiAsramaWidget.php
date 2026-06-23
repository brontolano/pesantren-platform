<?php

namespace Modules\Asrama\Filament\Widgets;

use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Modules\Asrama\Services\AsramaService;

/**
 * Ringkasan okupansi asrama (gap 102) — menampilkan AsramaService::getOccupancySummary()
 * yang sebelumnya dihitung tapi tak punya UI. Ditampilkan di header daftar Kamar.
 */
class OkupansiAsramaWidget extends StatsOverviewWidget
{
    protected function getStats(): array
    {
        $s = app(AsramaService::class)->getOccupancySummary();
        $kapasitas = (int) $s['occupiedBeds'] + (int) $s['availableBeds'];
        $persen = $kapasitas > 0 ? (int) round($s['occupiedBeds'] / $kapasitas * 100) : 0;

        return [
            Stat::make('Total Kamar', (string) $s['totalKamar'])
                ->icon('heroicon-o-home-modern'),
            Stat::make('Tempat Terisi', (string) $s['occupiedBeds'])
                ->description("{$persen}% okupansi dari {$kapasitas} tempat")
                ->color($persen >= 90 ? 'danger' : ($persen >= 70 ? 'warning' : 'success')),
            Stat::make('Tempat Tersedia', (string) $s['availableBeds'])
                ->color('success'),
        ];
    }
}
