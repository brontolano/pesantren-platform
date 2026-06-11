<?php

namespace Modules\Akademik\Services;

use Carbon\Carbon;
use Modules\Akademik\Models\CapaianKitab;
use Modules\Akademik\Models\Nilai;
use Modules\Akademik\Models\PeriodeAkademik;
use Modules\Akademik\Models\RaporDiniyah;
use Modules\Akademik\Models\RubrikKriteria;
use Modules\Akademik\Models\RubrikSkor;
use Modules\Kegiatan\Models\PresensiKegiatan;
use Modules\Kesantrian\Models\Santri;
use Modules\Kesantrian\Services\DisiplinService;
use Modules\Tahfidz\Models\HafalanTahfidz;
use Modules\Tahfidz\Models\PenilaianTahfidz;

/**
 * WP-10 — Generator Rapor Diniyah. Mengumpulkan 6 blok jadi SNAPSHOT JSON
 * (bukan live-query) dan menyimpan idempoten via updateOrCreate per
 * santri+periode. Santri tanpa data pada suatu blok → blok kosong ramah
 * (count 0 / list kosong), BUKAN error.
 *
 * Keputusan desain:
 * - nilai_imtihan: hanya Nilai yang kelasnya menempel pada Lembaga jenis
 *   'diniyah'; nilai akhir SELALU dihitung ulang lewat
 *   NilaiService::hitungNilaiAkhir (satu-satunya rumus, tidak diduplikasi).
 * - kehadiran_kegiatan: memakai PresensiKegiatan (bukan PresensiAsrama
 *   WP-25) — kegiatan terjadwal per periode adalah satuan yang sejalan
 *   dengan "hari efektif" KalenderService; presensi asrama bersatuan slot
 *   harian ganda (subuh/maghrib/malam) sehingga pembaginya tidak setara.
 * - tahfidz: skema HafalanTahfidz memakai surah+ayat (bukan juz/halaman),
 *   maka "posisi terakhir" = surah & ayat setoran selesai terakhir.
 * - disiplin: DisiplinService::totalPoin dengan acuan tanggal_akhir periode
 *   (poin bersih sesuai kebijakan masa berlaku tenant).
 */
class RaporDiniyahService
{
    public function __construct(
        private readonly NilaiService $nilaiService,
        private readonly KalenderService $kalenderService,
        private readonly DisiplinService $disiplinService,
    ) {}

    public function generate(Santri $santri, PeriodeAkademik $periode): RaporDiniyah
    {
        $blok = [
            'nilai_imtihan' => $this->blokNilaiImtihan($santri, $periode),
            'capaian_kitab' => $this->blokCapaianKitab($santri, $periode),
            'tahfidz' => $this->blokTahfidz($santri, $periode),
            'ubudiyah_akhlak' => $this->blokUbudiyahAkhlak($santri, $periode),
            'kehadiran_kegiatan' => $this->blokKehadiranKegiatan($santri, $periode),
            'disiplin' => $this->blokDisiplin($santri, $periode),
        ];

        return RaporDiniyah::query()->updateOrCreate([
            'santri_id' => $santri->id,
            'periode_akademik_id' => $periode->id,
        ], [
            'blok' => $blok,
            'generated_at' => now(),
        ]);
    }

    /** Nilai pelajaran pada kelas yang lembaganya berjenis 'diniyah'. */
    private function blokNilaiImtihan(Santri $santri, PeriodeAkademik $periode): array
    {
        $nilai = Nilai::query()
            ->where('santri_id', $santri->id)
            ->where('periode_akademik_id', $periode->id)
            ->whereHas('kelas.lembaga', fn ($q) => $q->where('jenis', 'diniyah'))
            ->with('pelajaran')
            ->orderBy('pelajaran_id')
            ->get();

        $daftar = $nilai->map(fn (Nilai $item): array => [
            'pelajaran' => $item->pelajaran?->nama,
            'kode' => $item->pelajaran?->kode,
            // SELALU rumus NilaiService (komponen berbobot / fallback 30-30-40).
            'nilai_akhir' => $this->nilaiService->hitungNilaiAkhir($item->only([
                'pelajaran_id', 'nilai_komponen', 'nilai_harian', 'nilai_uts', 'nilai_uas',
            ])),
        ])->values()->all();

        $akhir = array_column($daftar, 'nilai_akhir');

        return [
            'daftar' => $daftar,
            'rata_rata' => $akhir === [] ? null : round(array_sum($akhir) / count($akhir), 2),
        ];
    }

    /** Capaian per kajian: baris per-santri (sorogan) + posisi kelompok kelas santri. */
    private function blokCapaianKitab(Santri $santri, PeriodeAkademik $periode): array
    {
        $capaian = CapaianKitab::query()
            ->whereHas('kajianKitab', fn ($q) => $q->where('periode_akademik_id', $periode->id))
            ->where(function ($q) use ($santri) {
                $q->where('santri_id', $santri->id);

                if ($santri->kelas_id !== null) {
                    $q->orWhere(fn ($qq) => $qq
                        ->whereNull('santri_id')
                        ->whereHas('kajianKitab', fn ($k) => $k->where('kelas_id', $santri->kelas_id)));
                }
            })
            ->with('kajianKitab.kitab')
            ->orderByDesc('tanggal')
            ->orderByDesc('id')
            ->get()
            // Satu baris terakhir per kajian (capaian = posisi berjalan).
            ->unique('kajian_kitab_id')
            ->map(fn (CapaianKitab $c): array => [
                'kitab' => $c->kajianKitab?->kitab?->nama,
                'metode' => $c->kajianKitab?->metode,
                'posisi' => $c->posisi,
                'status' => $c->status,
                'tanggal' => $c->tanggal?->toDateString(),
            ])
            ->values()
            ->all();

        return ['daftar' => $capaian];
    }

    private function blokTahfidz(Santri $santri, PeriodeAkademik $periode): array
    {
        [$dari, $sampai] = [$periode->tanggal_mulai, $periode->tanggal_akhir];

        $selesai = HafalanTahfidz::query()
            ->where('santri_id', $santri->id)
            ->whereNotNull('tanggal_selesai')
            ->whereBetween('tanggal_selesai', [$dari, $sampai])
            ->orderByDesc('tanggal_selesai')
            ->orderByDesc('id')
            ->get();

        $terakhir = $selesai->first();

        $nilaiRata = PenilaianTahfidz::query()
            ->where('santri_id', $santri->id)
            ->whereBetween('tanggal', [$dari, $sampai])
            ->avg('nilai');

        return [
            'jumlah_setoran' => $selesai->count(),
            'terakhir' => $terakhir === null ? null : [
                'surah' => $terakhir->surah,
                'ayat_awal' => $terakhir->ayat_awal,
                'ayat_akhir' => $terakhir->ayat_akhir,
                'tanggal' => $terakhir->tanggal_selesai?->toDateString(),
            ],
            'nilai_rata' => $nilaiRata === null ? null : round((float) $nilaiRata, 2),
        ];
    }

    /** Reuse RubrikKriteria/RubrikSkor (G3-18): agregat = Σ(skor×bobot)/Σbobot. */
    private function blokUbudiyahAkhlak(Santri $santri, PeriodeAkademik $periode): array
    {
        $skor = RubrikSkor::query()
            ->where('santri_id', $santri->id)
            ->where('periode_akademik_id', $periode->id)
            ->with('kriteria')
            ->get()
            ->filter(fn (RubrikSkor $s) => $s->kriteria instanceof RubrikKriteria);

        $totalBobot = (float) $skor->sum(fn (RubrikSkor $s) => (float) $s->kriteria->bobot);

        return [
            'daftar' => $skor->map(fn (RubrikSkor $s): array => [
                'kriteria' => $s->kriteria->nama,
                'bobot' => (float) $s->kriteria->bobot,
                'skor' => (float) $s->skor,
            ])->values()->all(),
            'agregat' => $totalBobot > 0.0
                ? round((float) $skor->sum(fn (RubrikSkor $s) => (float) $s->skor * (float) $s->kriteria->bobot) / $totalBobot, 2)
                : null,
        ];
    }

    private function blokKehadiranKegiatan(Santri $santri, PeriodeAkademik $periode): array
    {
        $dari = Carbon::parse($periode->tanggal_mulai);
        $sampai = Carbon::parse($periode->tanggal_akhir);

        $hadir = PresensiKegiatan::query()
            ->where('santri_id', $santri->id)
            ->where('status', 'hadir')
            ->whereHas('kegiatan', fn ($q) => $q->whereBetween('mulai', [
                $dari->copy()->startOfDay(), $sampai->copy()->endOfDay(),
            ]))
            ->count();

        $santri->loadMissing('kelas.lembaga');
        $hariEfektif = $this->kalenderService->hariEfektifDalamRentang($dari, $sampai, $santri->kelas?->lembaga);

        return [
            'sumber' => 'presensi_kegiatan',
            'hadir' => $hadir,
            'hari_efektif' => $hariEfektif,
            'persentase' => $hariEfektif > 0 ? round($hadir / $hariEfektif * 100, 2) : null,
        ];
    }

    private function blokDisiplin(Santri $santri, PeriodeAkademik $periode): array
    {
        $acuan = $periode->tanggal_akhir ? Carbon::parse($periode->tanggal_akhir) : null;

        return [
            'poin_bersih' => $this->disiplinService->totalPoin($santri, $acuan),
        ];
    }
}
