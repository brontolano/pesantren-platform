<?php

namespace App\Filament\Widgets;

use Filament\Widgets\ChartWidget;

/**
 * Grafik donat "Kategori Pengeluaran" (mockup). Modul Kategori Pengeluaran / kas
 * keluar BELUM ada (papan PM #9) → tampil state kosong jujur (cincin abu
 * "Belum ada data"), bukan angka palsu. Saat modul dibuat, getData() diisi
 * agregasi nominal per kategori.
 */
class KategoriPengeluaranChart extends ChartWidget
{
    protected ?string $heading = 'Kategori Pengeluaran';

    protected ?string $description = 'Modul pengeluaran belum aktif';

    protected static ?int $sort = -5;

    protected int|string|array $columnSpan = 2;

    protected ?string $maxHeight = '280px';

    protected function getType(): string
    {
        return 'doughnut';
    }

    protected function getData(): array
    {
        // Belum ada model kategori pengeluaran → placeholder kosong (cincin abu).
        return [
            'datasets' => [
                [
                    'data' => [1],
                    'backgroundColor' => ['#e5e7eb'],
                    'borderWidth' => 0,
                ],
            ],
            'labels' => ['Belum ada data'],
        ];
    }

    protected function getOptions(): array
    {
        return [
            'cutout' => '65%',
            'plugins' => [
                'legend' => [
                    'display' => true,
                    'position' => 'bottom',
                ],
                'tooltip' => ['enabled' => false],
            ],
            'maintainAspectRatio' => false,
        ];
    }
}
