<?php

namespace Modules\Akademik\Services;

use Modules\Akademik\Models\NgajiSesiPeserta;
use Modules\Akademik\Models\SetoranSorogan;
use Modules\Kesantrian\Models\Santri;

/**
 * Issue #330 — Ringkasan Sorogan & Ngaji Kitab per santri.
 *
 * Dipakai dua arah:
 * - Rapor Diniyah (blok 'sorogan') via ringkasan(santri, dari, sampai).
 * - Halaman Progres Sorogan (riwayat + rekap per kitab) via progresPerKitab().
 *
 * Skala nilai 0–100 (konsisten PenilaianTahfidz). Rata-rata setoran =
 * rata-rata nilai_kelancaran/nilai_makna/nilai_nahwu yang terisi.
 */
class SoroganService
{
    /**
     * Rekap progres per kitab: posisi terakhir, jumlah setoran, nilai rata,
     * status terakhir (khatam bila ada setoran berstatus khatam).
     *
     * @return array<int, array<string, mixed>>
     */
    public function progresPerKitab(Santri $santri, ?string $dari = null, ?string $sampai = null): array
    {
        $setoran = SetoranSorogan::query()
            ->where('santri_id', $santri->id)
            ->when($dari !== null && $sampai !== null, fn ($q) => $q->whereBetween('tanggal', [$dari, $sampai]))
            ->with('kitab')
            ->orderBy('tanggal')
            ->orderBy('id')
            ->get();

        return $setoran
            ->groupBy('kitab_id')
            ->map(function ($rows) {
                /** @var \Illuminate\Support\Collection<int, SetoranSorogan> $rows */
                $terakhir = $rows->last();
                $nilai = $rows->map(fn (SetoranSorogan $s): ?float => $s->nilai_rata)->filter(fn ($v) => $v !== null);

                return [
                    'kitab' => $terakhir->kitab?->nama,
                    'jumlah_setoran' => $rows->count(),
                    'posisi_terakhir' => $this->posisi($terakhir),
                    'tanggal_terakhir' => $terakhir->tanggal?->toDateString(),
                    'status_terakhir' => $terakhir->status,
                    'khatam' => $rows->contains(fn (SetoranSorogan $s): bool => $s->status === 'khatam'),
                    'nilai_rata' => $nilai->isEmpty() ? null : round((float) $nilai->avg(), 2),
                ];
            })
            ->values()
            ->all();
    }

    /**
     * Blok ringkasan untuk Rapor Diniyah (santri tanpa data → blok kosong
     * ramah, BUKAN error — konsisten RaporDiniyahService).
     *
     * @return array<string, mixed>
     */
    public function ringkasan(Santri $santri, ?string $dari = null, ?string $sampai = null): array
    {
        $perKitab = $this->progresPerKitab($santri, $dari, $sampai);

        $nilai = array_values(array_filter(array_column($perKitab, 'nilai_rata'), fn ($v) => $v !== null));

        $sesi = NgajiSesiPeserta::query()
            ->where('santri_id', $santri->id)
            ->when($dari !== null && $sampai !== null, fn ($q) => $q
                ->whereHas('sesi', fn ($s) => $s->whereBetween('tanggal', [$dari, $sampai])));

        $ikut = (clone $sesi)->count();
        $hadir = (clone $sesi)->where('hadir', true)->count();

        return [
            'daftar' => $perKitab,
            'jumlah_setoran' => array_sum(array_column($perKitab, 'jumlah_setoran')),
            'kitab_khatam' => count(array_filter($perKitab, fn (array $k): bool => (bool) $k['khatam'])),
            'nilai_rata' => $nilai === [] ? null : round(array_sum($nilai) / count($nilai), 2),
            'sesi_bandongan' => ['ikut' => $ikut, 'hadir' => $hadir],
        ];
    }

    private function posisi(SetoranSorogan $s): string
    {
        $bagian = array_filter([
            $s->bab,
            $s->halaman_dari !== null
                ? 'hal. '.$s->halaman_dari.($s->halaman_sampai !== null && $s->halaman_sampai !== $s->halaman_dari ? '–'.$s->halaman_sampai : '')
                : null,
        ]);

        return $bagian === [] ? '-' : implode(' ', $bagian);
    }
}
