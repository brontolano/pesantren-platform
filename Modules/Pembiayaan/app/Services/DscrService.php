<?php

namespace Modules\Pembiayaan\Services;

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
