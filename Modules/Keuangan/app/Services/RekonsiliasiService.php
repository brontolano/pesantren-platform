<?php

namespace Modules\Keuangan\Services;

use Illuminate\Support\Facades\DB;
use Modules\Keuangan\Models\Pembayaran;
use Modules\Keuangan\Models\Tagihan;
use RuntimeException;

class RekonsiliasiService
{
    /**
     * Rekonsiliasi: cocokan total tagihan vs total pembayaran terverifikasi.
     * Mengembalikan summary per status + selisih.
     */
    public function rekonsiliasiPeriode(
        ?string $periodeAkademik = null,
        ?string $bulanMasehi = null,
    ): array {
        $this->ensureTenantContext();

        $queryTagihan = Tagihan::query()->whereNull('deleted_at');
        if ($periodeAkademik) {
            $queryTagihan->where('periode_akademik', $periodeAkademik);
        }
        if ($bulanMasehi) {
            $queryTagihan->where('bulan_masehi', $bulanMasehi);
        }

        $totalTagihan = (float) $queryTagihan->sum('nominal_akhir');
        $totalBelumBayar = (float) (clone $queryTagihan)->where('status', 'belum_bayar')->sum('nominal_akhir');
        $totalSebagian = (float) (clone $queryTagihan)->where('status', 'sebagian')->sum('nominal_akhir');
        $totalLunas = (float) (clone $queryTagihan)->where('status', 'lunas')->sum('nominal_akhir');

        $tagihanIds = (clone $queryTagihan)->pluck('id');

        $totalTerkumpul = (float) Pembayaran::query()
            ->whereIn('tagihan_id', $tagihanIds)
            ->where('is_verified', true)
            ->sum('nominal');

        $totalBelumVerifikasi = (float) Pembayaran::query()
            ->whereIn('tagihan_id', $tagihanIds)
            ->where('is_verified', false)
            ->sum('nominal');

        return [
            'filter' => [
                'periode_akademik' => $periodeAkademik,
                'bulan_masehi' => $bulanMasehi,
            ],
            'total_tagihan' => $totalTagihan,
            'total_terkumpul_verified' => $totalTerkumpul,
            'total_belum_verifikasi' => $totalBelumVerifikasi,
            'sisa_piutang' => $totalTagihan - $totalTerkumpul,
            'persentase_terkumpul' => $totalTagihan > 0
                ? round(($totalTerkumpul / $totalTagihan) * 100, 2)
                : 0,
            'breakdown_status' => [
                'belum_bayar' => $totalBelumBayar,
                'sebagian' => $totalSebagian,
                'lunas' => $totalLunas,
            ],
            'jumlah_tagihan' => $tagihanIds->count(),
        ];
    }

    private function ensureTenantContext(): void
    {
        if (! tenancy()->initialized || tenancy()->tenant === null) {
            throw new RuntimeException('Tenant context belum aktif.');
        }
    }
}
