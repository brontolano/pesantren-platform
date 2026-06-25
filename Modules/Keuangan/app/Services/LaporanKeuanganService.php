<?php

namespace Modules\Keuangan\Services;

use Modules\Keuangan\Models\Pembayaran;
use Modules\Keuangan\Models\Tagihan;
use RuntimeException;

class LaporanKeuanganService
{
    /**
     * Laporan harian: pembayaran yang masuk hari ini.
     */
    public function laporanHarian(?string $tanggal = null): array
    {
        $this->ensureTenantContext();
        $tanggal = $tanggal ?? now()->toDateString();

        $pembayaran = Pembayaran::query()
            ->where('tanggal_pembayaran', $tanggal)
            ->where('is_verified', true)
            ->get();

        $grouped = $pembayaran->groupBy(fn ($p) => $p->tagihan?->jenis_tagihan ?? 'lainnya');

        $perJenis = $grouped->map(fn ($items, $jenis) => [
            'jenis' => $jenis,
            'jumlah_transaksi' => $items->count(),
            'total' => (float) $items->sum('nominal'),
        ])->values()->all();

        return [
            'tanggal' => $tanggal,
            'total_pembayaran' => (float) $pembayaran->sum('nominal'),
            'jumlah_transaksi' => $pembayaran->count(),
            'per_jenis_tagihan' => $perJenis,
        ];
    }

    /**
     * Laporan mingguan: 7 hari terakhir.
     */
    public function laporanMingguan(?string $sampaiTanggal = null): array
    {
        $this->ensureTenantContext();
        $akhir = $sampaiTanggal ? new \DateTimeImmutable($sampaiTanggal) : now();
        $awal = now()->parse($akhir)->subDays(6)->toDateString();

        $perHari = Pembayaran::query()
            ->where('is_verified', true)
            ->whereBetween('tanggal_pembayaran', [$awal, $akhir instanceof \DateTimeInterface ? $akhir->format('Y-m-d') : $akhir])
            ->selectRaw('tanggal_pembayaran, COUNT(*) as jumlah, SUM(nominal) as total')
            ->groupBy('tanggal_pembayaran')
            ->orderBy('tanggal_pembayaran')
            ->get();

        return [
            'dari' => $awal,
            'sampai' => $akhir instanceof \DateTimeInterface ? $akhir->format('Y-m-d') : $akhir,
            'total' => (float) $perHari->sum('total'),
            'jumlah_transaksi' => (int) $perHari->sum('jumlah'),
            'per_hari' => $perHari->map(fn ($row) => [
                'tanggal' => $row->tanggal_pembayaran instanceof \DateTimeInterface
                    ? $row->tanggal_pembayaran->format('Y-m-d')
                    : (string) $row->tanggal_pembayaran,
                'jumlah' => (int) $row->jumlah,
                'total' => (float) $row->total,
            ])->all(),
        ];
    }

    /**
     * Laporan bulanan: summary satu bulan.
     */
    public function laporanBulanan(string $bulanMasehi): array
    {
        $this->ensureTenantContext();

        // bulanMasehi format: 2026-07
        $awal = $bulanMasehi.'-01';
        $akhir = now()->parse($awal)->endOfMonth()->toDateString();

        $totalTagihan = (float) Tagihan::query()
            ->whereNull('deleted_at')
            ->where('bulan_masehi', $bulanMasehi)
            ->sum('nominal_akhir');

        $totalTerkumpul = (float) Pembayaran::query()
            ->where('is_verified', true)
            ->whereBetween('tanggal_pembayaran', [$awal, $akhir])
            ->sum('nominal');

        $tagBelumLunas = Tagihan::query()
            ->whereNull('deleted_at')
            ->where('bulan_masehi', $bulanMasehi)
            ->whereIn('status', ['belum_bayar', 'sebagian'])
            ->count();

        $tagLunas = Tagihan::query()
            ->whereNull('deleted_at')
            ->where('bulan_masehi', $bulanMasehi)
            ->where('status', 'lunas')
            ->count();

        return [
            'bulan' => $bulanMasehi,
            'total_tagihan' => $totalTagihan,
            'total_terkumpul' => $totalTerkumpul,
            'sisa_piutang' => $totalTagihan - $totalTerkumpul,
            'persentase_terkumpul' => $totalTagihan > 0
                ? round(($totalTerkumpul / $totalTagihan) * 100, 2)
                : 0,
            'jumlah_lunas' => $tagLunas,
            'jumlah_belum_lunas' => $tagBelumLunas,
        ];
    }

    /**
     * Summary keseluruhan keuangan tenant (overview).
     */
    public function summaryOverview(): array
    {
        $this->ensureTenantContext();

        $totalTagihanAktif = (float) Tagihan::query()
            ->whereNull('deleted_at')
            ->where('status', '!=', 'batal')
            ->sum('nominal_akhir');

        $totalTerkumpul = (float) Pembayaran::query()
            ->where('is_verified', true)
            ->sum('nominal');

        $jatuhTempoCount = Tagihan::query()
            ->whereNull('deleted_at')
            ->where('jatuh_tempo', '<=', now())
            ->whereIn('status', ['belum_bayar', 'sebagian'])
            ->count();

        $hariIniTotal = (float) Pembayaran::query()
            ->where('is_verified', true)
            ->where('tanggal_pembayaran', now()->toDateString())
            ->sum('nominal');

        return [
            'total_tagihan_aktif' => $totalTagihanAktif,
            'total_terkumpul' => $totalTerkumpul,
            'total_piutang' => $totalTagihanAktif - $totalTerkumpul,
            'tagihan_jatuh_tempo' => $jatuhTempoCount,
            'pembayaran_hari_ini' => $hariIniTotal,
        ];
    }

    private function ensureTenantContext(): void
    {
        if (! tenancy()->initialized || tenancy()->tenant === null) {
            throw new RuntimeException('Tenant context belum aktif.');
        }
    }
}
