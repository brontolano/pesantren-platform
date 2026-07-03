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
        $cuti = 0;

        if (class_exists(Santri::class)) {
            $santri = Santri::query();
            $total = (clone $santri)->where('status', 'aktif')->count();
            $cuti = (clone $santri)->where('status', 'cuti')->count();
            $baru = (clone $santri)
                ->whereYear('created_at', now()->year)
                ->whereMonth('created_at', now()->month)
                ->count();
        }

        // Serapan dari KesantrianOverviewWidget (konsolidasi dasbor):
        // info cuti ikut tampil di deskripsi agar tak hilang saat baris
        // ringkasan domain dicabut dari dasbor utama.
        $deskripsi = $baru > 0 ? "+{$baru} baru bulan ini" : 'Santri aktif';
        if ($cuti > 0) {
            $deskripsi .= " · {$cuti} cuti";
        }

        return Stat::make('Total Santri', number_format($total, 0, ',', '.'))
            ->description($deskripsi)
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
        $menungguVerifikasi = 0.0;

        if (class_exists(Pembayaran::class)) {
            $bulanIni = Pembayaran::query()
                ->whereYear('tanggal_pembayaran', now()->year)
                ->whereMonth('tanggal_pembayaran', now()->month);

            $total = (float) (clone $bulanIni)->where('is_verified', true)->sum('nominal');
            $menungguVerifikasi = (float) (clone $bulanIni)->where('is_verified', false)->sum('nominal');
        }

        // JUJUR saat Rp 0 tapi ada data mentah: angka utama tetap definisi
        // ketat (terverifikasi), deskripsi menjelaskan pembayaran yang masih
        // antre verifikasi agar tak terbaca "tidak ada pemasukan".
        $deskripsi = 'Pembayaran terverifikasi';
        if ($total <= 0 && $menungguVerifikasi > 0) {
            $deskripsi = 'Rp '.number_format($menungguVerifikasi, 0, ',', '.').' menunggu verifikasi';
        }

        return Stat::make('Kas Masuk (Bulan Ini)', 'Rp '.number_format($total, 0, ',', '.'))
            ->description($deskripsi)
            ->descriptionIcon('heroicon-m-arrow-down-left')
            ->color('success');
    }

    private function statKasKeluar(): Stat
    {
        $total = 0.0;
        $belumTerposting = 0.0;

        if (class_exists(Belanja::class)) {
            $bulanIni = Belanja::query()
                ->whereYear('tanggal', now()->year)
                ->whereMonth('tanggal', now()->month);

            // Belanja yang sudah terposting ke buku (punya jurnal) bulan ini.
            $total = (float) (clone $bulanIni)->whereNotNull('jurnal_id')->sum('total');
            $belumTerposting = (float) (clone $bulanIni)->whereNull('jurnal_id')->sum('total');
        }

        // JUJUR utk tenant yang tak memakai posting jurnal: angka utama tetap
        // definisi ketat (terposting), deskripsi menampilkan total belanja
        // bulan ini tanpa gerbang jurnal berlabel "(belum terposting)".
        $deskripsi = 'Belanja terposting';
        if ($total <= 0 && $belumTerposting > 0) {
            $deskripsi = 'Rp '.number_format($belumTerposting, 0, ',', '.').' (belum terposting)';
        }

        return Stat::make('Kas Keluar (Bulan Ini)', 'Rp '.number_format($total, 0, ',', '.'))
            ->description($deskripsi)
            ->descriptionIcon('heroicon-m-arrow-up-right')
            ->color($total > 0 ? 'danger' : 'gray');
    }

    private function statTagihan(): Stat
    {
        $count = 0;
        $sisaNominal = 0.0;

        if (class_exists(Tagihan::class)) {
            // SATU definisi dgn KeuanganOverviewWidget ("Jatuh Tempo"):
            // tagihan belum lunas YANG sudah lewat jatuh tempo — bukan semua
            // tagihan belum lunas (tagihan belum jatuh tempo itu normal).
            $lewat = Tagihan::query()
                ->whereIn('status', ['belum_bayar', 'sebagian'])
                ->where('jatuh_tempo', '<=', now());

            $count = (clone $lewat)->count();

            // Total nominal SISA (nominal akhir dikurangi pembayaran
            // terverifikasi) dari tagihan yang lewat jatuh tempo tsb.
            $nominalAkhir = (float) (clone $lewat)->sum('nominal_akhir');
            $terbayar = 0.0;

            if (class_exists(Pembayaran::class)) {
                $terbayar = (float) Pembayaran::query()
                    ->where('is_verified', true)
                    ->whereIn('tagihan_id', (clone $lewat)->select('id'))
                    ->sum('nominal');
            }

            $sisaNominal = max(0.0, $nominalAkhir - $terbayar);
        }

        return Stat::make('Tagihan Lewat Jatuh Tempo', number_format($count, 0, ',', '.'))
            ->description($count > 0
                ? 'Sisa Rp '.number_format($sisaNominal, 0, ',', '.').' perlu ditindaklanjuti'
                : 'Tidak ada tunggakan')
            ->descriptionIcon('heroicon-m-exclamation-triangle')
            ->color($count > 0 ? 'warning' : 'success');
    }
}
