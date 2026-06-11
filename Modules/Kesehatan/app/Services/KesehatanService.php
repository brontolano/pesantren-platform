<?php

namespace Modules\Kesehatan\Services;

use InvalidArgumentException;
use Modules\Akademik\Models\PresensiSantri;
use Modules\Kesehatan\Models\KunjunganUks;
use Modules\Kesehatan\Models\RiwayatKesehatan;
use Modules\Kesehatan\Models\SesiKonseling;
use Modules\Kesantrian\Models\Santri;
use Illuminate\Support\Collection;

class KesehatanService
{
    // ── Riwayat Kesehatan ──────────────────────────────────────────────────

    public function catatKesehatan(Santri $santri, array $data): RiwayatKesehatan
    {
        return RiwayatKesehatan::create([
            'tenant_id' => $santri->tenant_id,
            'santri_id' => $santri->getKey(),
            'tanggal'   => $data['tanggal'] ?? now()->toDateString(),
            'keluhan'   => $data['keluhan'] ?? null,
            'diagnosa'  => $data['diagnosa'] ?? null,
            'tindakan'  => $data['tindakan'] ?? null,
            'kondisi'   => $data['kondisi'] ?? 'baik',
            'petugas'   => $data['petugas'] ?? null,
            'catatan'   => $data['catatan'] ?? null,
        ]);
    }

    public function getRiwayatSantri(Santri $santri): Collection
    {
        return RiwayatKesehatan::query()
            ->where('santri_id', $santri->getKey())
            ->orderByDesc('tanggal')
            ->get();
    }

    public function getSantriSakit(): Collection
    {
        return RiwayatKesehatan::query()
            ->where('kondisi', 'sakit')
            ->whereDate('tanggal', today())
            ->with('santri')
            ->get();
    }

    // ── Sesi Konseling ─────────────────────────────────────────────────────

    public function jadwalkanKonseling(Santri $santri, array $data): SesiKonseling
    {
        return SesiKonseling::create([
            'tenant_id'   => $santri->tenant_id,
            'santri_id'   => $santri->getKey(),
            'tanggal'     => $data['tanggal'],
            'topik'       => $data['topik'] ?? null,
            'deskripsi'   => $data['deskripsi'] ?? null,
            'status'      => 'terjadwal',
            'konselor_id' => $data['konselor_id'] ?? null,
        ]);
    }

    public function selesaikanKonseling(SesiKonseling $sesi, array $data): SesiKonseling
    {
        $sesi->update([
            'status'           => 'selesai',
            'rekomendasi'      => $data['rekomendasi'] ?? null,
            'catatan_lanjutan' => $data['catatan_lanjutan'] ?? null,
        ]);

        return $sesi->fresh();
    }

    public function getKonselingSantri(Santri $santri): Collection
    {
        return SesiKonseling::query()
            ->where('santri_id', $santri->getKey())
            ->with('konselor')
            ->orderByDesc('tanggal')
            ->get();
    }

    // ── Kunjungan UKS (WP-27) ───────────────────────────────────────────────

    /**
     * Catat kunjungan santri ke UKS.
     * Idempoten per (santri, tanggal) — catat ulang meng-update kunjungan hari itu.
     * Saat status='istirahat' → PresensiSantri otomatis di-update (observer).
     *
     * @throws InvalidArgumentException jika status tidak valid
     */
    public function catatKunjungan(
        Santri $santri,
        string $tanggal,
        string $status,
        array $opts = []
    ): KunjunganUks {
        if (! in_array($status, KunjunganUks::STATUS, true)) {
            throw new InvalidArgumentException("Status kunjungan UKS tidak valid: {$status}");
        }

        return KunjunganUks::updateOrCreate(
            ['santri_id' => $santri->id, 'tanggal' => $tanggal],
            [
                'tenant_id' => $santri->tenant_id,
                'jam_masuk' => $opts['jam_masuk'] ?? now()->format('H:i:s'),
                'keluhan' => $opts['keluhan'] ?? null,
                'tindakan' => $opts['tindakan'] ?? null,
                'obat' => $opts['obat'] ?? null,
                'status' => $status,
                'rujukan_ke' => $opts['rujukan_ke'] ?? null,
                'rujukan_biaya' => $opts['rujukan_biaya'] ?? null,
                'pencatat_id' => $opts['pencatat_id'] ?? null,
            ]
        );
    }

    /**
     * Tandai jam keluar kunjungan UKS.
     */
    public function tutupKunjungan(KunjunganUks $kunjungan, string $jamKeluar = null): KunjunganUks
    {
        $kunjungan->update([
            'jam_keluar' => $jamKeluar ?? now()->format('H:i:s'),
        ]);

        return $kunjungan->fresh();
    }

    /**
     * Dapatkan daftar kunjungan UKS santri pada rentang tanggal.
     */
    public function getDaftarKunjungan(Santri $santri, string $dari, string $sampai): Collection
    {
        return KunjunganUks::where('santri_id', $santri->getKey())
            ->whereBetween('tanggal', [$dari, $sampai])
            ->with('pencatat')
            ->orderByDesc('tanggal')
            ->get();
    }

    /**
     * Dapatkan kunjungan UKS santri hari ini (jika ada).
     */
    public function getKunjunganHariIni(Santri $santri): ?KunjunganUks
    {
        return KunjunganUks::where('santri_id', $santri->getKey())
            ->whereDate('tanggal', now()->toDateString())
            ->first();
    }

    /**
     * Dapatkan daftar santri yang sedang istirahat hari ini.
     */
    public function getDaftarIstirahat(): Collection
    {
        return KunjunganUks::where('tenant_id', tenant('id'))
            ->where('status', 'istirahat')
            ->whereDate('tanggal', now()->toDateString())
            ->whereNull('jam_keluar')
            ->with(['santri', 'pencatat'])
            ->orderByDesc('jam_masuk')
            ->get();
    }

    /**
     * Dapatkan daftar rujukan yang belum ditangani (status=rujuk, jam_keluar=NULL).
     */
    public function getDaftarRujukanTerbuka(): Collection
    {
        return KunjunganUks::where('tenant_id', tenant('id'))
            ->where('status', 'rujuk')
            ->whereNull('jam_keluar')
            ->with(['santri', 'pencatat'])
            ->orderByDesc('created_at')
            ->get();
    }
}
