<?php

namespace Modules\Pajak\Services;

use Illuminate\Support\Facades\DB;
use Modules\Ledger\Models\Akun;
use Modules\Ledger\Models\SumberDana;
use Modules\Ledger\Services\LaporanService;

/**
 * Laporan keuangan per ISAK 35 (Entitas Berorientasi Nonlaba).
 *
 * ISAK 35 menggantikan PSAK 45 sejak 1 Jan 2020. Klasifikasi aset neto:
 * - "Tanpa Pembatasan" (unrestricted) — dana umum operasional
 * - "Dengan Pembatasan" (restricted) — dana terikat dari donor (wakaf, ZIS, BOS)
 */
class LaporanIsak35Service
{
    public function __construct(private readonly LaporanService $laporan) {}

    /**
     * Laporan Perubahan Aset Neto (inti ISAK 35).
     *
     * @return array{tanpa_pembatasan: array, dengan_pembatasan: array, total: array}
     */
    public function perubahanAsetNeto(?string $from = null, ?string $to = null): array
    {
        $tanpaPembatasan = $this->hitungAsetNeto(false, $from, $to);
        $denganPembatasan = $this->hitungAsetNeto(true, $from, $to);

        $totalAwal = round($tanpaPembatasan['saldo_awal'] + $denganPembatasan['saldo_awal'], 2);
        $totalAkhir = round($tanpaPembatasan['saldo_akhir'] + $denganPembatasan['saldo_akhir'], 2);

        return [
            'tanpa_pembatasan' => $tanpaPembatasan,
            'dengan_pembatasan' => $denganPembatasan,
            'total' => [
                'saldo_awal' => $totalAwal,
                'perubahan' => round($totalAkhir - $totalAwal, 2),
                'saldo_akhir' => $totalAkhir,
            ],
        ];
    }

    /**
     * Laporan Penghasilan Komprehensif (pengganti Laba Rugi di ISAK 35).
     * Sama dengan L/R tapi dikategorikan per pembatasan.
     */
    public function penghasilanKomprehensif(?string $from = null, ?string $to = null): array
    {
        $tanpaPembatasan = $this->pendapatanBeban(false, $from, $to);
        $denganPembatasan = $this->pendapatanBeban(true, $from, $to);

        return [
            'tanpa_pembatasan' => $tanpaPembatasan,
            'dengan_pembatasan' => $denganPembatasan,
            'surplus_total' => round(
                $tanpaPembatasan['surplus'] + $denganPembatasan['surplus'], 2
            ),
        ];
    }

    /**
     * Laporan Posisi Keuangan (Neraca) format ISAK 35.
     * Ekuitas dipecah jadi Aset Neto tanpa/dengan pembatasan.
     */
    public function posisiKeuangan(?string $to = null): array
    {
        $neraca = $this->laporan->neraca($to);

        $akunTanpa = Akun::where('kode', '3-1000')->first();
        $akunDengan = Akun::whereIn('kode', ['3-2000', '3-3000'])->pluck('id');

        $saldoTanpa = 0.0;
        $saldoDengan = 0.0;

        if ($akunTanpa) {
            $saldoTanpa = (float) DB::table('jurnal_baris')
                ->join('jurnal', 'jurnal.id', '=', 'jurnal_baris.jurnal_id')
                ->where('jurnal_baris.akun_id', $akunTanpa->id)
                ->when($to, fn ($q) => $q->where('jurnal.tanggal', '<=', $to))
                ->selectRaw('COALESCE(SUM(kredit) - SUM(debit), 0) as saldo')
                ->value('saldo');
        }

        if ($akunDengan->isNotEmpty()) {
            $saldoDengan = (float) DB::table('jurnal_baris')
                ->join('jurnal', 'jurnal.id', '=', 'jurnal_baris.jurnal_id')
                ->whereIn('jurnal_baris.akun_id', $akunDengan->all())
                ->when($to, fn ($q) => $q->where('jurnal.tanggal', '<=', $to))
                ->selectRaw('COALESCE(SUM(kredit) - SUM(debit), 0) as saldo')
                ->value('saldo');
        }

        return [
            'aset' => $neraca['aset'],
            'total_aset' => $neraca['total_aset'],
            'kewajiban' => $neraca['kewajiban'],
            'aset_neto' => [
                'tanpa_pembatasan' => round($saldoTanpa + $neraca['surplus_berjalan'], 2),
                'dengan_pembatasan' => round($saldoDengan, 2),
                'total' => round($saldoTanpa + $saldoDengan + $neraca['surplus_berjalan'], 2),
            ],
            'total_kewajiban_aset_neto' => $neraca['total_kewajiban_ekuitas'],
            'seimbang' => $neraca['seimbang'],
        ];
    }

    private function hitungAsetNeto(bool $terikat, ?string $from, ?string $to): array
    {
        $sumberIds = SumberDana::where('is_terikat', $terikat)->pluck('id');

        $saldoAwal = 0.0;
        if ($from && $sumberIds->isNotEmpty()) {
            $saldoAwal = $this->saldoDanaSumber($sumberIds, null, $from);
        }

        $pendapatan = $sumberIds->isNotEmpty()
            ? $this->totalSumber($sumberIds, 'PENDAPATAN', $from, $to) : 0.0;
        $beban = $sumberIds->isNotEmpty()
            ? $this->totalSumber($sumberIds, 'BEBAN', $from, $to) : 0.0;

        // Jika tanpa pembatasan dan tidak ada sumber dana tagged, ambil semua
        if (! $terikat && $sumberIds->isEmpty()) {
            $lr = $this->laporan->labaRugi($from, $to);
            $pendapatan = $lr['total_pendapatan'];
            $beban = $lr['total_beban'];
        }

        $perubahan = round($pendapatan - $beban, 2);

        return [
            'saldo_awal' => round($saldoAwal, 2),
            'pendapatan' => round($pendapatan, 2),
            'beban' => round($beban, 2),
            'surplus' => $perubahan,
            'saldo_akhir' => round($saldoAwal + $perubahan, 2),
        ];
    }

    private function pendapatanBeban(bool $terikat, ?string $from, ?string $to): array
    {
        $sumberIds = SumberDana::where('is_terikat', $terikat)->pluck('id');

        if (! $terikat && $sumberIds->isEmpty()) {
            $lr = $this->laporan->labaRugi($from, $to);

            return [
                'pendapatan' => $lr['pendapatan'],
                'beban' => $lr['beban'],
                'total_pendapatan' => $lr['total_pendapatan'],
                'total_beban' => $lr['total_beban'],
                'surplus' => $lr['surplus_defisit'],
            ];
        }

        $pendapatan = $sumberIds->isNotEmpty()
            ? $this->totalSumber($sumberIds, 'PENDAPATAN', $from, $to) : 0.0;
        $beban = $sumberIds->isNotEmpty()
            ? $this->totalSumber($sumberIds, 'BEBAN', $from, $to) : 0.0;

        return [
            'pendapatan' => [],
            'beban' => [],
            'total_pendapatan' => round($pendapatan, 2),
            'total_beban' => round($beban, 2),
            'surplus' => round($pendapatan - $beban, 2),
        ];
    }

    private function saldoDanaSumber($ids, ?string $from, ?string $to): float
    {
        return (float) DB::table('jurnal_baris')
            ->join('jurnal', 'jurnal.id', '=', 'jurnal_baris.jurnal_id')
            ->join('akun', 'akun.id', '=', 'jurnal_baris.akun_id')
            ->whereIn('jurnal_baris.sumber_dana_id', $ids->all())
            ->when($to, fn ($q) => $q->where('jurnal.tanggal', '<', $to))
            ->selectRaw("COALESCE(
                SUM(CASE WHEN akun.tipe = 'PENDAPATAN' THEN kredit - debit ELSE 0 END)
                - SUM(CASE WHEN akun.tipe = 'BEBAN' THEN debit - kredit ELSE 0 END)
            , 0) as saldo")
            ->value('saldo');
    }

    private function totalSumber($ids, string $tipe, ?string $from, ?string $to): float
    {
        $expr = $tipe === 'PENDAPATAN'
            ? 'SUM(kredit) - SUM(debit)'
            : 'SUM(debit) - SUM(kredit)';

        return (float) DB::table('jurnal_baris')
            ->join('jurnal', 'jurnal.id', '=', 'jurnal_baris.jurnal_id')
            ->join('akun', 'akun.id', '=', 'jurnal_baris.akun_id')
            ->where('akun.tipe', $tipe)
            ->whereIn('jurnal_baris.sumber_dana_id', $ids->all())
            ->when($from, fn ($q) => $q->where('jurnal.tanggal', '>=', $from))
            ->when($to, fn ($q) => $q->where('jurnal.tanggal', '<=', $to))
            ->selectRaw("COALESCE({$expr}, 0) as total")
            ->value('total');
    }
}
