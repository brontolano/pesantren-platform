<?php

namespace Modules\Ledger\Services;

use Illuminate\Support\Collection;
use Modules\Ledger\Models\Akun;
use Modules\Ledger\Models\JurnalBaris;

/**
 * Proyeksi read-only dari jurnal_baris (Pagar #1: laporan = proyeksi, bukan tabel tersimpan).
 *
 * Menyediakan Buku Besar per akun, BKU (Buku Kas Umum) / Buku Pembantu Kas-Bank,
 * dan neraca saldo (trial balance) sebagai dasar Neraca/L-R di modul Pelaporan.
 */
class BukuBesarService
{
    /**
     * Buku Besar satu akun: daftar mutasi + saldo berjalan.
     *
     * @return array{akun: Akun, saldo_awal: float, mutasi: Collection, saldo_akhir: float}
     */
    public function bukuBesar(int $akunId, ?string $from = null, ?string $to = null): array
    {
        $akun = Akun::findOrFail($akunId);
        $faktor = $akun->saldo_normal === 'debit' ? 1 : -1;

        $saldoAwal = 0.0;
        if ($from !== null) {
            $saldoAwal = $this->saldoSampai($akunId, $from, exclusive: true) * $faktor;
        }

        $query = JurnalBaris::query()
            ->where('akun_id', $akunId)
            ->whereHas('jurnal', function ($q) use ($from, $to) {
                $q->where('status', 'posted');
                if ($from !== null) {
                    $q->whereDate('tanggal', '>=', $from);
                }
                if ($to !== null) {
                    $q->whereDate('tanggal', '<=', $to);
                }
            })
            ->with('jurnal:id,nomor,tanggal,deskripsi')
            ->join('jurnal', 'jurnal.id', '=', 'jurnal_baris.jurnal_id')
            ->orderBy('jurnal.tanggal')
            ->orderBy('jurnal.id')
            ->select('jurnal_baris.*');

        $saldo = $saldoAwal;
        $mutasi = $query->get()->map(function (JurnalBaris $baris) use (&$saldo, $faktor) {
            $saldo += ($baris->debit - $baris->kredit) * $faktor;

            return [
                'tanggal' => $baris->jurnal->tanggal,
                'nomor' => $baris->jurnal->nomor,
                'deskripsi' => $baris->jurnal->deskripsi,
                'debit' => (float) $baris->debit,
                'kredit' => (float) $baris->kredit,
                'saldo' => round($saldo, 2),
            ];
        });

        return [
            'akun' => $akun,
            'saldo_awal' => round($saldoAwal, 2),
            'mutasi' => $mutasi,
            'saldo_akhir' => round($saldo, 2),
        ];
    }

    /**
     * Buku Kas Umum (BKU): akumulasi seluruh baris pada akun kas/bank dalam rentang.
     * Acceptance M1: BKU = akumulasi baris akun kas/bank periode tsb (rekonsiliasi 100%).
     *
     * @return array{saldo_awal: float, mutasi: Collection, total_masuk: float, total_keluar: float, saldo_akhir: float}
     */
    public function bku(?string $from = null, ?string $to = null): array
    {
        $akunKasBankIds = Akun::kasBank()->pluck('id')->all();

        $saldoAwal = 0.0;
        if ($from !== null) {
            foreach ($akunKasBankIds as $id) {
                $saldoAwal += $this->saldoSampai($id, $from, exclusive: true);
            }
        }

        $query = JurnalBaris::query()
            ->whereIn('akun_id', $akunKasBankIds)
            ->whereHas('jurnal', function ($q) use ($from, $to) {
                $q->where('status', 'posted');
                if ($from !== null) {
                    $q->whereDate('tanggal', '>=', $from);
                }
                if ($to !== null) {
                    $q->whereDate('tanggal', '<=', $to);
                }
            })
            ->with('jurnal:id,nomor,tanggal,deskripsi')
            ->join('jurnal', 'jurnal.id', '=', 'jurnal_baris.jurnal_id')
            ->orderBy('jurnal.tanggal')
            ->orderBy('jurnal.id')
            ->select('jurnal_baris.*');

        $saldo = $saldoAwal;
        $totalMasuk = 0.0;
        $totalKeluar = 0.0;

        $mutasi = $query->get()->map(function (JurnalBaris $baris) use (&$saldo, &$totalMasuk, &$totalKeluar) {
            $masuk = (float) $baris->debit;   // kas masuk = debit pada akun kas
            $keluar = (float) $baris->kredit; // kas keluar = kredit pada akun kas
            $saldo += $masuk - $keluar;
            $totalMasuk += $masuk;
            $totalKeluar += $keluar;

            return [
                'tanggal' => $baris->jurnal->tanggal,
                'nomor' => $baris->jurnal->nomor,
                'deskripsi' => $baris->jurnal->deskripsi,
                'masuk' => $masuk,
                'keluar' => $keluar,
                'saldo' => round($saldo, 2),
            ];
        });

        return [
            'saldo_awal' => round($saldoAwal, 2),
            'mutasi' => $mutasi,
            'total_masuk' => round($totalMasuk, 2),
            'total_keluar' => round($totalKeluar, 2),
            'saldo_akhir' => round($saldo, 2),
        ];
    }

    /**
     * Neraca saldo (trial balance): saldo tiap akun. Σdebit harus = Σkredit.
     * Dipakai modul Pelaporan untuk menurunkan Neraca & Laba-Rugi.
     *
     * @return Collection<int, array{akun_id:int, kode:string, nama:string, tipe:string, debit:float, kredit:float}>
     */
    public function neracaSaldo(?string $to = null): Collection
    {
        return Akun::orderBy('kode')->get()->map(function (Akun $akun) use ($to) {
            $net = $this->saldoSampai($akun->id, $to); // debit - kredit
            $saldo = $akun->saldo_normal === 'debit' ? $net : -$net;

            return [
                'akun_id' => $akun->id,
                'kode' => $akun->kode,
                'nama' => $akun->nama,
                'tipe' => $akun->tipe,
                'debit' => $saldo >= 0 && $akun->saldo_normal === 'debit' ? round($saldo, 2) : ($saldo < 0 && $akun->saldo_normal === 'kredit' ? round(-$saldo, 2) : 0.0),
                'kredit' => $saldo >= 0 && $akun->saldo_normal === 'kredit' ? round($saldo, 2) : ($saldo < 0 && $akun->saldo_normal === 'debit' ? round(-$saldo, 2) : 0.0),
            ];
        })->filter(fn ($row) => $row['debit'] != 0.0 || $row['kredit'] != 0.0)->values();
    }

    /**
     * Saldo netto (Σdebit - Σkredit) sebuah akun sampai tanggal tertentu.
     */
    private function saldoSampai(int $akunId, ?string $date = null, bool $exclusive = false): float
    {
        $query = JurnalBaris::query()
            ->where('akun_id', $akunId)
            ->whereHas('jurnal', function ($q) use ($date, $exclusive) {
                $q->where('status', 'posted');
                if ($date !== null) {
                    $q->whereDate('tanggal', $exclusive ? '<' : '<=', $date);
                }
            });

        return round((float) $query->sum('debit') - (float) $query->sum('kredit'), 2);
    }
}
