<?php

namespace Modules\Ledger\Services;

use Illuminate\Support\Facades\DB;
use Modules\Ledger\Models\Akun;
use Modules\Ledger\Models\JurnalBaris;
use Modules\Ledger\Models\PeriodeAkuntansi;
use RuntimeException;

/**
 * G1-06 — Tutup Buku (Period Closing). Memposting JURNAL PENUTUP yang me-nol-kan
 * akun nominal (PENDAPATAN/BEBAN) ke ekuitas (Aset Neto), lalu mengunci periode.
 *
 * Prinsip: semua mutasi tetap lewat JurnalService::postJurnal (pagar saldo/
 * periode/kehalalan), idempoten via (ref_type=PeriodClosing, ref_id=periode).
 * Saldo akun riil (ASET/KEWAJIBAN/EKUITAS) terbawa otomatis (tidak ditutup).
 *
 * Catatan: pembalik akrual otomatis menyusul bersama G2-15 (akrual); penutupan
 * per-dana terikat (wakaf/ZIS ke aset neto terikat) = penyempurnaan lanjutan —
 * versi ini menutup surplus/defisit gabungan ke Aset Neto Tidak Terikat.
 */
class PeriodClosingService
{
    /** Akun ekuitas tujuan surplus/defisit (Aset Neto Tidak Terikat). */
    public const AKUN_EKUITAS_PENUTUP = '3-1000';

    /** Pratinjau jurnal penutup TANPA posting (untuk konfirmasi UI). */
    public function pratinjau(PeriodeAkuntansi $periode): array
    {
        $this->ensureTenantContext();
        [$baris, $surplus] = $this->hitungPenutup($periode);

        return [
            'periode' => $periode->nama,
            'surplus_defisit' => $surplus,
            'jumlah_baris' => count($baris),
            'baris' => $baris,
            'sudah_ditutup' => $periode->isClosed(),
        ];
    }

    /**
     * Tutup buku: posting jurnal penutup (bila ada aktivitas) lalu kunci periode.
     *
     * @return array{periode:PeriodeAkuntansi, jurnal:mixed, surplus_defisit:float}
     */
    public function tutupBuku(PeriodeAkuntansi $periode, ?int $userId = null): array
    {
        $this->ensureTenantContext();

        if ($periode->isClosed()) {
            throw new RuntimeException("Periode \"{$periode->nama}\" sudah ditutup.");
        }

        [$baris, $surplus] = $this->hitungPenutup($periode);

        $jurnal = null;
        if ($baris !== []) {
            // Diposting saat periode MASIH open (pagar period-lock lolos), tanggal
            // = akhir periode. Idempoten via ref PeriodClosing.
            $jurnal = app(JurnalService::class)->postJurnal([
                'tanggal' => $periode->tgl_selesai->toDateString(),
                'deskripsi' => "Jurnal penutup periode {$periode->nama}",
                'tipe_sumber' => 'penutup',
                'ref_type' => 'PeriodClosing',
                'ref_id' => (string) $periode->id,
                'dibuat_oleh' => $userId,
                'baris' => $baris,
            ]);
        }

        // Kunci periode lewat PeriodeService (otoritas tunggal lock) setelah jurnal aman.
        $periode = app(PeriodeService::class)->tutupBuku($periode, $userId);

        return ['periode' => $periode, 'jurnal' => $jurnal, 'surplus_defisit' => $surplus];
    }

    /**
     * Susun baris jurnal penutup + surplus/defisit periode.
     *
     * @return array{0: array<int, array>, 1: float}
     */
    private function hitungPenutup(PeriodeAkuntansi $periode): array
    {
        $from = $periode->tgl_mulai->toDateString();
        $to = $periode->tgl_selesai->toDateString();

        $baris = [];
        $sumNet = 0.0; // Σ(Σdebit − Σkredit) akun nominal = −(surplus)

        $nominal = Akun::query()
            ->whereIn('tipe', ['PENDAPATAN', 'BEBAN'])
            ->where('is_postable', true)
            ->orderBy('kode')
            ->get();

        foreach ($nominal as $akun) {
            $net = $this->movement((int) $akun->id, $from, $to);
            if (abs($net) < 0.005) {
                continue;
            }
            $sumNet += $net;
            // Balik net agar saldo akun jadi 0: net<0 (pendapatan) → debit; net>0 (beban) → kredit.
            $baris[] = [
                'akun_id' => (int) $akun->id,
                'debit' => $net < 0 ? round(-$net, 2) : 0,
                'kredit' => $net > 0 ? round($net, 2) : 0,
                'memo' => 'Penutup '.$akun->kode,
            ];
        }

        if ($baris === []) {
            return [[], 0.0];
        }

        $sumNet = round($sumNet, 2);
        if (abs($sumNet) >= 0.005) {
            // Garis penyeimbang ke ekuitas: debit−kredit harus = sumNet.
            $eq = $this->akunEkuitas();
            $baris[] = [
                'akun_id' => (int) $eq->id,
                'debit' => $sumNet > 0 ? $sumNet : 0,       // sumNet>0 = defisit → kurangi ekuitas
                'kredit' => $sumNet < 0 ? -$sumNet : 0,     // sumNet<0 = surplus → tambah ekuitas
                'memo' => 'Surplus/Defisit periode → Aset Neto',
            ];
        }

        return [$baris, round(-$sumNet, 2)]; // surplus = pendapatan − beban = −sumNet
    }

    private function movement(int $akunId, string $from, string $to): float
    {
        return round((float) JurnalBaris::query()
            ->where('akun_id', $akunId)
            ->whereHas('jurnal', fn ($j) => $j
                ->where('status', 'posted')
                ->whereBetween('tanggal', [$from.' 00:00:00', $to.' 23:59:59']))
            ->sum(DB::raw('debit - kredit')), 2);
    }

    private function akunEkuitas(): Akun
    {
        return Akun::query()->where('kode', self::AKUN_EKUITAS_PENUTUP)
            ->firstOr(fn () => throw new RuntimeException('Akun ekuitas penutup ('.self::AKUN_EKUITAS_PENUTUP.') belum di-seed.'));
    }

    private function ensureTenantContext(): void
    {
        if (! tenancy()->initialized || tenancy()->tenant === null) {
            throw new RuntimeException('Tenant context belum aktif untuk tutup buku.');
        }
    }
}
