<?php

namespace Modules\Pajak\Services;

use Modules\Pajak\Models\TerPph21;
use Modules\Payroll\Models\SlipGaji;

/**
 * PPh Pasal 21 — dual-mode: TER bulanan (PP 58/2023, Jan-Nov) + progresif
 * Desember (UU HPP Pasal 17). KALKULATOR MURNI (tak memutasi slip).
 *
 * Alur TER (sejak 2024):
 *   Jan–Nov: PPh = Bruto × TER% (lookup kategori A/B/C dari PTKP status)
 *   Desember: hitung PPh tahunan progresif, kurangi akumulasi Jan–Nov
 *
 * ⚠️ GATE KEPATUHAN (Q3): tarif & aturan WAJIB ditinjau penasihat pajak.
 */
class PajakPenghasilanService
{
    public const PERLU_REVIEW_PENASIHAT_PAJAK = true;

    public const PTKP = [
        'TK0' => 54000000, 'TK1' => 58500000, 'TK2' => 63000000, 'TK3' => 67500000,
        'K0' => 58500000, 'K1' => 63000000, 'K2' => 67500000, 'K3' => 72000000,
    ];

    public const BRACKETS = [
        [60000000, 5], [250000000, 15], [500000000, 25], [5000000000, 30],
    ];

    public const TARIF_TERTINGGI = 35;

    /** Mapping PTKP status → kategori TER. */
    public const KATEGORI_TER = [
        'TK0' => 'A', 'TK1' => 'A',
        'TK2' => 'B', 'TK3' => 'B', 'K0' => 'B', 'K1' => 'B',
        'K2' => 'C', 'K3' => 'C',
    ];

    public function ptkp(string $status): float
    {
        return (float) (self::PTKP[$status] ?? self::PTKP['TK0']);
    }

    public function kategoriTer(string $ptkpStatus): string
    {
        return self::KATEGORI_TER[$ptkpStatus] ?? 'A';
    }

    /** PPh21 setahun dari PKP (progresif berlapis UU HPP). */
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
     * PPh 21 bulanan via TER (Jan-Nov) atau progresif recalc (Desember).
     *
     * @param  int  $bulanKe  1-12 (bulan ke berapa dalam tahun pajak)
     * @return array{metode:string, bruto:float, tarif_ter:?float, pph21:float, ...}
     */
    public function hitungBulananTer(
        float $brutoBulanan,
        string $ptkpStatus = 'TK0',
        int $bulanKe = 1,
        ?float $akumulasiBrutoSebelumnya = null,
        ?float $akumulasiPph21Sebelumnya = null,
    ): array {
        $kategori = $this->kategoriTer($ptkpStatus);

        if ($bulanKe < 12) {
            return $this->hitungTerBulanan($brutoBulanan, $kategori, $bulanKe);
        }

        return $this->hitungDesemberRecalc(
            $brutoBulanan,
            $ptkpStatus,
            $akumulasiBrutoSebelumnya ?? 0.0,
            $akumulasiPph21Sebelumnya ?? 0.0,
        );
    }

    /**
     * TER bulanan (Jan-Nov): PPh = Bruto × TER%.
     */
    private function hitungTerBulanan(float $bruto, string $kategori, int $bulanKe): array
    {
        $ter = TerPph21::cariTarif($kategori, $bruto);
        $tarif = $ter ? (float) $ter->tarif_persen : 0.0;
        $pph21 = round($bruto * $tarif / 100, 0, PHP_ROUND_HALF_UP);

        return [
            'metode' => 'ter',
            'bruto' => round($bruto, 2),
            'kategori_ter' => $kategori,
            'tarif_ter' => $tarif,
            'pph21' => (float) $pph21,
            'bulan_ke' => $bulanKe,
        ];
    }

    /**
     * Desember: hitung PPh tahunan progresif, kurangi akumulasi Jan-Nov.
     *
     * Bruto tahunan = akumulasi Jan-Nov + bruto Desember
     * PKP = (bruto_tahunan - biaya_jabatan - PTKP) dibulatkan ribuan ke bawah
     * PPh Desember = PPh tahunan - akumulasi PPh Jan-Nov
     */
    private function hitungDesemberRecalc(
        float $brutoDesember,
        string $ptkpStatus,
        float $akumulasiBruto,
        float $akumulasiPph21,
    ): array {
        $brutoTahunan = round($akumulasiBruto + $brutoDesember, 2);
        $biayaJabatan = round(min($brutoTahunan * 0.05, 6000000), 2);
        $nettoTahunan = $brutoTahunan - $biayaJabatan;
        $ptkp = $this->ptkp($ptkpStatus);
        $pkp = floor(max(0.0, $nettoTahunan - $ptkp) / 1000) * 1000;
        $pph21Tahunan = $this->pph21Tahunan($pkp);
        $pph21Desember = round(max(0.0, $pph21Tahunan - $akumulasiPph21), 0, PHP_ROUND_HALF_UP);

        return [
            'metode' => 'progresif_desember',
            'bruto' => round($brutoDesember, 2),
            'bruto_tahunan' => $brutoTahunan,
            'biaya_jabatan' => $biayaJabatan,
            'ptkp' => $ptkp,
            'pkp' => $pkp,
            'pph21_tahunan' => $pph21Tahunan,
            'akumulasi_pph21_jan_nov' => $akumulasiPph21,
            'pph21' => (float) $pph21Desember,
            'bulan_ke' => 12,
        ];
    }

    /**
     * Backward-compatible: PPh21 bulanan sederhana (tanpa TER).
     * Dipakai saat tabel TER belum di-seed atau untuk estimasi cepat.
     */
    public function hitungBulanan(float $brutoBulanan, string $ptkpStatus = 'TK0'): array
    {
        $brutoTahunan = round($brutoBulanan * 12, 2);
        $biayaJabatan = round(min($brutoTahunan * 0.05, 6000000), 2);
        $nettoTahunan = $brutoTahunan - $biayaJabatan;
        $ptkp = $this->ptkp($ptkpStatus);
        $pkp = floor(max(0.0, $nettoTahunan - $ptkp) / 1000) * 1000;
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

    /** Versi ringkas: hanya PPh21 sebulan (backward compat). */
    public function pph21Bulanan(float $brutoBulanan, string $ptkpStatus = 'TK0'): float
    {
        return $this->hitungBulanan($brutoBulanan, $ptkpStatus)['pph21_bulanan'];
    }

    /**
     * PPh21 via TER — versi ringkas, hanya nominal.
     * Otomatis pilih TER (bulan 1-11) atau progresif recalc (bulan 12).
     */
    public function pph21BulananTer(
        float $brutoBulanan,
        string $ptkpStatus = 'TK0',
        int $bulanKe = 1,
        ?float $akumulasiBrutoSebelumnya = null,
        ?float $akumulasiPph21Sebelumnya = null,
    ): float {
        return $this->hitungBulananTer(
            $brutoBulanan, $ptkpStatus, $bulanKe,
            $akumulasiBrutoSebelumnya, $akumulasiPph21Sebelumnya,
        )['pph21'];
    }

    /**
     * Hitung akumulasi bruto & PPh21 Jan s/d bulan sebelumnya dari slip final.
     *
     * @return array{bruto: float, pph21: float, jumlah_bulan: int}
     */
    public function akumulasiSampaiSebelumnya(int $usersId, string $periodeSekarang): array
    {
        $tahun = substr($periodeSekarang, 0, 4);
        $bulanSekarang = (int) substr($periodeSekarang, 5, 2);

        $slips = SlipGaji::where('users_id', $usersId)
            ->where('status', 'final')
            ->where('periode', 'like', $tahun.'-%')
            ->where('periode', '<', $periodeSekarang)
            ->whereRaw("periode ~ '^\d{4}-(0[1-9]|1[0-2])$'")
            ->get();

        return [
            'bruto' => round((float) $slips->sum('bruto'), 2),
            'pph21' => round((float) $slips->sum('pph21_dipotong'), 2),
            'jumlah_bulan' => $slips->count(),
        ];
    }

    /**
     * Rekap SPT PPh21 satu periode (dari slip final). Untuk ekspor.
     *
     * @return array{periode:string, baris:array, total_bruto:float, total_pph21:float, jumlah_slip:int}
     */
    public function sptRekapPph21(string $periode): array
    {
        $slips = SlipGaji::query()
            ->where('periode', $periode)
            ->where('status', 'final')
            ->with('user')
            ->get();

        $baris = $slips->map(fn (SlipGaji $s) => [
            'nama' => $s->user?->name ?? '—',
            'bruto' => (float) $s->bruto,
            'pph21' => (float) $s->pph21_dipotong,
        ])->all();

        return [
            'periode' => $periode,
            'baris' => $baris,
            'total_bruto' => round((float) $slips->sum('bruto'), 2),
            'total_pph21' => round((float) $slips->sum('pph21_dipotong'), 2),
            'jumlah_slip' => $slips->count(),
        ];
    }
}
