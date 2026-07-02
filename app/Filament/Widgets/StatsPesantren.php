<?php

namespace App\Filament\Widgets;

use App\Models\User;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Modules\Kesantrian\Models\Santri;
use Modules\Keuangan\Models\Pembayaran;
use Modules\Keuangan\Models\Tagihan;
use Modules\Pengadaan\Models\Belanja;

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

    protected int|string|array $columnSpan = 'full';

    protected ?string $heading = null; // heading dibuang — kartu berbicara sendiri

    /**
     * 5 kartu statistik dalam satu baris di xl, 3 di sm, 2 di mobile.
     * Sesuai mockup: Total Santri · Pengurus · Kas Masuk · Kas Keluar · Tagihan.
     */
    protected function getColumns(): int|array
    {
        return [
            'default' => 2,
            'sm' => 3,
            'xl' => 5,
        ];
    }

    public static function canView(): bool
    {
        // Adaptif per-peran: sembunyikan kartu bila pemakai tak punya satu pun
        // dimensi yg relevan (mis. petugas sarpras/payroll murni).
        return auth()->user()?->canAny(['view_santri', 'view_laporan_keuangan']) ?? false;
    }

    protected function getStats(): array
    {
        // ADAPTIF: statistik SANTRI hanya utk pemegang view_santri; statistik
        // KEUANGAN (kas masuk/keluar, tagihan) hanya utk view_laporan_keuangan
        // — tiap peran melihat angka yg relevan fungsinya saja.
        $user = auth()->user();
        $stats = [];

        if ($user?->can('view_santri')) {
            $stats[] = $this->statSantri();
            $stats[] = $this->statPengurus();
        }

        if ($user?->can('view_laporan_keuangan')) {
            $stats[] = $this->statKasMasuk();
            $stats[] = $this->statKasKeluar();
            $stats[] = $this->statTagihan();
        }

        return $stats;
    }

    private function statSantri(): Stat
    {
        $total = 0;
        $baru = 0;

        if (class_exists(Santri::class)) {
            $santri = Santri::query();
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
            $count = User::role(['guru', 'bendahara', 'kepala'])->count();
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

        if (class_exists(Pembayaran::class)) {
            $total = (float) Pembayaran::query()
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
        $total = 0.0;

        if (class_exists(Belanja::class)) {
            // Belanja yang sudah terposting ke buku (punya jurnal) bulan ini.
            $total = (float) Belanja::query()
                ->whereNotNull('jurnal_id')
                ->whereYear('tanggal', now()->year)
                ->whereMonth('tanggal', now()->month)
                ->sum('total');
        }

        return Stat::make('Kas Keluar (Bulan Ini)', 'Rp '.number_format($total, 0, ',', '.'))
            ->description('Belanja terposting')
            ->descriptionIcon('heroicon-m-arrow-up-right')
            ->color($total > 0 ? 'danger' : 'gray');
    }

    private function statTagihan(): Stat
    {
        $count = 0;

        if (class_exists(Tagihan::class)) {
            $count = Tagihan::query()
                ->whereIn('status', ['belum_bayar', 'sebagian'])
                ->count();
        }

        return Stat::make('Tagihan Belum Lunas', number_format($count, 0, ',', '.'))
            ->description('Perlu ditindaklanjuti')
            ->descriptionIcon('heroicon-m-exclamation-triangle')
            ->color($count > 0 ? 'warning' : 'success');
    }
}
