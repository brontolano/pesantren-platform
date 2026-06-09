<?php

namespace Modules\Pajak\Services;

use Modules\Payroll\Models\SlipGaji;

/**
 * G2-12 — PPh Pasal 21 PTKP-aware tiered (UU HPP). Metode gross: bruto −
 * biaya jabatan (5%, maks Rp6jt/thn) → netto; PKP = netto − PTKP; pajak
 * progresif per lapisan. KALKULATOR MURNI (tak memutasi slip).
 *
 * ⚠️ GATE KEPATUHAN (Q3): tarif & aturan WAJIB ditinjau penasihat pajak sebelum
 * dipakai resmi. Versi ini menyederhanakan (tanpa surcharge non-NPWP, iuran
 * pensiun pengurang, atau TER bulanan PMK 168/2023).
 */
class PajakPenghasilanService
{
    public const PERLU_REVIEW_PENASIHAT_PAJAK = true;

    /** PTKP setahun (Rp) per status. */
    public const PTKP = [
        'TK0' => 54000000, 'TK1' => 58500000, 'TK2' => 63000000, 'TK3' => 67500000,
        'K0' => 58500000, 'K1' => 63000000, 'K2' => 67500000, 'K3' => 72000000,
    ];

    /** Lapisan tarif progresif UU HPP: [batas_atas_PKP, tarif%]. */
    public const BRACKETS = [
        [60000000, 5], [250000000, 15], [500000000, 25], [5000000000, 30],
    ];

    public const TARIF_TERTINGGI = 35; // > Rp5M

    public function ptkp(string $status): float
    {
        return (float) (self::PTKP[$status] ?? self::PTKP['TK0']);
    }

    /** PPh21 setahun dari PKP (progresif berlapis). */
    public function pph21Tahunan(float $pkp): float
    {
        $pkp = max(0.0, $pkp);
        $pajak = 0.0;
        $bawah = 0.0;

        foreach (self::BRACKETS as [$atas, $tarif]) {
            if ($pkp <= $bawah) {
                return round($pajak, 2);
            }
            $kena = min($pkp, (float) $atas) - $bawah;
            $pajak += $kena * $tarif / 100;
            $bawah = (float) $atas;
        }

        if ($pkp > $bawah) {
            $pajak += ($pkp - $bawah) * self::TARIF_TERTINGGI / 100;
        }

        return round($pajak, 2);
    }

    /**
     * PPh21 sebulan dari bruto sebulan + status PTKP.
     *
     * @return array{bruto_tahunan:float, biaya_jabatan:float, ptkp:float, pkp:float, pph21_tahunan:float, pph21_bulanan:float}
     */
    public function hitungBulanan(float $brutoBulanan, string $ptkpStatus = 'TK0'): array
    {
        $brutoTahunan = round($brutoBulanan * 12, 2);
        $biayaJabatan = round(min($brutoTahunan * 0.05, 6000000), 2);     // 5%, maks 6jt/thn
        $nettoTahunan = $brutoTahunan - $biayaJabatan;
        $ptkp = $this->ptkp($ptkpStatus);
        $pkp = floor(max(0.0, $nettoTahunan - $ptkp) / 1000) * 1000;      // dibulatkan ribuan ke bawah
        $pph21Tahunan = $this->pph21Tahunan($pkp);

        return [
            'bruto_tahunan' => $brutoTahunan,
            'biaya_jabatan' => $biayaJabatan,
            'ptkp' => $ptkp,
            'pkp' => $pkp,
            'pph21_tahunan' => $pph21Tahunan,
            'pph21_bulanan' => round($pph21Tahunan / 12, 2),
        ];
    }

    /** Versi ringkas: hanya PPh21 sebulan. */
    public function pph21Bulanan(float $brutoBulanan, string $ptkpStatus = 'TK0'): float
    {
        return $this->hitungBulanan($brutoBulanan, $ptkpStatus)['pph21_bulanan'];
    }

    /**
     * Rekap SPT PPh21 satu periode (dari slip final). Untuk ekspor F8.
     *
     * @return array{periode:string, baris:array<int,array>, total_pph21:float, jumlah_slip:int}
     */
    public function sptRekapPph21(string $periode): array
    {
        $slips = SlipGaji::query()->where('periode', $periode)->where('status', 'final')->with('user')->get();

        $baris = $slips->map(fn (SlipGaji $s) => [
            'nama' => $s->user?->name ?? '—',
            'bruto' => (float) $s->bruto,
            'pph21' => (float) $s->pph21_dipotong,
        ])->all();

        return [
            'periode' => $periode,
            'baris' => $baris,
            'total_pph21' => round((float) $slips->sum('pph21_dipotong'), 2),
            'jumlah_slip' => $slips->count(),
        ];
    }
}
