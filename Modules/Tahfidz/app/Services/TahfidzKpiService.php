<?php

namespace Modules\Tahfidz\Services;

use InvalidArgumentException;
use Modules\Kesantrian\Models\Santri;
use Modules\Tahfidz\Models\HafalanTahfidz;
use Modules\Tahfidz\Models\TahfidzTarget;
use RuntimeException;

/**
 * G3-18 — KPI Tahfidz: target hafalan per santri vs realisasi (hafalan selesai).
 * Tidak mengubah TahfidzService/HafalanTahfidz yang sudah teruji.
 */
class TahfidzKpiService
{
    public function setTarget(Santri|int $santri, string $periode, int $targetSurah, ?string $keterangan = null): TahfidzTarget
    {
        $this->ensureTenantContext();
        $santriId = $santri instanceof Santri ? $santri->id : (int) $santri;
        if ($targetSurah < 0) {
            throw new InvalidArgumentException('Target surah tidak boleh negatif.');
        }

        return TahfidzTarget::updateOrCreate(
            ['santri_id' => $santriId, 'periode' => $periode],
            ['target_surah' => $targetSurah, 'keterangan' => $keterangan],
        );
    }

    /**
     * Realisasi = jumlah hafalan berstatus 'selesai'.
     * Bila $periode diberikan (tahun di-parse dari awal string, mis. "2026" /
     * "2026-S1"), realisasi DIBATASI ke hafalan yang tanggal_selesai-nya pada
     * tahun itu — agar capaian per-periode tidak terkontaminasi pencapaian tahun
     * lain (hafalan tanpa tanggal_selesai tidak diatribusikan ke periode mana pun).
     * Tanpa $periode → kumulatif seluruh hafalan selesai.
     */
    public function realisasi(Santri|int $santri, ?string $periode = null): int
    {
        $this->ensureTenantContext();
        $santriId = $santri instanceof Santri ? $santri->id : (int) $santri;

        $q = HafalanTahfidz::where('santri_id', $santriId)->where('status', 'selesai');
        if ($periode !== null && preg_match('/^(\d{4})/', $periode, $m)) {
            $q->whereYear('tanggal_selesai', (int) $m[1]);
        }

        return $q->count();
    }

    /**
     * Capaian KPI (%) = realisasi(periode) / target × 100. Target 0 → 0
     * (hindari div-by-zero). Realisasi difilter per-periode (lihat realisasi()).
     *
     * @return array{target:int, realisasi:int, capaian:float}
     */
    public function capaian(Santri|int $santri, string $periode): array
    {
        $this->ensureTenantContext();
        $santriId = $santri instanceof Santri ? $santri->id : (int) $santri;

        $target = (int) (TahfidzTarget::where('santri_id', $santriId)->where('periode', $periode)->value('target_surah') ?? 0);
        $realisasi = $this->realisasi($santriId, $periode);
        $capaian = $target > 0 ? round($realisasi / $target * 100, 2) : 0.0;

        return ['target' => $target, 'realisasi' => $realisasi, 'capaian' => $capaian];
    }

    private function ensureTenantContext(): void
    {
        if (! tenancy()->initialized || tenancy()->tenant === null) {
            throw new RuntimeException('Tenant context belum aktif.');
        }
    }
}
