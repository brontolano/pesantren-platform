<?php

namespace App\Filament\Widgets;

use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Modules\Keuangan\Models\Pembayaran;
use Modules\Keuangan\Models\Tagihan;

class KeuanganOverviewWidget extends StatsOverviewWidget
{
    public static function canView(): bool
    {
        return auth()->user()?->canAny(['view_keuangan', 'manage_keuangan']) ?? false;
    }

    protected static ?int $sort = 2;

    protected function getStats(): array
    {
        if (! auth()->user()?->canAny(['view_keuangan', 'manage_keuangan'])) {
            return [];
        }

        $totalTagihan = (float) Tagihan::query()
            ->whereNull('deleted_at')
            ->where('status', '!=', 'batal')
            ->sum('nominal_akhir');

        $totalTerkumpul = (float) Pembayaran::query()
            ->where('is_verified', true)
            ->sum('nominal');

        $jatuhTempo = Tagihan::query()
            ->whereNull('deleted_at')
            ->where('jatuh_tempo', '<=', now())
            ->whereIn('status', ['belum_bayar', 'sebagian'])
            ->count();

        $hariIni = (float) Pembayaran::query()
            ->where('is_verified', true)
            ->where('tanggal_pembayaran', now()->toDateString())
            ->sum('nominal');

        return [
            Stat::make('Total Tagihan Aktif', 'Rp ' . number_format($totalTagihan, 0, ',', '.'))
                ->icon('heroicon-o-banknotes'),
            Stat::make('Total Terkumpul', 'Rp ' . number_format($totalTerkumpul, 0, ',', '.'))
                ->icon('heroicon-o-check-circle')
                ->color('success'),
            Stat::make('Piutang', 'Rp ' . number_format($totalTagihan - $totalTerkumpul, 0, ',', '.'))
                ->icon('heroicon-o-exclamation-triangle')
                ->color($totalTagihan - $totalTerkumpul > 0 ? 'danger' : 'success'),
            Stat::make('Jatuh Tempo', $jatuhTempo . ' tagihan')
                ->icon('heroicon-o-clock')
                ->color($jatuhTempo > 0 ? 'warning' : 'success'),
            Stat::make('Pembayaran Hari Ini', 'Rp ' . number_format($hariIni, 0, ',', '.'))
                ->icon('heroicon-o-currency-dollar'),
        ];
    }
}
