<?php

namespace Modules\Keuangan\Services;

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

    /**
     * Jaring pengaman I-7: deteksi tagihan yang status tersimpannya TIDAK cocok
     * dengan status hasil hitung-ulang dari pembayaran terverifikasi (status
     * drift). Drift menandakan state-machine pernah terlewati / data manual —
     * harus dialarmkan. Mengembalikan daftar anomali (kosong = sehat).
     *
     * @return list<array{tagihan_id:int,status_tersimpan:string,status_seharusnya:string,total_verified:float,nominal_akhir:float}>
     */
    public function deteksiAnomaliStatus(): array
    {
        $this->ensureTenantContext();

        $anomali = [];

        Tagihan::query()
            ->whereNull('deleted_at')
            ->where('status', '!=', 'batal')
            ->chunkById(200, function ($tagihans) use (&$anomali): void {
                foreach ($tagihans as $tagihan) {
                    $verified = (float) $tagihan->pembayaran()
                        ->where('is_verified', true)
                        ->sum('nominal');

                    $nominalAkhir = (float) $tagihan->nominal_akhir;
                    $seharusnya = $this->statusDari($verified, $nominalAkhir);

                    if ($seharusnya !== $tagihan->status) {
                        $anomali[] = [
                            'tagihan_id' => $tagihan->id,
                            'status_tersimpan' => $tagihan->status,
                            'status_seharusnya' => $seharusnya,
                            'total_verified' => $verified,
                            'nominal_akhir' => $nominalAkhir,
                        ];
                    }
                }
            });

        return $anomali;
    }

    /**
     * Aturan status kanonik (independen dari PembayaranService) — dipakai untuk
     * verifikasi-silang. Sengaja diduplikasi agar rekonsiliasi menjadi sumber
     * kebenaran kedua yang berdiri sendiri.
     */
    private function statusDari(float $verified, float $nominalAkhir): string
    {
        if ($verified >= $nominalAkhir && $nominalAkhir > 0) {
            return 'lunas';
        }

        if ($verified > 0) {
            return 'sebagian';
        }

        return 'belum_bayar';
    }

    private function ensureTenantContext(): void
    {
        if (! tenancy()->initialized || tenancy()->tenant === null) {
            throw new RuntimeException('Tenant context belum aktif.');
        }
    }
}
