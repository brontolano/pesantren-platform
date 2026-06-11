<?php

namespace Modules\Kesehatan\Observers;

use Modules\Akademik\Models\PresensiSantri;
use Modules\Kesehatan\Models\KunjunganUks;

/**
 * Observer untuk KunjunganUks.
 * Integrasi otomatis dengan PresensiSantri saat status='istirahat'.
 */
class KunjunganUksObserver
{
    /**
     * Saat kunjungan UKS dibuat atau diupdate:
     * - Jika status='istirahat' → PresensiSantri di-upsert dengan status='sakit' untuk tanggal itu.
     * - Jika status != 'istirahat' (pulang/rujuk) → PresensiSantri dihapus (kembalikan ke status normal/hadir).
     */
    public function created(KunjunganUks $kunjungan): void
    {
        $this->syncPresensi($kunjungan);
    }

    public function updated(KunjunganUks $kunjungan): void
    {
        $this->syncPresensi($kunjungan);
    }

    public function deleted(KunjunganUks $kunjungan): void
    {
        // Saat kunjungan dihapus → hapus mark sakit dari presensi hari itu.
        PresensiSantri::where('tenant_id', $kunjungan->tenant_id)
            ->where('santri_id', $kunjungan->santri_id)
            ->where('tanggal', $kunjungan->tanggal)
            ->where('status', 'sakit')
            ->delete();
    }

    private function syncPresensi(KunjunganUks $kunjungan): void
    {
        if ($kunjungan->status === 'istirahat') {
            // Upsert presensi dengan status sakit
            PresensiSantri::updateOrCreate(
                [
                    'tenant_id' => $kunjungan->tenant_id,
                    'santri_id' => $kunjungan->santri_id,
                    'tanggal' => $kunjungan->tanggal,
                ],
                [
                    'tenant_id' => $kunjungan->tenant_id,
                    'kelas_id' => $kunjungan->santri->kelas_id,
                    'status' => 'sakit',
                    'keterangan' => "UKS: {$kunjungan->keluhan}",
                    'dicatat_oleh' => $kunjungan->pencatat_id,
                ]
            );
        } else {
            // Hapus presensi sakit untuk tanggal itu (kembalikan ke normal/hadir)
            PresensiSantri::where('tenant_id', $kunjungan->tenant_id)
                ->where('santri_id', $kunjungan->santri_id)
                ->where('tanggal', $kunjungan->tanggal)
                ->where('status', 'sakit')
                ->delete();
        }
    }
}
