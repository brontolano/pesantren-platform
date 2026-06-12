<?php

namespace Modules\Pajak\Services;

use Illuminate\Support\Facades\DB;
use Modules\Ledger\Models\SumberDana;
use Modules\Ledger\Services\LaporanService;

/**
 * SPT Tahunan Badan 1771 — worksheet pembantu.
 *
 * Yayasan pendidikan: pendapatan dari kegiatan pendidikan BEBAS PPh Badan
 * (Pasal 4(3) UU PPh), pendapatan komersial KENA 22%.
 *
 * Service ini memisahkan pendapatan berdasarkan sumber dana (is_komersial)
 * dan menghitung estimasi PPh Badan terutang.
 */
class SptBadanService
{
    public const TARIF_PPH_BADAN = 22; // persen

    public function __construct(
        private readonly LaporanService $laporan,
        private readonly SisaLebihService $sisaLebih,
    ) {}

    /**
     * Worksheet SPT Badan — pisahkan pendapatan & beban per kategori pajak.
     */
    public function worksheet(string $tahun): array
    {
        $from = $tahun.'-01-01';
        $to = $tahun.'-12-31';

        $lr = $this->laporan->labaRugi($from, $to);

        $pendapatanKomersial = $this->pendapatanPerKategori($from, $to, true);
        $pendapatanPendidikan = $this->pendapatanPerKategori($from, $to, false);
        $bebanKomersial = $this->bebanPerKategori($from, $to, true);
        $bebanPendidikan = $this->bebanPerKategori($from, $to, false);

        $labaKomersial = round($pendapatanKomersial - $bebanKomersial, 2);
        $labaPendidikan = round($pendapatanPendidikan - $bebanPendidikan, 2);

        $koreksiPositif = 0.0;  // placeholder untuk koreksi fiskal
        $koreksiNegatif = 0.0;

        $pkp = round(max(0.0, $labaKomersial + $koreksiPositif - $koreksiNegatif), 2);
        $pphBadan = round($pkp * self::TARIF_PPH_BADAN / 100, 2);

        $sisaLebihData = $this->sisaLebih->ringkasan();
        $sisaLebihPajak = $this->sisaLebih->potensiPajakBadan();

        return [
            'tahun' => $tahun,
            'laba_rugi_total' => $lr,

            'pendapatan' => [
                'pendidikan' => [
                    'nominal' => $pendapatanPendidikan,
                    'status_pajak' => 'DIKECUALIKAN (Pasal 4 ayat 3)',
                ],
                'komersial' => [
                    'nominal' => $pendapatanKomersial,
                    'status_pajak' => 'KENA PAJAK',
                ],
            ],

            'beban' => [
                'pendidikan' => $bebanPendidikan,
                'komersial' => $bebanKomersial,
            ],

            'laba_fiskal' => [
                'pendidikan' => $labaPendidikan,
                'komersial' => $labaKomersial,
            ],

            'koreksi_fiskal' => [
                'positif' => $koreksiPositif,
                'negatif' => $koreksiNegatif,
                'catatan' => 'Koreksi fiskal belum diimplementasikan — isi manual atau hubungi konsultan pajak.',
            ],

            'penghasilan_kena_pajak' => $pkp,
            'tarif_pph_badan' => self::TARIF_PPH_BADAN,
            'pph_badan_terutang' => $pphBadan,

            'sisa_lebih' => [
                'data' => $sisaLebihData,
                'potensi_pajak' => $sisaLebihPajak,
            ],

            'kode_map' => '411126',  // MAP PPh Badan
            'kode_kjs' => '200',     // KJS tahunan
        ];
    }

    /**
     * Total pendapatan dari sumber dana komersial atau pendidikan.
     */
    private function pendapatanPerKategori(string $from, string $to, bool $komersial): float
    {
        $sumberDanaIds = SumberDana::where('is_komersial', $komersial)
            ->where('is_active', true)
            ->pluck('id');

        if ($sumberDanaIds->isEmpty()) {
            return $komersial ? 0.0 : $this->totalAkun($from, $to, '4%'); // default semua pendapatan = pendidikan
        }

        return (float) DB::table('jurnal_baris')
            ->join('jurnal', 'jurnal.id', '=', 'jurnal_baris.jurnal_id')
            ->join('akun', 'akun.id', '=', 'jurnal_baris.akun_id')
            ->where('akun.kode', 'like', '4%')
            ->whereIn('jurnal_baris.sumber_dana_id', $sumberDanaIds->all())
            ->whereBetween('jurnal.tanggal', [$from, $to])
            ->selectRaw('COALESCE(SUM(jurnal_baris.kredit) - SUM(jurnal_baris.debit), 0) as total')
            ->value('total');
    }

    private function bebanPerKategori(string $from, string $to, bool $komersial): float
    {
        $sumberDanaIds = SumberDana::where('is_komersial', $komersial)
            ->where('is_active', true)
            ->pluck('id');

        if ($sumberDanaIds->isEmpty()) {
            return $komersial ? 0.0 : $this->totalAkun($from, $to, '5%');
        }

        return (float) DB::table('jurnal_baris')
            ->join('jurnal', 'jurnal.id', '=', 'jurnal_baris.jurnal_id')
            ->join('akun', 'akun.id', '=', 'jurnal_baris.akun_id')
            ->where('akun.kode', 'like', '5%')
            ->whereIn('jurnal_baris.sumber_dana_id', $sumberDanaIds->all())
            ->whereBetween('jurnal.tanggal', [$from, $to])
            ->selectRaw('COALESCE(SUM(jurnal_baris.debit) - SUM(jurnal_baris.kredit), 0) as total')
            ->value('total');
    }

    private function totalAkun(string $from, string $to, string $kodePrefix): float
    {
        $isBeban = str_starts_with($kodePrefix, '5');

        return (float) DB::table('jurnal_baris')
            ->join('jurnal', 'jurnal.id', '=', 'jurnal_baris.jurnal_id')
            ->join('akun', 'akun.id', '=', 'jurnal_baris.akun_id')
            ->where('akun.kode', 'like', $kodePrefix)
            ->whereBetween('jurnal.tanggal', [$from, $to])
            ->selectRaw($isBeban
                ? 'COALESCE(SUM(jurnal_baris.debit) - SUM(jurnal_baris.kredit), 0) as total'
                : 'COALESCE(SUM(jurnal_baris.kredit) - SUM(jurnal_baris.debit), 0) as total')
            ->value('total');
    }
}
