<?php

namespace App\Filament\Widgets;

use Filament\Widgets\ChartWidget;
use Modules\Keuangan\Models\Pembayaran;

/**
 * Grafik area "Ringkasan Keuangan" (mockup): Pemasukan vs Pengeluaran per hari
 * bulan berjalan. Pemasukan = Pembayaran terverifikasi. Pengeluaran = 0 sampai
 * modul kas keluar ada (papan PM #9) — ditampilkan jujur sebagai garis nol.
 */
class KeuanganAreaChart extends ChartWidget
{
    protected ?string $heading = 'Ringkasan Keuangan';

    protected ?string $description = 'Pemasukan vs pengeluaran bulan ini';

    protected static ?int $sort = -6;

    protected int|string|array $columnSpan = 4;

    protected ?string $maxHeight = '280px';

    protected function getType(): string
    {
        return 'line';
    }

    protected function getData(): array
    {
        $year = now()->year;
        $month = now()->month;
        $days = now()->daysInMonth;

        $income = array_fill(1, $days, 0.0);

        if (class_exists(Pembayaran::class)) {
            $rows = Pembayaran::query()
                ->where('is_verified', true)
                ->whereYear('tanggal_pembayaran', $year)
                ->whereMonth('tanggal_pembayaran', $month)
                ->get(['tanggal_pembayaran', 'nominal']);

            foreach ($rows as $row) {
                if ($row->tanggal_pembayaran === null) {
                    continue;
                }
                $d = (int) $row->tanggal_pembayaran->format('j');
                $income[$d] = ($income[$d] ?? 0) + (float) $row->nominal;
            }
        }

        $labels = [];
        $pemasukan = [];
        $pengeluaran = [];
        for ($d = 1; $d <= $days; $d++) {
            $labels[] = (string) $d;
            $pemasukan[] = round($income[$d] ?? 0, 2);
            $pengeluaran[] = 0;
        }

        return [
            'datasets' => [
                [
                    'label' => 'Pemasukan',
                    'data' => $pemasukan,
                    'borderColor' => '#16a34a',
                    'backgroundColor' => 'rgba(22, 163, 74, 0.12)',
                    'fill' => true,
                    'tension' => 0.35,
                    'pointRadius' => 0,
                    'pointHoverRadius' => 4,
                ],
                [
                    'label' => 'Pengeluaran',
                    'data' => $pengeluaran,
                    'borderColor' => '#ef4444',
                    'backgroundColor' => 'rgba(239, 68, 68, 0.10)',
                    'fill' => true,
                    'tension' => 0.35,
                    'pointRadius' => 0,
                    'pointHoverRadius' => 4,
                ],
            ],
            'labels' => $labels,
        ];
    }

    protected function getOptions(): array
    {
        return [
            'plugins' => [
                'legend' => [
                    'display' => true,
                    'position' => 'top',
                    'align' => 'end',
                ],
            ],
            'scales' => [
                'y' => [
                    'beginAtZero' => true,
                    'ticks' => ['maxTicksLimit' => 5],
                ],
                'x' => [
                    'grid' => ['display' => false],
                ],
            ],
            'maintainAspectRatio' => false,
        ];
    }
}
