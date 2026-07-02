<?php

namespace Modules\Pembiayaan\Services;

use Modules\Ledger\Services\LaporanService;
use Modules\Ledger\Support\FiturKeuangan;

/**
 * Analisis kelayakan pembiayaan syariah (DSCR).
 * DSCR = EBITDA ÷ (Angsuran Pokok + Margin/Imbal Hasil tahunan).
 *
 * KOREKSI WAJIB #1: ambang LEMBAGA = 1,25× (BUKAN 2,5× yang merupakan syarat
 * Pinjaman Daerah). Zona: aman ≥ 1,25 · waspada 1,0–1,25 · bahaya < 1,0.
 * Ambang configurable (default 1,25). Hasil < ambang TIDAK auto-reject.
 */
class DscrService
{
    /**
     * @return array{dscr: float, zona: string, ambang: float}
     */
    public function hitung(float $ebitda, float $angsuranPokok, float $margin): array
    {
        $penyebut = round($angsuranPokok + $margin, 2);
        $dscr = $penyebut > 0 ? round($ebitda / $penyebut, 2) : 0.0;
        $ambang = FiturKeuangan::ambangDscr();

        return [
            'dscr' => $dscr,
            'zona' => $this->zona($dscr, $ambang),
            'ambang' => $ambang,
        ];
    }

    /**
     * gap-101 — Panduan/estimasi EBITDA tahunan dari buku besar (READ-ONLY, advisory).
     *
     * Proksi EBITDA ≈ surplus/defisit operasional (Pendapatan − Beban) dari
     * LaporanService::labaRugi() yang SUDAH ADA. Angka ini hanya bantuan input
     * agar staf tak menebak — TIDAK memposting jurnal & TIDAK mengubah rumus DSCR.
     * Karena beban penyusutan & beban bunga masih termasuk dalam surplus L-R,
     * hasilnya konservatif (≤ EBITDA sebenarnya); staf boleh menyesuaikan manual.
     *
     * @param  string|null  $from  awal periode (Y-m-d); null = sejak awal
     * @param  string|null  $to  akhir periode (Y-m-d); null = s.d. kini
     * @return array{estimasi: float, dasar: string, catatan: string}
     */
    public function estimasiEbitdaDariLedger(?string $from = null, ?string $to = null): array
    {
        $surplus = (float) app(LaporanService::class)->labaRugi($from, $to)['surplus_defisit'];

        return [
            'estimasi' => round(max($surplus, 0.0), 2),
            'dasar' => 'surplus_operasional_laba_rugi',
            'catatan' => 'Estimasi advisory dari surplus operasional buku besar (konservatif; belum '
                .'menambahkan kembali penyusutan/bunga). Silakan sesuaikan manual bila perlu.',
        ];
    }

    private function zona(float $dscr, float $ambang): string
    {
        if ($dscr >= $ambang) {
            return 'aman';      // hijau
        }
        if ($dscr >= 1.0) {
            return 'waspada';   // kuning — BUKAN ditolak
        }

        return 'bahaya';        // merah
    }
}
