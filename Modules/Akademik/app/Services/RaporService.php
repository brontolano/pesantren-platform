<?php

namespace Modules\Akademik\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Modules\Akademik\Models\PeriodeAkademik;
use Modules\Akademik\Models\Rapor;
use Modules\Kesantrian\Models\Kelas;
use Modules\Kesantrian\Models\Santri;

class RaporService
{
    /**
     * @return Collection<int, Rapor>
     */
    public function generateForKelas(PeriodeAkademik $periode, Kelas $kelas, array $catatan = []): Collection
    {
        $santriList = Santri::query()
            ->where('kelas_id', $kelas->id)
            ->where('status', 'aktif')
            ->orderBy('nama')
            ->get();

        $generated = DB::transaction(function () use ($santriList, $periode, $catatan): Collection {
            $raporList = $santriList->map(function (Santri $santri) use ($periode, $catatan): array {
                $nilai = $periode->nilai()
                    ->with('pelajaran')
                    ->where('santri_id', $santri->id)
                    ->orderBy('pelajaran_id')
                    ->get();

                $totalSks = (float) $nilai->sum(fn ($item) => (int) ($item->pelajaran->sks ?? 0));
                $totalBobot = (float) $nilai->sum(fn ($item) => ((float) $item->nilai_akhir) * (int) ($item->pelajaran->sks ?? 0));
                $ipk = $totalSks > 0 ? round($totalBobot / $totalSks, 2) : 0.0;

                $ringkasanNilai = $nilai->map(fn ($item): array => [
                    'pelajaran' => $item->pelajaran->nama,
                    'kode' => $item->pelajaran->kode,
                    'sks' => $item->pelajaran->sks,
                    'nilai_harian' => $item->nilai_harian,
                    'nilai_uts' => $item->nilai_uts,
                    'nilai_uas' => $item->nilai_uas,
                    'nilai_akhir' => $item->nilai_akhir,
                ])->values()->all();

                return [
                    'santri' => $santri,
                    'payload' => [
                        'total_sks' => $totalSks,
                        'ipk' => $ipk,
                        'rank_kelas' => null,
                        'catatan_walikelas' => $catatan[$santri->id] ?? null,
                        'ringkasan_nilai' => $ringkasanNilai,
                        'generated_at' => now(),
                    ],
                ];
            })->sortByDesc(fn (array $row) => $row['payload']['ipk'])->values();

            return $raporList->map(function (array $row, int $index) use ($periode): Rapor {
                /** @var Santri $santri */
                $santri = $row['santri'];
                $payload = $row['payload'];
                $payload['rank_kelas'] = $index + 1;

                return Rapor::query()->updateOrCreate([
                    'santri_id' => $santri->id,
                    'periode_akademik_id' => $periode->id,
                ], $payload);
            });
        });

        return $generated->values();
    }
}
