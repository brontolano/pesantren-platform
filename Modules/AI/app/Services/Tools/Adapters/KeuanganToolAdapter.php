<?php

namespace Modules\AI\Services\Tools\Adapters;

use App\Models\User;
use Modules\Keuangan\Models\Tagihan;

/**
 * T-A033 — adapter READ-ONLY ke modul Keuangan (Blueprint §6):
 * getTagihan/getStatusBayar → Tagihan::belumLunas() + accessor resmi
 * (total_dibayar hanya pembayaran TERVERIFIKASI, sisa_tagihan).
 *
 * Catatan deviasi blueprint: LaporanKeuanganService tidak punya method
 * per-santri (hanya laporan harian/mingguan/bulanan/overview), maka status
 * bayar per santri dibaca dari model Tagihan + accessor-nya — query Eloquent
 * READ pada model modul lain diperbolehkan bila service tak menyediakan
 * (Aturan Emas, dicatat). TIDAK ADA tulisan apa pun di kelas ini.
 */
class KeuanganToolAdapter
{
    /**
     * @param  array<string, mixed>  $args
     * @return array{0: array<string, mixed>, 1: string}
     */
    public function getTagihan(array $args, ?User $pemanggil = null): array
    {
        $rows = Tagihan::query()
            ->belumLunas()
            ->where('santri_id', (int) $args['santri_id'])
            ->with('pembayaran')
            ->orderBy('jatuh_tempo')
            ->get();

        $daftar = $rows->map(fn (Tagihan $t): array => [
            'jenis' => (string) $t->jenis_tagihan,
            'keterangan' => $t->keterangan,
            'nominal' => (float) $t->nominal_akhir,
            'sisa' => (float) $t->sisa_tagihan,
            'jatuh_tempo' => $t->jatuh_tempo?->toDateString(),
            'status' => (string) $t->status,
        ])->values()->all();

        return [
            [
                'daftar' => $daftar,
                'jumlah' => count($daftar),
                'total_sisa' => round(array_sum(array_column($daftar, 'sisa')), 2),
            ],
            'Modules\Keuangan — Tagihan::belumLunas()',
        ];
    }

    /**
     * @param  array<string, mixed>  $args
     * @return array{0: array<string, mixed>, 1: string}
     */
    public function getStatusBayar(array $args, ?User $pemanggil = null): array
    {
        $rows = Tagihan::query()
            ->where('santri_id', (int) $args['santri_id'])
            ->where('status', '!=', 'batal')
            ->with('pembayaran')
            ->get();

        $totalTagihan = round((float) $rows->sum(fn (Tagihan $t) => (float) $t->nominal_akhir), 2);
        $totalDibayar = round((float) $rows->sum(fn (Tagihan $t) => (float) $t->total_dibayar), 2);

        return [
            [
                'jumlah_tagihan' => $rows->count(),
                'jumlah_lunas' => $rows->where('status', 'lunas')->count(),
                'jumlah_belum_lunas' => $rows->whereIn('status', ['belum_bayar', 'sebagian'])->count(),
                'total_tagihan' => $totalTagihan,
                'total_dibayar' => $totalDibayar,
                'total_sisa' => round($totalTagihan - $totalDibayar, 2),
            ],
            'Modules\Keuangan — Tagihan + pembayaran terverifikasi',
        ];
    }
}
