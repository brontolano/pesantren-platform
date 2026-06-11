<?php

namespace Modules\Akademik\Services;

use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;
use Modules\Akademik\Models\Nilai;
use Modules\Akademik\Models\PeriodeAkademik;
use Modules\Akademik\Models\Rapor;
use Modules\Kesantrian\Models\Kelas;
use Modules\Kesantrian\Models\Santri;
use Modules\Kesantrian\Services\DisiplinService;
use RuntimeException;

/**
 * F2-PWA — Layar wali kelas: rekap anak binaan (kehadiran %, rata nilai,
 * poin disiplin via service existing) + catatan wali kelas untuk rapor
 * (kolom rapor.catatan_walikelas existing, upsert per santri+periode).
 *
 * Penjagaan: HANYA wali kelas binaan (kelas.wali_kelas_id = guru) yang boleh
 * membaca/menulis — guru lain ditolak (assertWali → 403 di controller).
 */
class WaliKelasService
{
    public function __construct(
        private readonly PresensiSantriService $presensiSantriService,
        private readonly DisiplinService $disiplinService,
    ) {}

    /** Kelas binaan guru ini. */
    public function kelasBinaan(User $guru): Collection
    {
        return Kelas::query()
            ->where('wali_kelas_id', $guru->id)
            ->where('is_active', true)
            ->orderBy('nama_kelas')
            ->get()
            ->toBase();
    }

    public function assertWali(User $guru, Kelas $kelas): void
    {
        if ((int) $kelas->wali_kelas_id !== (int) $guru->id) {
            throw new RuntimeException('Bukan wali kelas ini.');
        }
    }

    /**
     * Rekap anak binaan dalam satu periode.
     *
     * @return array<int, array<string, mixed>>
     */
    public function rekap(Kelas $kelas, PeriodeAkademik $periode): array
    {
        $this->ensureTenantContext();

        $dari = $periode->tanggal_mulai;
        $sampai = min(now()->toDateString(), (string) $periode->tanggal_akhir);

        $rapor = Rapor::query()
            ->where('periode_akademik_id', $periode->id)
            ->get()
            ->keyBy('santri_id');

        $rataNilai = Nilai::query()
            ->where('periode_akademik_id', $periode->id)
            ->whereNotNull('nilai_akhir')
            ->selectRaw('santri_id, avg(nilai_akhir) as rata')
            ->groupBy('santri_id')
            ->pluck('rata', 'santri_id');

        return Santri::query()
            ->where('kelas_id', $kelas->id)
            ->where('status', 'aktif')
            ->orderBy('nama')
            ->get()
            ->map(function (Santri $s) use ($dari, $sampai, $rapor, $rataNilai): array {
                $kehadiran = $this->presensiSantriService->rekap($s, $dari, $sampai);

                return [
                    'santri_id' => $s->id,
                    'nis' => $s->nis,
                    'nama' => $s->nama,
                    'kehadiran_persen' => $kehadiran['persen'],
                    'kehadiran_total' => $kehadiran['total'],
                    'rata_nilai' => $rataNilai->has($s->id) ? round((float) $rataNilai->get($s->id), 2) : null,
                    'poin_disiplin' => $this->disiplinService->totalPoin($s),
                    'catatan_walikelas' => $rapor->get($s->id)?->catatan_walikelas,
                ];
            })
            ->values()
            ->all();
    }

    /**
     * Upsert catatan wali kelas ke rapor santri (per santri+periode).
     *
     * @throws ValidationException
     */
    public function simpanCatatan(User $guru, Kelas $kelas, Santri $santri, PeriodeAkademik $periode, string $catatan): Rapor
    {
        $this->ensureTenantContext();
        $this->assertWali($guru, $kelas);

        if ((int) $santri->kelas_id !== (int) $kelas->id) {
            throw ValidationException::withMessages([
                'santri_id' => 'Santri bukan anggota kelas binaan ini.',
            ]);
        }

        if (mb_strlen($catatan) > 2000) {
            throw ValidationException::withMessages([
                'catatan' => 'Catatan maksimal 2000 karakter.',
            ]);
        }

        return Rapor::query()->updateOrCreate(
            [
                'santri_id' => $santri->id,
                'periode_akademik_id' => $periode->id,
            ],
            ['catatan_walikelas' => $catatan],
        );
    }

    private function ensureTenantContext(): void
    {
        if (! tenancy()->initialized || tenancy()->tenant === null) {
            throw new RuntimeException('Tenant context belum aktif untuk wali kelas.');
        }
    }
}
