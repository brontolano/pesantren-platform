<?php

namespace Modules\Akademik\Services;

use InvalidArgumentException;
use Modules\Akademik\Models\RubrikKriteria;
use Modules\Akademik\Models\RubrikSkor;
use Modules\Kesantrian\Models\Santri;
use RuntimeException;

/**
 * G3-18 — Penilaian rubrik kompetensi berbobot.
 * Nilai agregat = Σ(skor × bobot) / Σbobot atas kriteria yang DINILAI.
 * Hanya kriteria aktif & berbobot > 0 yang ikut (bobot ≤ 0 diabaikan agar tak
 * mengencerkan/merusak rata-rata). Σbobot = 0 → agregat 0 (bukan div-by-zero).
 */
class RubrikService
{
    public function catatSkor(RubrikKriteria|int $kriteria, Santri|int $santri, float $skor, ?int $periodeAkademikId = null): RubrikSkor
    {
        $this->ensureTenantContext();
        $kriteria = $kriteria instanceof RubrikKriteria ? $kriteria : RubrikKriteria::query()->findOrFail($kriteria);
        $santriId = $santri instanceof Santri ? $santri->id : (int) $santri;

        if ($skor < 0 || $skor > 100) {
            throw new InvalidArgumentException('Skor rubrik harus 0..100.');
        }

        return RubrikSkor::updateOrCreate(
            [
                'kriteria_id' => $kriteria->id,
                'santri_id' => $santriId,
                'periode_akademik_id' => $periodeAkademikId ?? $kriteria->periode_akademik_id,
            ],
            ['skor' => round($skor, 2)],
        );
    }

    /**
     * Nilai agregat rubrik untuk satu santri pada satu periode (opsional pelajaran).
     * Membobot hanya kriteria AKTIF berbobot > 0 yang punya skor.
     */
    public function agregat(Santri|int $santri, ?int $periodeAkademikId = null, ?int $pelajaranId = null): float
    {
        $this->ensureTenantContext();
        $santriId = $santri instanceof Santri ? $santri->id : (int) $santri;

        $skorRows = RubrikSkor::query()
            ->where('santri_id', $santriId)
            ->when($periodeAkademikId !== null, fn ($q) => $q->where('periode_akademik_id', $periodeAkademikId))
            ->with('kriteria')
            ->get();

        $totalBerbobot = 0.0;
        $totalBobot = 0.0;
        foreach ($skorRows as $row) {
            $kriteria = $row->kriteria;
            if ($kriteria === null || ! $kriteria->is_active) {
                continue;
            }
            if ($pelajaranId !== null && (int) $kriteria->pelajaran_id !== $pelajaranId) {
                continue;
            }
            $bobot = (float) $kriteria->bobot;
            if ($bobot <= 0) {
                continue; // bobot non-positif tidak dihitung
            }
            // Clamp 0..100 (pertahanan berlapis bila skor masuk lewat jalur non-catatSkor).
            $skor = max(0.0, min(100.0, (float) $row->skor));
            $totalBerbobot += $skor * $bobot;
            $totalBobot += $bobot;
        }

        if ($totalBobot <= 0) {
            return 0.0; // tak ada kriteria berbobot → 0 (hindari div-by-zero)
        }

        return round($totalBerbobot / $totalBobot, 2);
    }

    private function ensureTenantContext(): void
    {
        if (! tenancy()->initialized || tenancy()->tenant === null) {
            throw new RuntimeException('Tenant context belum aktif.');
        }
    }
}
