<?php

namespace Modules\Akademik\Services;

use App\Models\User;
use Illuminate\Validation\ValidationException;
use Modules\Akademik\Models\Nilai;
use Modules\Akademik\Models\PeriodeAkademik;
use Modules\Kesantrian\Models\Kelas;
use Modules\Kesantrian\Models\Santri;

/**
 * F2-PWA — Grid nilai massal: SATU jalur persist untuk DUA pintu
 * (panel Filament & PWA Guru). Validasi 0-100, penjagaan komponen asing,
 * rumus nilai akhir, dan input_by semuanya hidup di NilaiService (F0) —
 * service ini hanya menyiapkan data grid + meneruskan simpan per baris.
 *
 * Idempoten: NilaiService::simpan meng-upsert by
 * (santri, pelajaran, periode) — autosave retry tidak menggandakan baris.
 */
class NilaiGridService
{
    public function __construct(private readonly NilaiService $nilaiService) {}

    /**
     * Simpan SATU baris grid (autosave per-baris dari PWA, atau loop simpan
     * massal dari panel). JANGAN tambah jalur tulis nilai lain.
     *
     * @param  array<string, mixed>  $data
     *
     * @throws ValidationException
     */
    public function simpanBaris(array $data, ?User $actor = null): Nilai
    {
        return $this->nilaiService->simpan($data, $actor);
    }

    /**
     * Data grid: daftar komponen yang berlaku + baris santri (nilai
     * tersimpan ikut dimuat agar guru melanjutkan, bukan mengetik ulang).
     *
     * @return array{komponen: array<int, array<string, mixed>>, baris: array<int, array<string, mixed>>}
     */
    public function dataGrid(int $kelasId, int $pelajaranId, int $periodeId): array
    {
        $komponen = $this->nilaiService->komponenUntukPelajaran($pelajaranId)
            ->map(fn ($k): array => [
                'id' => $k->id,
                'nama' => $k->nama,
                'bobot' => $k->bobot,
            ])
            ->values()
            ->all();

        $tersimpan = Nilai::query()
            ->where('pelajaran_id', $pelajaranId)
            ->where('periode_akademik_id', $periodeId)
            ->get()
            ->keyBy('santri_id');

        $baris = Santri::query()
            ->where('kelas_id', $kelasId)
            ->where('status', 'aktif')
            ->orderBy('nama')
            ->get()
            ->map(function (Santri $s) use ($tersimpan): array {
                $nilai = $tersimpan->get($s->id);

                return [
                    'santri_id' => $s->id,
                    'nis' => $s->nis,
                    'nama' => $s->nama,
                    'nilai_komponen' => $nilai?->nilai_komponen ?? [],
                    'nilai_harian' => $nilai?->nilai_harian,
                    'nilai_uts' => $nilai?->nilai_uts,
                    'nilai_uas' => $nilai?->nilai_uas,
                    'nilai_akhir' => $nilai?->nilai_akhir,
                ];
            })
            ->values()
            ->all();

        return ['komponen' => $komponen, 'baris' => $baris];
    }

    /**
     * Periode akademik aktif untuk LEMBAGA kelas (WP-01: periode per lembaga).
     * Tenant multi-lembaga bisa punya ≥2 periode aktif — tanpa scoping,
     * nilai tersimpan ke periode lembaga lain.
     */
    public function periodeAktif(?Kelas $kelas = null): ?PeriodeAkademik
    {
        $lembagaId = $kelas?->lembaga_id;

        return PeriodeAkademik::query()
            ->where('is_active', true)
            ->where('lembaga_id', $lembagaId)
            ->orderByDesc('tanggal_mulai')
            ->first()
            ?? PeriodeAkademik::query()
                ->where('is_active', true)
                ->whereNull('lembaga_id')
                ->orderByDesc('tanggal_mulai')
                ->first();
    }
}
