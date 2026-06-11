<?php

namespace App\Filament\Widgets;

use App\Support\PanelTheme;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Modules\Keuangan\Models\Pembayaran;
use Modules\Pengadaan\Models\Belanja;

/**
 * Kas Overview (tahun berjalan) — bagian dari penekanan KEUANGAN tema
 * Baitul Maal (referensi desain TU Pesantren). Hanya tampil saat tema
 * baitul-maal aktif; tema lain tidak berubah.
 */
class KasOverviewWidget extends StatsOverviewWidget
{
    protected static ?int $sort = -4;

    protected int|string|array $columnSpan = 2;

    protected ?string $heading = 'Kas Overview';

    protected ?string $description = 'Tahun berjalan';

    public static function canView(): bool
    {
        return PanelTheme::theme() === 'baitul-maal';
    }

    protected function getColumns(): int|array
    {
        return 1; // vertikal, seperti kartu Kas Overview referensi
    }

    protected function getStats(): array
    {
        $masuk = 0.0;
        if (class_exists(Pembayaran::class)) {
            $masuk = (float) Pembayaran::query()
                ->where('is_verified', true)
                ->whereYear('tanggal_pembayaran', now()->year)
                ->sum('nominal');
        }

        $keluar = 0.0;
        if (class_exists(Belanja::class)) {
            // Hanya belanja yang sudah terposting ke buku (punya jurnal).
            $keluar = (float) Belanja::query()
                ->whereNotNull('jurnal_id')
                ->whereYear('tanggal', now()->year)
                ->sum('total');
        }

        $rp = fn (float $n): string => 'Rp '.number_format($n, 0, ',', '.');

        return [
            Stat::make('Total Pemasukan', $rp($masuk))
                ->description('Pembayaran terverifikasi')
                ->descriptionIcon('heroicon-m-arrow-trending-up')
                ->color('success'),
            Stat::make('Total Pengeluaran', $rp($keluar))
                ->description('Belanja terposting')
                ->descriptionIcon('heroicon-m-arrow-trending-down')
                ->color('danger'),
            Stat::make('Saldo Akhir (estimasi)', $rp($masuk - $keluar))
                ->description('Pemasukan − pengeluaran tahun ini')
                ->descriptionIcon('heroicon-m-banknotes')
                ->color($masuk - $keluar >= 0 ? 'success' : 'danger'),
        ];
    }
}
