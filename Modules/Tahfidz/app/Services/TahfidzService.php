<?php

namespace Modules\Tahfidz\Services;

use Modules\Tahfidz\Models\HafalanTahfidz;
use Modules\Tahfidz\Models\PenilaianTahfidz;
use Modules\Kesantrian\Models\Santri;
use Illuminate\Support\Collection;

class TahfidzService
{
    // ── Hafalan ─────────────────────────────────────────────────────────────

    public function createHafalan(Santri $santri, array $data): HafalanTahfidz
    {
        return HafalanTahfidz::create([
            'tenant_id'      => $santri->tenant_id,
            'santri_id'      => $santri->getKey(),
            'surah'          => $data['surah'] ?? null,
            'ayat_awal'      => $data['ayat_awal'] ?? null,
            'ayat_akhir'     => $data['ayat_akhir'] ?? null,
            'tanggal_target' => $data['tanggal_target'] ?? null,
            'status'         => $data['status'] ?? 'direncanakan',
            'catatan'        => $data['catatan'] ?? null,
        ]);
    }

    public function updateHafalan(HafalanTahfidz $hafalan, array $data): HafalanTahfidz
    {
        $hafalan->update(array_filter([
            'surah'           => $data['surah'] ?? $hafalan->surah,
            'ayat_awal'       => $data['ayat_awal'] ?? $hafalan->ayat_awal,
            'ayat_akhir'      => $data['ayat_akhir'] ?? $hafalan->ayat_akhir,
            'tanggal_target'  => $data['tanggal_target'] ?? $hafalan->tanggal_target,
            'tanggal_selesai' => $data['tanggal_selesai'] ?? $hafalan->tanggal_selesai,
            'status'          => $data['status'] ?? $hafalan->status,
            'catatan'         => $data['catatan'] ?? $hafalan->catatan,
        ], fn ($v) => $v !== null));

        return $hafalan->fresh();
    }

    public function selesaikanHafalan(HafalanTahfidz $hafalan): HafalanTahfidz
    {
        $hafalan->update([
            'status'          => 'selesai',
            'tanggal_selesai' => now()->toDateString(),
        ]);

        return $hafalan->fresh();
    }

    public function getSantriHafalan(Santri $santri): Collection
    {
        return HafalanTahfidz::query()
            ->where('santri_id', $santri->getKey())
            ->with('penilaian')
            ->orderByDesc('tanggal_target')
            ->get();
    }

    public function getRingkasanHafalan(Santri $santri): array
    {
        $hafalan = HafalanTahfidz::query()
            ->where('santri_id', $santri->getKey())
            ->get();

        return [
            'total'       => $hafalan->count(),
            'selesai'     => $hafalan->where('status', 'selesai')->count(),
            'direncanakan'=> $hafalan->where('status', 'direncanakan')->count(),
            'berjalan'    => $hafalan->where('status', 'berjalan')->count(),
        ];
    }

    // ── Penilaian ────────────────────────────────────────────────────────────

    public function addPenilaian(HafalanTahfidz $hafalan, array $data): PenilaianTahfidz
    {
        return PenilaianTahfidz::create([
            'tenant_id'  => $hafalan->tenant_id,
            'santri_id'  => $hafalan->santri_id,
            'hafalan_id' => $hafalan->getKey(),
            'jenis'      => $data['jenis'] ?? 'murajaah',
            'nilai'      => $data['nilai'] ?? null,
            'catatan'    => $data['catatan'] ?? null,
            'tanggal'    => $data['tanggal'] ?? now()->toDateString(),
            'input_by'   => $data['input_by'] ?? null,
        ]);
    }

    public function getRataRataNilai(Santri $santri): ?float
    {
        $avg = PenilaianTahfidz::query()
            ->where('santri_id', $santri->getKey())
            ->whereNotNull('nilai')
            ->avg('nilai');

        return $avg !== null ? round((float) $avg, 2) : null;
    }
}
