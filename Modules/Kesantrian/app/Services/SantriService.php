<?php

namespace Modules\Kesantrian\Services;

use Illuminate\Database\Eloquent\Collection;
use Modules\Kesantrian\Models\Alumni;
use Modules\Kesantrian\Models\Santri;
use Modules\Kesantrian\Models\StatusSantri;

class SantriService
{
    /**
     * Buat santri baru.
     */
    public function create(array $data): Santri
    {
        $santri = Santri::create($data);

        // Catat status awal
        StatusSantri::create([
            'tenant_id' => $santri->tenant_id,
            'santri_id' => $santri->id,
            'status' => 'aktif',
            'tanggal_status' => now(),
            'alasan' => 'Pendaftaran baru',
        ]);

        return $santri;
    }

    /**
     * Update data santri.
     */
    public function update(Santri $santri, array $data): Santri
    {
        $santri->update($data);

        return $santri->fresh();
    }

    /**
     * Soft-delete santri.
     */
    public function delete(Santri $santri): bool
    {
        return $santri->delete();
    }

    /**
     * Ubah status santri (cuti, keluar, dikeluarkan).
     */
    public function ubahStatus(Santri $santri, string $status, string $alasan, ?int $durasiCutiHari = null, ?int $createdBy = null): Santri
    {
        $santri->update(['status' => $status]);

        $statusRecord = [
            'tenant_id' => $santri->tenant_id,
            'santri_id' => $santri->id,
            'status' => $status,
            'tanggal_status' => now(),
            'alasan' => $alasan,
            'created_by' => $createdBy,
        ];

        if ($status === 'cuti' && $durasiCutiHari) {
            $statusRecord['durasi_cuti_hari'] = $durasiCutiHari;
            $statusRecord['tanggal_kembali'] = now()->addDays($durasiCutiHari);
        }

        StatusSantri::create($statusRecord);

        return $santri->fresh();
    }

    /**
     * Aktifkan kembali santri dari cuti.
     */
    public function reaktivasi(Santri $santri, ?int $createdBy = null): Santri
    {
        return $this->ubahStatus($santri, 'aktif', 'Reaktivasi dari cuti', null, $createdBy);
    }

    /**
     * Luluskan santri → buat record alumni.
     */
    public function luluskan(Santri $santri, string $tahunLulus, ?string $statusLanjutan = null, ?string $sekolahTujuan = null, ?int $createdBy = null): Santri
    {
        $this->ubahStatus($santri, 'lulus', 'Kelulusan', null, $createdBy);

        Alumni::create([
            'tenant_id' => $santri->tenant_id,
            'santri_id' => $santri->id,
            'tahun_lulus' => $tahunLulus,
            'status_lanjutan' => $statusLanjutan,
            'sekolah_tujuan' => $sekolahTujuan,
        ]);

        return $santri->fresh();
    }

    /**
     * List santri per kelas.
     */
    public function listPerKelas(int $kelasId): Collection
    {
        return Santri::where('kelas_id', $kelasId)
            ->where('status', 'aktif')
            ->orderBy('nama')
            ->get();
    }

    /**
     * Hitung santri per status.
     */
    public function hitungPerStatus(): array
    {
        return Santri::query()
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status')
            ->toArray();
    }
}
