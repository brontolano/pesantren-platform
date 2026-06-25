<?php

namespace Modules\Pajak\Services;

use Modules\Pajak\Models\TarifPajak;
use Modules\Pajak\Support\KebijakanPajakKehalalan;

/**
 * Mesin pajak otomatis (PMK 59/2022). Membaca tarif dari tabel `tarif_pajak`
 * (configurable per tenant) — bukan konstanta. Setiap aturan punya uji unit.
 *
 * ⚠️ Gate kepatuhan (Q3): aturan ini WAJIB ditinjau penasihat pajak sebelum rilis.
 */
class MesinPajakService
{
    /**
     * GATE KEPATUHAN (Q3) — mengikat. Aturan PPN/PPh di sini mengikuti PMK 59/2022
     * sebagai DEFAULT, tetapi WAJIB ditinjau & disahkan penasihat pajak sebelum
     * dipakai produksi/rilis. Tarif disimpan di tabel `tarif_pajak` (configurable),
     * bukan dikunci di kode — agar mudah dikoreksi saat regulasi berubah.
     */
    public const PERLU_REVIEW_PENASIHAT_PAJAK = true;

    public const DASAR_REGULASI = 'PMK 59/2022';

    public function __construct(
        private readonly KebijakanPajakKehalalan $kebijakanKehalalan = new KebijakanPajakKehalalan,
    ) {}

    /**
     * Hitung PPN & PPh untuk satu belanja.
     *
     * @param  string  $jenis  'barang' | 'jasa' | 'sewa'
     * @param  string  $kehalalan  'halal' | 'syubhat' | 'non_halal' (G1-04)
     * @return array{ppn: float, pph_jenis: ?string, pph: float}
     */
    public function hitung(float $dpp, string $jenis, bool $danaBos, bool $vendorNpwp, string $kehalalan = 'halal'): array
    {
        $dpp = round($dpp, 2);

        // G1-04: hak negara atas pajak hanya dari transaksi HALAL. Dana syubhat/
        // non-halal → tidak dipungut pajak negara.
        if ($this->kebijakanKehalalan->bebasPajakNegara($kehalalan)) {
            return ['ppn' => 0.0, 'pph_jenis' => null, 'pph' => 0.0];
        }

        return [
            'ppn' => $this->hitungPpn($dpp),
            'pph_jenis' => $this->jenisPph($jenis),
            'pph' => $this->hitungPph($dpp, $jenis, $danaBos, $vendorNpwp),
        ];
    }

    private function hitungPpn(float $dpp): float
    {
        $tarif = $this->tarif('ppn');
        if ($tarif === null) {
            return 0.0;
        }

        // PPN dipungut bila DPP mencapai ambang (default Rp1jt utk BOS/BOP).
        if ($tarif->ambang_min !== null && $dpp < (float) $tarif->ambang_min) {
            return 0.0;
        }

        return round($dpp * (float) $tarif->tarif_persen / 100, 2);
    }

    private function hitungPph(float $dpp, string $jenis, bool $danaBos, bool $vendorNpwp): float
    {
        return match ($jenis) {
            'barang' => $this->pph22($dpp, $danaBos, $vendorNpwp),
            'jasa' => $this->pph23($dpp, $vendorNpwp),
            'sewa' => $this->tarifKali($dpp, 'pph_final_42'),
            default => 0.0,
        };
    }

    private function pph22(float $dpp, bool $danaBos, bool $vendorNpwp): float
    {
        // Barang dari dana BOS DIKECUALIKAN PPh 22.
        if ($danaBos || ! $vendorNpwp) {
            return 0.0;
        }

        $tarif = $this->tarif('pph22');
        if ($tarif === null) {
            return 0.0;
        }
        // Hanya untuk DPP di atas ambang (default Rp2jt).
        if ($tarif->ambang_min !== null && $dpp < (float) $tarif->ambang_min) {
            return 0.0;
        }

        return round($dpp * (float) $tarif->tarif_persen / 100, 2);
    }

    private function pph23(float $dpp, bool $vendorNpwp): float
    {
        // Jasa: 2% (NPWP) / 4% (non-NPWP), tanpa ambang minimum.
        return $this->tarifKali($dpp, $vendorNpwp ? 'pph23_npwp' : 'pph23_non_npwp');
    }

    private function jenisPph(string $jenis): ?string
    {
        return match ($jenis) {
            'barang' => 'pph22',
            'jasa' => 'pph23',
            'sewa' => 'pph_final_42',
            default => null,
        };
    }

    private function tarifKali(float $dpp, string $kode): float
    {
        $tarif = $this->tarif($kode);
        if ($tarif === null) {
            return 0.0;
        }

        return round($dpp * (float) $tarif->tarif_persen / 100, 2);
    }

    private function tarif(string $kode): ?TarifPajak
    {
        return TarifPajak::where('kode', $kode)->where('is_active', true)->first();
    }
}
