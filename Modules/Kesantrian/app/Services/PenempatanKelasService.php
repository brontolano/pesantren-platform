<?php

namespace Modules\Kesantrian\Services;

use App\Models\Setting;
use Illuminate\Support\Facades\DB;
use Modules\Kesantrian\Models\Kelas;
use Modules\Kesantrian\Models\PenempatanKelas;
use Modules\Kesantrian\Models\Santri;

class PenempatanKelasService
{
    /**
     * Tempatkan santri ke kelas (penempatan awal atau mutasi).
     */
    public function tempatkan(Santri $santri, Kelas $kelas, string $tahunAkademik, ?string $semester = null, ?string $alasanMutasi = null): PenempatanKelas
    {
        return DB::transaction(function () use ($santri, $kelas, $tahunAkademik, $semester, $alasanMutasi) {
            // Nonaktifkan penempatan sebelumnya
            PenempatanKelas::where('santri_id', $santri->id)
                ->where('is_current', true)
                ->update(['is_current' => false]);

            // Buat penempatan baru
            $penempatan = PenempatanKelas::create([
                'tenant_id' => $santri->tenant_id,
                'santri_id' => $santri->id,
                'kelas_id' => $kelas->id,
                'semester' => $semester,
                'tahun_akademik' => $tahunAkademik,
                'tanggal_penempatan' => now(),
                'alasan_mutasi' => $alasanMutasi,
                'is_current' => true,
            ]);

            // Update kelas_id di santri
            $santri->update(['kelas_id' => $kelas->id]);

            return $penempatan;
        });
    }

    /**
     * Mutasi santri ke kelas lain.
     */
    public function mutasi(Santri $santri, Kelas $kelasBaru, string $tahunAkademik, string $alasan, ?string $semester = null): PenempatanKelas
    {
        return $this->tempatkan($santri, $kelasBaru, $tahunAkademik, $semester, $alasan);
    }

    /**
     * Bulk assign santri ke kelas.
     */
    public function bulkTempatkan(array $santriIds, Kelas $kelas, string $tahunAkademik, ?string $semester = null): int
    {
        $count = 0;
        foreach ($santriIds as $santriId) {
            $santri = Santri::find($santriId);
            if ($santri && $santri->isAktif()) {
                $this->tempatkan($santri, $kelas, $tahunAkademik, $semester);
                $count++;
            }
        }
        return $count;
    }

    /**
     * Engine kenaikan kelas — mode ditentukan oleh setting tenant.
     *
     * Mode:
     * - 'linear': semua santri naik ke kelas berikutnya (promosi normal)
     * - 'muadahah': santri bisa loncat/turun kelas berdasarkan nilai (configurable)
     *
     * @param string $tahunAkademikBaru e.g. "2027/2028"
     * @param array $pemetaan Mapping kelas lama → kelas baru. Format: [old_kelas_id => new_kelas_id]
     * @param array|null $overrides Khusus mode muadahah: [santri_id => target_kelas_id] untuk loncat/turun
     */
    public function prosesKenaikanKelas(string $tahunAkademikBaru, array $pemetaan, ?array $overrides = null): array
    {
        $mode = $this->getMode();
        $hasil = ['berhasil' => 0, 'gagal' => 0, 'detail' => []];

        foreach ($pemetaan as $kelasLamaId => $kelasBaruId) {
            $kelasBaru = Kelas::find($kelasBaruId);
            if (! $kelasBaru) {
                continue;
            }

            $santriDiKelas = Santri::where('kelas_id', $kelasLamaId)
                ->where('status', 'aktif')
                ->get();

            foreach ($santriDiKelas as $santri) {
                $targetKelas = $kelasBaru;

                // Mode muadahah: cek override individual
                if ($mode === 'muadahah' && $overrides && isset($overrides[$santri->id])) {
                    $targetKelas = Kelas::find($overrides[$santri->id]);
                    if (! $targetKelas) {
                        $hasil['gagal']++;
                        $hasil['detail'][] = "Santri {$santri->nis}: kelas target override tidak ditemukan";
                        continue;
                    }
                }

                $alasan = $mode === 'muadahah' && $overrides && isset($overrides[$santri->id])
                    ? "Kenaikan kelas mode Mu'adalah (override)"
                    : "Kenaikan kelas mode " . ucfirst($mode);

                $kelasAsal = optional($santri->kelas)->nama_kelas ?? '?';

                $this->tempatkan($santri, $targetKelas, $tahunAkademikBaru, null, $alasan);
                $hasil['berhasil']++;
                $hasil['detail'][] = "Santri {$santri->nis}: {$kelasAsal} -> {$targetKelas->nama_kelas}";
            }
        }

        return $hasil;
    }

    /**
     * Ambil mode penempatan kelas dari setting tenant.
     * Default: 'linear'
     */
    public function getMode(): string
    {
        $setting = Setting::where('key', 'kelas_mode')->first();
        return $setting?->value ?? 'linear';
    }

    /**
     * Riwayat penempatan santri.
     */
    public function riwayat(Santri $santri): \Illuminate\Database\Eloquent\Collection
    {
        return PenempatanKelas::where('santri_id', $santri->id)
            ->with('kelas')
            ->orderByDesc('tanggal_penempatan')
            ->get();
    }
}
