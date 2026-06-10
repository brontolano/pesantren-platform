<?php

namespace Modules\AI\Services\Tools\Adapters;

use App\Models\User;
use Modules\Kegiatan\Models\IzinSantri;
use Modules\Kegiatan\Models\PresensiKegiatan;

/**
 * T-A033 — adapter READ-ONLY ke modul Kegiatan (Blueprint §6).
 *
 * Catatan deviasi blueprint (diperiksa pada kelas nyata): KegiatanService
 * hanya menyediakan baca per-KEGIATAN (getPresensiKegiatan) dan getIzinPending
 * global — tidak ada baca per-santri. getKehadiran/getIzin membaca model
 * PresensiKegiatan/IzinSantri langsung (Eloquent READ, diperbolehkan bila
 * service tak menyediakan). TIDAK ADA tulisan apa pun di kelas ini.
 */
class KegiatanToolAdapter
{
    /**
     * @param  array<string, mixed>  $args
     * @return array{0: array<string, mixed>, 1: string}
     */
    public function getKehadiran(array $args, ?User $pemanggil = null): array
    {
        $rows = PresensiKegiatan::query()
            ->where('santri_id', (int) $args['santri_id'])
            ->with('kegiatan')
            ->orderByDesc('id')
            ->limit(30)
            ->get();

        $rekap = $rows->groupBy('status')->map(fn ($g) => $g->count())->all();

        $daftar = $rows->map(fn (PresensiKegiatan $p): array => [
            'kegiatan' => (string) ($p->kegiatan->nama ?? '-'),
            'status' => (string) $p->status,
            'keterangan' => $p->keterangan,
        ])->values()->all();

        return [
            [
                'rekap' => $rekap,
                'daftar' => $daftar,
                'jumlah' => count($daftar),
            ],
            'Modules\Kegiatan — PresensiKegiatan (read-only)',
        ];
    }

    /**
     * @param  array<string, mixed>  $args
     * @return array{0: array<string, mixed>, 1: string}
     */
    public function getIzin(array $args, ?User $pemanggil = null): array
    {
        $rows = IzinSantri::query()
            ->where('santri_id', (int) $args['santri_id'])
            ->orderByDesc('tanggal_mulai')
            ->limit(10)
            ->get();

        $daftar = $rows->map(fn (IzinSantri $iz): array => [
            'jenis' => (string) $iz->jenis_izin,
            'tanggal_mulai' => $iz->tanggal_mulai?->toDateString(),
            'tanggal_selesai' => $iz->tanggal_selesai?->toDateString(),
            'status' => (string) $iz->status,
            'sudah_kembali' => (bool) $iz->sudah_kembali,
        ])->values()->all();

        return [
            [
                'daftar' => $daftar,
                'jumlah' => count($daftar),
            ],
            'Modules\Kegiatan — IzinSantri (read-only)',
        ];
    }
}
