<?php

namespace Modules\Asrama\Services;

use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Collection;
use Modules\Asrama\Models\BiayaAsrama;
use Modules\Asrama\Models\KamarAsrama;
use Modules\Asrama\Models\PenempatanAsrama;
use Modules\Kesantrian\Models\Santri;

class AsramaService
{
    public function getAvailableRooms(): Collection
    {
        return KamarAsrama::query()
            ->withCount(['penempatan as occupied_count' => fn ($query) => $query->where('is_current', true)])
            ->where('is_active', true)
            ->get()
            ->filter(fn (KamarAsrama $kamar) => $kamar->occupied_count < $kamar->kapasitas)
            ->values();
    }

    public function assignSantriToKamar(Santri $santri, KamarAsrama $kamar, array $attributes = []): PenempatanAsrama
    {
        if ($kamar->tenant_id !== $santri->tenant_id) {
            throw new ModelNotFoundException('Kamar tidak ditemukan untuk tenant ini.');
        }

        $occupied = $kamar->penempatanAktif()->count();
        if ($occupied >= $kamar->kapasitas) {
            throw new \RuntimeException('Kamar sudah penuh.');
        }

        PenempatanAsrama::query()
            ->where('santri_id', $santri->getKey())
            ->where('is_current', true)
            ->update(['is_current' => false, 'tanggal_keluar' => now()->toDateString(), 'status' => 'keluar']);

        return PenempatanAsrama::create(array_merge([
            'tenant_id' => $santri->tenant_id,
            'santri_id' => $santri->getKey(),
            'kamar_id' => $kamar->getKey(),
            'tanggal_masuk' => now()->toDateString(),
            'is_current' => true,
            'status' => 'aktif',
        ], $attributes));
    }

    public function getOccupancySummary(): array
    {
        $totalKamar = KamarAsrama::query()->count();
        $occupied = PenempatanAsrama::query()->where('is_current', true)->count();

        return [
            'totalKamar' => $totalKamar,
            'occupiedBeds' => $occupied,
            'availableBeds' => max(0, KamarAsrama::query()->sum('kapasitas') - $occupied),
        ];
    }

    public function createBiayaForSantri(Santri $santri, KamarAsrama $kamar, string $periode, float $nominal, ?string $jatuhTempo = null): BiayaAsrama
    {
        if ($santri->tenant_id !== $kamar->tenant_id) {
            throw new ModelNotFoundException('Data tenant tidak cocok.');
        }

        return BiayaAsrama::create([
            'tenant_id' => $santri->tenant_id,
            'santri_id' => $santri->getKey(),
            'kamar_id' => $kamar->getKey(),
            'periode' => $periode,
            'nominal' => $nominal,
            'status_pembayaran' => 'belum_bayar',
            'jatuh_tempo' => $jatuhTempo,
        ]);
    }
}
