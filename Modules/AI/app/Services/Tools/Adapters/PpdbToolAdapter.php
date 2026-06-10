<?php

namespace Modules\AI\Services\Tools\Adapters;

use App\Models\User;
use Modules\PPDB\Models\CalonSantri;

/**
 * T-A033 — adapter READ-ONLY ke modul PPDB (Blueprint §6).
 *
 * Catatan deviasi blueprint (diperiksa pada kelas nyata):
 * PpdbStatusTrackingService berbasis TOKEN tautan publik (issueTrackingLink/
 * getStatusSnapshot(token)) — tidak cocok untuk pemanggilan ber-identitas dari
 * chatbot. getStatusPPDB membaca model CalonSantri langsung berdasarkan
 * nomor_pendaftaran (Eloquent READ). Pemanggil berperan 'peserta' (tanpa peran
 * staf) HANYA boleh melihat pendaftarannya sendiri (kolom user_id).
 * TIDAK ADA tulisan apa pun di kelas ini.
 */
class PpdbToolAdapter
{
    /**
     * @param  array<string, mixed>  $args
     * @return array{0: array<string, mixed>, 1: string}
     */
    public function getStatusPPDB(array $args, ?User $pemanggil = null): array
    {
        $query = CalonSantri::query()
            ->where('nomor_pendaftaran', (string) $args['nomor_pendaftaran']);

        // Peserta murni (bukan staf): batasi ke pendaftaran milik sendiri.
        $peranStaf = ['admin_pesantren', 'tu', 'kepala'];
        if (
            $pemanggil !== null
            && method_exists($pemanggil, 'hasRole')
            && $pemanggil->hasRole('peserta')
            && array_intersect($pemanggil->getRoleNames()->all(), $peranStaf) === []
        ) {
            $query->where('user_id', $pemanggil->getKey());
        }

        $calon = $query->firstOrFail();

        return [
            [
                'nomor_pendaftaran' => (string) $calon->nomor_pendaftaran,
                'nama' => (string) $calon->nama,
                'status_pendaftaran' => (string) $calon->status_pendaftaran,
                'gelombang' => $calon->gelombang,
                'tahun_ajaran' => $calon->tahun_ajaran,
                'program_diminati' => $calon->program_diminati,
                'tanggal_keputusan' => $calon->tanggal_keputusan?->toDateString(),
            ],
            'Modules\PPDB — CalonSantri (read-only)',
        ];
    }
}
