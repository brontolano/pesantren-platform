<?php

namespace Modules\Ledger\Services;

use Illuminate\Support\Facades\DB;
use Modules\Ledger\Exceptions\AnggaranTerlampauiException;
use Modules\Ledger\Models\Anggaran;
use Modules\Ledger\Models\JurnalBaris;

/**
 * Guard anggaran RKAM (adaptasi Budget Control ERPNext) — bandingkan REALISASI
 * (akumulasi beban dari jurnal) dengan PAGU (tabel anggaran) per akun + dimensi
 * + tahun. Bila proyeksi (realisasi + transaksi baru) melampaui pagu:
 *   - mode 'peringatan' → kembalikan status 'peringatan' (boleh lanjut),
 *   - mode 'blokir'     → assertBoleh() melempar AnggaranTerlampauiException.
 *
 * Bila TIDAK ada baris anggaran untuk kombinasi tsb → 'ok' (tanpa batas) →
 * alur lama tak terpengaruh. Murni additif & non-invasif.
 */
class AnggaranGuardService
{
    /** Realisasi beban (debit − kredit) untuk akun + dimensi pada tahun kalender. */
    public function realisasi(int $akunId, ?int $divisiId, ?int $sumberDanaId, string $tahun): float
    {
        $year = (int) substr($tahun, 0, 4);

        $query = JurnalBaris::query()
            ->where('akun_id', $akunId)
            ->whereHas('jurnal', fn ($j) => $j->whereYear('tanggal', $year));

        if ($divisiId !== null) {
            $query->where('divisi_id', $divisiId);
        }
        if ($sumberDanaId !== null) {
            $query->where('sumber_dana_id', $sumberDanaId);
        }

        return round((float) $query->sum(DB::raw('debit - kredit')), 2);
    }

    /**
     * @return array{ada_anggaran:bool, status:string, anggaran?:float, realisasi?:float, proyeksi?:float, sisa?:float, mode?:string}
     */
    public function cek(int $akunId, ?int $divisiId, ?int $sumberDanaId, string $tahun, float $tambahan = 0.0): array
    {
        $anggaran = Anggaran::query()
            ->where('akun_id', $akunId)
            ->where('tahun_anggaran', $tahun)
            ->where(fn ($w) => $divisiId === null ? $w->whereNull('divisi_id') : $w->where('divisi_id', $divisiId))
            ->where(fn ($w) => $sumberDanaId === null ? $w->whereNull('sumber_dana_id') : $w->where('sumber_dana_id', $sumberDanaId))
            ->first();

        if ($anggaran === null) {
            return ['ada_anggaran' => false, 'status' => 'ok'];
        }

        $pagu = (float) $anggaran->nominal_anggaran;
        $realisasi = $this->realisasi($akunId, $divisiId, $sumberDanaId, $tahun);
        $proyeksi = round($realisasi + $tambahan, 2);
        $lampaui = $proyeksi > $pagu + 0.005;

        return [
            'ada_anggaran' => true,
            'anggaran' => $pagu,
            'realisasi' => $realisasi,
            'proyeksi' => $proyeksi,
            'sisa' => round($pagu - $realisasi, 2),
            'mode' => $anggaran->mode,
            'status' => $lampaui ? ($anggaran->mode === 'blokir' ? 'blokir' : 'peringatan') : 'ok',
        ];
    }

    /**
     * Tegakkan pagu: lempar bila status 'blokir'. Kembalikan hasil cek (untuk
     * pemanggil menampilkan peringatan). Aman dipanggil tanpa anggaran (no-op).
     */
    public function assertBoleh(int $akunId, ?int $divisiId, ?int $sumberDanaId, string $tahun, float $tambahan): array
    {
        $hasil = $this->cek($akunId, $divisiId, $sumberDanaId, $tahun, $tambahan);

        if (($hasil['status'] ?? 'ok') === 'blokir') {
            throw new AnggaranTerlampauiException(sprintf(
                'Anggaran RKAM terlampaui: pagu Rp%s, sudah terealisasi Rp%s, transaksi ini Rp%s → proyeksi Rp%s melebihi pagu. Belanja diblokir.',
                number_format((float) $hasil['anggaran'], 0, ',', '.'),
                number_format((float) $hasil['realisasi'], 0, ',', '.'),
                number_format($tambahan, 0, ',', '.'),
                number_format((float) $hasil['proyeksi'], 0, ',', '.'),
            ));
        }

        return $hasil;
    }
}
