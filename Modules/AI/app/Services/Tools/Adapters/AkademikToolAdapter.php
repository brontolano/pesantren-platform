<?php

namespace Modules\AI\Services\Tools\Adapters;

use App\Models\User;
use Modules\Akademik\Models\JadwalPelajaran;
use Modules\Akademik\Models\Nilai;

/**
 * T-A033 — adapter READ-ONLY ke modul Akademik (Blueprint §6).
 *
 * Catatan deviasi blueprint (diperiksa pada kelas nyata):
 * - JadwalPelajaranService hanya punya simpan() (tulis) — TIDAK ada method
 *   baca; getJadwal membaca model JadwalPelajaran langsung (Eloquent READ,
 *   diperbolehkan bila service tak menyediakan).
 * - NilaiService hanya simpan()/hitungNilaiAkhir(); RaporService hanya
 *   generateForKelas() (tulis) — getNilai membaca model Nilai langsung.
 * TIDAK ADA tulisan apa pun di kelas ini.
 */
class AkademikToolAdapter
{
    /**
     * @param  array<string, mixed>  $args
     * @return array{0: array<string, mixed>, 1: string}
     */
    public function getJadwal(array $args, ?User $pemanggil = null): array
    {
        $rows = JadwalPelajaran::query()
            ->where('kelas_id', (int) $args['kelas_id'])
            ->when(
                isset($args['hari']) && $args['hari'] !== '',
                fn ($q) => $q->whereRaw('LOWER(hari) = ?', [mb_strtolower((string) $args['hari'])]),
            )
            ->with(['pelajaran', 'kelas'])
            ->orderBy('hari')
            ->orderBy('jam_mulai')
            ->get();

        $daftar = $rows->map(fn (JadwalPelajaran $j): array => [
            'hari' => (string) $j->hari,
            'jam_mulai' => (string) $j->jam_mulai,
            'jam_selesai' => (string) $j->jam_selesai,
            'pelajaran' => (string) ($j->pelajaran->nama ?? '-'),
            'ruangan' => $j->ruangan,
        ])->values()->all();

        return [
            [
                'kelas' => (string) ($rows->first()?->kelas?->nama_kelas ?? ''),
                'daftar' => $daftar,
                'jumlah' => count($daftar),
            ],
            'Modules\Akademik — JadwalPelajaran (read-only)',
        ];
    }

    /**
     * @param  array<string, mixed>  $args
     * @return array{0: array<string, mixed>, 1: string}
     */
    public function getNilai(array $args, ?User $pemanggil = null): array
    {
        $rows = Nilai::query()
            ->where('santri_id', (int) $args['santri_id'])
            ->when(
                isset($args['periode_akademik_id']),
                fn ($q) => $q->where('periode_akademik_id', (int) $args['periode_akademik_id']),
            )
            ->with('pelajaran')
            ->get();

        $daftar = $rows->map(fn (Nilai $n): array => [
            'pelajaran' => (string) ($n->pelajaran->nama ?? '-'),
            'nilai_harian' => $n->nilai_harian,
            'nilai_uts' => $n->nilai_uts,
            'nilai_uas' => $n->nilai_uas,
            'nilai_akhir' => $n->nilai_akhir,
        ])->values()->all();

        $akhir = array_values(array_filter(
            array_column($daftar, 'nilai_akhir'),
            static fn ($v): bool => $v !== null,
        ));

        return [
            [
                'daftar' => $daftar,
                'jumlah' => count($daftar),
                'rata_rata' => $akhir === [] ? null : round(array_sum($akhir) / count($akhir), 2),
            ],
            'Modules\Akademik — Nilai (read-only)',
        ];
    }
}
