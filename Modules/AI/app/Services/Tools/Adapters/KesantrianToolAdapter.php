<?php

namespace Modules\AI\Services\Tools\Adapters;

use App\Models\User;
use Modules\Kesantrian\Models\Santri;

/**
 * T-A033 — adapter READ-ONLY ke modul Kesantrian (Blueprint §6):
 * getSantri (TERBATAS) — hanya anak tertaut wali penanya; tautan AccessWali
 * sudah divalidasi ToolRegistry SEBELUM adapter dipanggil (peran tool = wali).
 *
 * Catatan deviasi blueprint (diperiksa pada kelas nyata): SantriService tidak
 * punya getter per-santri (create/update/ubahStatus/listPerKelas) — profil
 * dibaca dari model Santri langsung (Eloquent READ) dengan FIELD MINIMAL,
 * tanpa NIK/alamat/PII sensitif. TIDAK ADA tulisan apa pun di kelas ini.
 */
class KesantrianToolAdapter
{
    /**
     * @param  array<string, mixed>  $args
     * @return array{0: array<string, mixed>, 1: string}
     */
    public function getSantri(array $args, ?User $pemanggil = null): array
    {
        $santri = Santri::query()
            ->with('kelas')
            ->findOrFail((int) $args['santri_id']);

        return [
            [
                'nis' => (string) $santri->nis,
                'nama' => (string) $santri->nama,
                'jenis_kelamin' => (string) $santri->jenis_kelamin,
                'status' => (string) $santri->status,
                'kelas' => $santri->kelas?->nama_kelas,
                'tahun_angkatan' => $santri->tahun_angkatan,
            ],
            'Modules\Kesantrian — Santri (read-only, field terbatas)',
        ];
    }
}
