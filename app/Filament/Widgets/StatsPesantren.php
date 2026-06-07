<?php

namespace App\Filament\Widgets;

use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/**
 * Kartu statistik ringkas dashboard tenant (mengikuti mockup TU Pesantren):
 * Total Santri, Pengurus/Ustadz, Kas Masuk & Keluar bulan ini, Tagihan Belum Lunas.
 *
 * Semua angka dari data NYATA tenant. Modul yang belum ada (kas keluar) ditandai
 * jujur sebagai "belum aktif", bukan angka palsu. Guard class_exists agar aman
 * bila sebuah modul dinonaktifkan.
 */
class StatsPesantren extends StatsOverviewWidget
{
    protected static ?int $sort = -8;

    protected ?string $heading = 'Ringkasan';

    protected function getStats(): array
    {
        return [
            $this->statSantri(),
            $this->statPengurus(),
            $this->statKasMasuk(),
            $this->statKasKeluar(),
            $this->statTagihan(),
        ];
    }

    private function statSantri(): Stat
    {
        $total = 0;
        $baru = 0;

        if (class_exists(\Modules\Kesantrian\Models\Santri::class)) {
            $santri = \Modules\Kesantrian\Models\Santri::query();
            $total = (clone $santri)->where('status', 'aktif')->count();
            $baru = (clone $santri)
                ->whereYear('created_at', now()->year)
                ->whereMonth('created_at', now()->month)
                ->count();
        }

        return Stat::make('Total Santri', number_format($total, 0, ',', '.'))
            ->description($baru > 0 ? "+{$baru} baru bulan ini" : 'Santri aktif')
            ->descriptionIcon($baru > 0 ? 'heroicon-m-arrow-trending-up' : 'heroicon-m-users')
            ->color('success');
    }

    private function statPengurus(): Stat
    {
        $count = 0;

        try {
            $count = \App\Models\User::role(['guru', 'bendahara', 'kepala'])->count();
        } catch (\Throwable $e) {
            $count = 0;
        }

        return Stat::make('Pengurus / Ustadz', number_format($count, 0, ',', '.'))
            ->description('Guru, bendahara, kepala')
            ->descriptionIcon('heroicon-m-academic-cap')
            ->color('info');
    }

    private function statKasMasuk(): Stat
    {
        $total = 0.0;

        if (class_exists(\Modules\Keuangan\Models\Pembayaran::class)) {
            $total = (float) \Modules\Keuangan\Models\Pembayaran::query()
                ->where('is_verified', true)
                ->whereYear('tanggal_pembayaran', now()->year)
                ->whereMonth('tanggal_pembayaran', now()->month)
                ->sum('nominal');
        }

        return Stat::make('Kas Masuk (Bulan Ini)', 'Rp '.number_format($total, 0, ',', '.'))
            ->description('Pembayaran terverifikasi')
            ->descriptionIcon('heroicon-m-arrow-down-left')
            ->color('success');
    }

    private function statKasKeluar(): Stat
    {
        // Modul kas keluar / pengeluaran belum ada (lihat papan PM #9). Jujur: 0.
        return Stat::make('Kas Keluar (Bulan Ini)', 'Rp 0')
            ->description('Modul pengeluaran belum aktif')
            ->descriptionIcon('heroicon-m-arrow-up-right')
            ->color('gray');
    }

    private function statTagihan(): Stat
    {
        $count = 0;

        if (class_exists(\Modules\Keuangan\Models\Tagihan::class)) {
            $count = \Modules\Keuangan\Models\Tagihan::query()
                ->whereIn('status', ['belum_bayar', 'sebagian'])
                ->count();
        }

        return Stat::make('Tagihan Belum Lunas', number_format($count, 0, ',', '.'))
            ->description('Perlu ditindaklanjuti')
            ->descriptionIcon('heroicon-m-exclamation-triangle')
            ->color($count > 0 ? 'warning' : 'success');
    }
}
