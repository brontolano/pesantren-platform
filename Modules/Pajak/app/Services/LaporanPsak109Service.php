<?php

namespace Modules\Pajak\Services;

use Illuminate\Support\Facades\DB;
use Modules\Ledger\Models\Akun;
use Modules\Ledger\Models\SumberDana;

/**
 * Laporan keuangan per PSAK 109 (Akuntansi Zakat, Infak/Sedekah).
 *
 * Menghasilkan 4 laporan wajib PSAK 109:
 * 1. Laporan Posisi Keuangan (per dana)
 * 2. Laporan Perubahan Dana
 * 3. Laporan Perubahan Aset Kelolaan
 * 4. Laporan Arus Kas (delegasi ke LaporanService::arusKas)
 *
 * Data bersumber dari jurnal_baris yang memiliki sumber_dana_id.
 */
class LaporanPsak109Service
{
    /**
     * Laporan Perubahan Dana — inti PSAK 109.
     * Menampilkan penerimaan, penyaluran, dan saldo per dana.
     *
     * @return array{dana_zakat: array, dana_infak: array, dana_amil: array, dana_non_halal: array}
     */
    public function perubahanDana(?string $from = null, ?string $to = null): array
    {
        return [
            'dana_zakat' => $this->hitungDana('zakat', $from, $to),
            'dana_infak_sedekah' => $this->hitungDana('infak', $from, $to),
            'dana_amil' => $this->hitungDana('amil', $from, $to),
            'dana_non_halal' => $this->hitungDanaNonHalal($from, $to),
        ];
    }

    /**
     * Laporan Posisi Keuangan (Neraca per Dana).
     */
    public function posisiKeuangan(?string $to = null): array
    {
        $danaZakat = $this->saldoDana('zakat', $to);
        $danaInfak = $this->saldoDana('infak', $to);
        $danaAmil = $this->saldoDana('amil', $to);
        $danaNonHalal = $this->saldoNonHalal($to);

        return [
            'aset' => [
                'kas_bank' => $this->totalAsetKasBank($to),
                'piutang' => $this->totalAsetPiutang($to),
                'aset_tetap' => $this->totalAsetTetap($to),
            ],
            'kewajiban' => $this->totalKewajiban($to),
            'saldo_dana' => [
                'zakat' => $danaZakat,
                'infak_sedekah' => $danaInfak,
                'amil' => $danaAmil,
                'non_halal' => $danaNonHalal,
                'total' => round($danaZakat + $danaInfak + $danaAmil + $danaNonHalal, 2),
            ],
        ];
    }

    /**
     * Laporan Perubahan Aset Kelolaan — aset yang dikelola amil untuk mustahiq.
     */
    public function perubahanAsetKelolaan(?string $from = null, ?string $to = null): array
    {
        // Aset kelolaan = aset tetap yang dibiayai dari dana zakat/infak
        // Mengambil jurnal di akun aset tetap (1-2xxx) dengan sumber dana ZIS
        $sumberZis = SumberDana::where(function ($q) {
            $q->where('nama_sumber', 'like', '%zakat%')
              ->orWhere('nama_sumber', 'like', '%infak%')
              ->orWhere('nama_sumber', 'like', '%sedekah%');
        })->pluck('id');

        if ($sumberZis->isEmpty()) {
            return ['saldo_awal' => 0, 'penambahan' => 0, 'penyusutan' => 0, 'penghentian' => 0, 'saldo_akhir' => 0];
        }

        $akunAsetTetap = Akun::where('kode', 'like', '1-2%')->pluck('id');

        $saldoAwal = 0.0;
        if ($from) {
            $saldoAwal = (float) DB::table('jurnal_baris')
                ->join('jurnal', 'jurnal.id', '=', 'jurnal_baris.jurnal_id')
                ->whereIn('jurnal_baris.akun_id', $akunAsetTetap->all())
                ->whereIn('jurnal_baris.sumber_dana_id', $sumberZis->all())
                ->where('jurnal.tanggal', '<', $from)
                ->selectRaw('COALESCE(SUM(debit) - SUM(kredit), 0)')
                ->value('total') ?? 0;
        }

        $mutasi = DB::table('jurnal_baris')
            ->join('jurnal', 'jurnal.id', '=', 'jurnal_baris.jurnal_id')
            ->whereIn('jurnal_baris.akun_id', $akunAsetTetap->all())
            ->whereIn('jurnal_baris.sumber_dana_id', $sumberZis->all())
            ->when($from, fn ($q) => $q->where('jurnal.tanggal', '>=', $from))
            ->when($to, fn ($q) => $q->where('jurnal.tanggal', '<=', $to))
            ->selectRaw('COALESCE(SUM(debit), 0) as penambahan, COALESCE(SUM(kredit), 0) as penghentian')
            ->first();

        $penambahan = (float) ($mutasi->penambahan ?? 0);
        $penghentian = (float) ($mutasi->penghentian ?? 0);

        return [
            'saldo_awal' => round($saldoAwal, 2),
            'penambahan' => round($penambahan, 2),
            'penyusutan' => 0.0, // placeholder — penyusutan terpisah
            'penghentian' => round($penghentian, 2),
            'saldo_akhir' => round($saldoAwal + $penambahan - $penghentian, 2),
        ];
    }

    private function hitungDana(string $jenis, ?string $from, ?string $to): array
    {
        $sumber = $this->sumberDanaByJenis($jenis);
        if ($sumber->isEmpty()) {
            return ['saldo_awal' => 0, 'penerimaan' => 0, 'penyaluran' => 0, 'bagian_amil' => 0, 'saldo_akhir' => 0];
        }

        $saldoAwal = $from ? $this->saldoSumber($sumber, null, $from) : 0.0;
        $penerimaan = $this->penerimaanSumber($sumber, $from, $to);
        $penyaluran = $this->penyaluranSumber($sumber, $from, $to);

        return [
            'saldo_awal' => round($saldoAwal, 2),
            'penerimaan' => round($penerimaan, 2),
            'penyaluran' => round($penyaluran, 2),
            'bagian_amil' => 0.0, // placeholder for amil share calculation
            'saldo_akhir' => round($saldoAwal + $penerimaan - $penyaluran, 2),
        ];
    }

    private function hitungDanaNonHalal(?string $from, ?string $to): array
    {
        $akun = Akun::where('kode', '2-2000')->first();
        if (! $akun) {
            return ['saldo_awal' => 0, 'penerimaan' => 0, 'penyaluran' => 0, 'saldo_akhir' => 0];
        }

        $saldoAwal = 0.0;
        if ($from) {
            $saldoAwal = (float) DB::table('jurnal_baris')
                ->join('jurnal', 'jurnal.id', '=', 'jurnal_baris.jurnal_id')
                ->where('jurnal_baris.akun_id', $akun->id)
                ->where('jurnal.tanggal', '<', $from)
                ->selectRaw('COALESCE(SUM(kredit) - SUM(debit), 0) as total')
                ->value('total');
        }

        $penerimaan = (float) DB::table('jurnal_baris')
            ->join('jurnal', 'jurnal.id', '=', 'jurnal_baris.jurnal_id')
            ->where('jurnal_baris.akun_id', $akun->id)
            ->when($from, fn ($q) => $q->where('jurnal.tanggal', '>=', $from))
            ->when($to, fn ($q) => $q->where('jurnal.tanggal', '<=', $to))
            ->sum('jurnal_baris.kredit');

        $penyaluran = (float) DB::table('jurnal_baris')
            ->join('jurnal', 'jurnal.id', '=', 'jurnal_baris.jurnal_id')
            ->where('jurnal_baris.akun_id', $akun->id)
            ->when($from, fn ($q) => $q->where('jurnal.tanggal', '>=', $from))
            ->when($to, fn ($q) => $q->where('jurnal.tanggal', '<=', $to))
            ->sum('jurnal_baris.debit');

        return [
            'saldo_awal' => round($saldoAwal, 2),
            'penerimaan' => round($penerimaan, 2),
            'penyaluran' => round($penyaluran, 2),
            'saldo_akhir' => round($saldoAwal + $penerimaan - $penyaluran, 2),
        ];
    }

    private function sumberDanaByJenis(string $jenis)
    {
        return SumberDana::where(function ($q) use ($jenis) {
            match ($jenis) {
                'zakat' => $q->where('nama_sumber', 'like', '%zakat%'),
                'infak' => $q->where('nama_sumber', 'like', '%infak%')
                             ->orWhere('nama_sumber', 'like', '%sedekah%'),
                'amil' => $q->where('nama_sumber', 'like', '%amil%'),
                default => $q->whereRaw('1=0'),
            };
        })->pluck('id');
    }

    private function saldoSumber($ids, ?string $from, ?string $to): float
    {
        $q = DB::table('jurnal_baris')
            ->join('jurnal', 'jurnal.id', '=', 'jurnal_baris.jurnal_id')
            ->join('akun', 'akun.id', '=', 'jurnal_baris.akun_id')
            ->whereIn('jurnal_baris.sumber_dana_id', $ids->all());

        if ($to) {
            $q->where('jurnal.tanggal', '<', $to);
        }

        // Pendapatan (kredit) - Beban (debit) for this fund
        return (float) $q->selectRaw('COALESCE(SUM(CASE WHEN akun.tipe = \'PENDAPATAN\' THEN kredit - debit ELSE 0 END) - SUM(CASE WHEN akun.tipe = \'BEBAN\' THEN debit - kredit ELSE 0 END), 0) as saldo')
            ->value('saldo');
    }

    private function penerimaanSumber($ids, ?string $from, ?string $to): float
    {
        return (float) DB::table('jurnal_baris')
            ->join('jurnal', 'jurnal.id', '=', 'jurnal_baris.jurnal_id')
            ->join('akun', 'akun.id', '=', 'jurnal_baris.akun_id')
            ->where('akun.tipe', 'PENDAPATAN')
            ->whereIn('jurnal_baris.sumber_dana_id', $ids->all())
            ->when($from, fn ($q) => $q->where('jurnal.tanggal', '>=', $from))
            ->when($to, fn ($q) => $q->where('jurnal.tanggal', '<=', $to))
            ->selectRaw('COALESCE(SUM(kredit) - SUM(debit), 0) as total')
            ->value('total');
    }

    private function penyaluranSumber($ids, ?string $from, ?string $to): float
    {
        return (float) DB::table('jurnal_baris')
            ->join('jurnal', 'jurnal.id', '=', 'jurnal_baris.jurnal_id')
            ->join('akun', 'akun.id', '=', 'jurnal_baris.akun_id')
            ->where('akun.tipe', 'BEBAN')
            ->whereIn('jurnal_baris.sumber_dana_id', $ids->all())
            ->when($from, fn ($q) => $q->where('jurnal.tanggal', '>=', $from))
            ->when($to, fn ($q) => $q->where('jurnal.tanggal', '<=', $to))
            ->selectRaw('COALESCE(SUM(debit) - SUM(kredit), 0) as total')
            ->value('total');
    }

    private function saldoDana(string $jenis, ?string $to): float
    {
        $ids = $this->sumberDanaByJenis($jenis);
        if ($ids->isEmpty()) {
            return 0.0;
        }
        return $this->saldoSumber($ids, null, $to ? date('Y-m-d', strtotime($to.' +1 day')) : null);
    }

    private function saldoNonHalal(?string $to): float
    {
        $akun = Akun::where('kode', '2-2000')->first();
        if (! $akun) {
            return 0.0;
        }

        return (float) DB::table('jurnal_baris')
            ->join('jurnal', 'jurnal.id', '=', 'jurnal_baris.jurnal_id')
            ->where('jurnal_baris.akun_id', $akun->id)
            ->when($to, fn ($q) => $q->where('jurnal.tanggal', '<=', $to))
            ->selectRaw('COALESCE(SUM(kredit) - SUM(debit), 0) as saldo')
            ->value('saldo');
    }

    private function totalAsetKasBank(?string $to): float
    {
        return $this->saldoTipeAkun('1-1%', $to);
    }

    private function totalAsetPiutang(?string $to): float
    {
        return $this->saldoTipeAkun('1-12%', $to);
    }

    private function totalAsetTetap(?string $to): float
    {
        return $this->saldoTipeAkun('1-2%', $to);
    }

    private function totalKewajiban(?string $to): float
    {
        $ids = Akun::where('tipe', 'KEWAJIBAN')->pluck('id');
        if ($ids->isEmpty()) {
            return 0.0;
        }

        return (float) DB::table('jurnal_baris')
            ->join('jurnal', 'jurnal.id', '=', 'jurnal_baris.jurnal_id')
            ->whereIn('jurnal_baris.akun_id', $ids->all())
            ->when($to, fn ($q) => $q->where('jurnal.tanggal', '<=', $to))
            ->selectRaw('COALESCE(SUM(kredit) - SUM(debit), 0) as saldo')
            ->value('saldo');
    }

    private function saldoTipeAkun(string $kodePattern, ?string $to): float
    {
        $ids = Akun::where('kode', 'like', $kodePattern)->pluck('id');
        if ($ids->isEmpty()) {
            return 0.0;
        }

        return (float) DB::table('jurnal_baris')
            ->join('jurnal', 'jurnal.id', '=', 'jurnal_baris.jurnal_id')
            ->whereIn('jurnal_baris.akun_id', $ids->all())
            ->when($to, fn ($q) => $q->where('jurnal.tanggal', '<=', $to))
            ->selectRaw('COALESCE(SUM(debit) - SUM(kredit), 0) as saldo')
            ->value('saldo');
    }
}
