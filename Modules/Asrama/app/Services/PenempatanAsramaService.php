<?php

namespace Modules\Asrama\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\Asrama\Models\KamarAsrama;
use Modules\Asrama\Models\PenempatanAsrama;
use Modules\Kesantrian\Models\Santri;

class PenempatanAsramaService
{
    public function tempatkan(Santri|int $santri, KamarAsrama|int $kamar, array $data = []): PenempatanAsrama
    {
        $santriModel = $santri instanceof Santri ? $santri : Santri::query()->findOrFail($santri);
        $kamarModel = $kamar instanceof KamarAsrama ? $kamar : KamarAsrama::query()->findOrFail($kamar);

        return DB::transaction(function () use ($santriModel, $kamarModel, $data) {
            $this->pastikanKamarTidakPenuh($kamarModel, $santriModel->id);

            PenempatanAsrama::query()
                ->where('santri_id', $santriModel->id)
                ->where('is_current', true)
                ->update([
                    'is_current' => false,
                    'status' => 'dipindahkan',
                    'tanggal_keluar' => $data['tanggal_masuk'] ?? now()->toDateString(),
                ]);

            return PenempatanAsrama::create([
                'santri_id' => $santriModel->id,
                'kamar_id' => $kamarModel->id,
                'tanggal_masuk' => $data['tanggal_masuk'] ?? now()->toDateString(),
                'tanggal_keluar' => $data['tanggal_keluar'] ?? null,
                'status' => $data['status'] ?? 'aktif',
                'keterangan' => $data['keterangan'] ?? null,
                'is_current' => true,
            ]);
        });
    }

    public function occupancySummary(): array
    {
        $kamar = KamarAsrama::query()->withCount(['penempatanAktif as penghuni_aktif'])->get();

        $totalKamar = $kamar->count();
        $totalKapasitas = $kamar->sum('kapasitas');
        $totalPenghuni = $kamar->sum('penghuni_aktif');

        return [
            'total_kamar' => $totalKamar,
            'total_kapasitas' => $totalKapasitas,
            'total_penghuni' => $totalPenghuni,
            'occupancy_rate' => $totalKapasitas > 0
                ? round(($totalPenghuni / $totalKapasitas) * 100, 2)
                : 0.0,
        ];
    }

    private function pastikanKamarTidakPenuh(KamarAsrama $kamar, ?int $excludeSantriId = null): void
    {
        $penghuniAktif = $kamar->penempatanAktif()
            ->when($excludeSantriId !== null, fn ($query) => $query->where('santri_id', '!=', $excludeSantriId))
            ->count();

        if ($penghuniAktif >= $kamar->kapasitas) {
            throw ValidationException::withMessages([
                'kamar_id' => 'Kamar asrama sudah penuh.',
            ]);
        }
    }
}
