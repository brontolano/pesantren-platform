<?php

declare(strict_types=1);

namespace App\Http\Controllers\PortalGuru;

use App\Http\Controllers\Controller;
use App\Http\Controllers\PortalGuru\Concerns\ResolvesPortalGuruContext;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Akademik\Models\PeriodeAkademik;
use Modules\Kesantrian\Models\Santri;
use Modules\Tahfidz\Models\HalaqahSantri;
use Modules\Tahfidz\Models\Munaqasyah;
use Modules\Tahfidz\Models\SimaAn;

/**
 * WP-11 — PWA Guru mode muhaffizh: form catat sima'an (rentang bebas + nilai
 * kelancaran/tajwid) dan munaqasyah (juz diuji + hasil lulus|mengulang).
 *
 * Idempoten via client_ref (UUID per entri, pola F1-PWA setoran): retry
 * jaringan mengirim ref sama → unique (tenant_id, client_ref) menolak baris
 * kedua, server menjawab sukses tanpa menulis dobel.
 *
 * TIDAK menyentuh TahfidzService::setor / HafalanTahfidz.
 */
class PortalGuruTahfidzLanjutanController extends Controller
{
    use ResolvesPortalGuruContext;

    public function simaanForm(Request $request): Response
    {
        return Inertia::render('PortalGuru/Simaan', [
            ...$this->sharedProps($request),
            'santri' => $this->santriPilihan($this->currentGuru($request)),
        ]);
    }

    public function simaanStore(Request $request): RedirectResponse
    {
        $guru = $this->currentGuru($request);

        $data = $request->validate([
            'santri_id' => ['required', 'integer'],
            'rentang' => ['required', 'string', 'max:255'],
            'nilai_kelancaran' => ['nullable', 'integer', 'min:0', 'max:100'],
            'nilai_tajwid' => ['nullable', 'integer', 'min:0', 'max:100'],
            'catatan' => ['nullable', 'string', 'max:500'],
            'tanggal' => ['required', 'date'],
            'client_ref' => ['required', 'uuid'],
        ]);

        $this->authorizeSantri($guru, (int) $data['santri_id']);

        // Idempoten HANYA untuk retry record yang sama (penyimak+santri sama);
        // ref milik record lain → 409, bukan sukses-diam (data hilang senyap).
        $sudahAda = SimaAn::query()->where('client_ref', $data['client_ref'])->first();
        if ($sudahAda !== null) {
            abort_unless(
                $sudahAda->penyimak_id === $guru->id && $sudahAda->santri_id === (int) $data['santri_id'],
                409, 'client_ref sudah dipakai record lain — muat ulang form.',
            );

            return back()->with('success', 'Sima\'an tercatat.');
        }

        try {
            SimaAn::create([...$data, 'penyimak_id' => $guru->id]);
        } catch (UniqueConstraintViolationException) {
            // Race retry paralel: baris pertama sudah masuk — tetap sukses.
        }

        return back()->with('success', 'Sima\'an tercatat.');
    }

    public function munaqasyahForm(Request $request): Response
    {
        return Inertia::render('PortalGuru/Munaqasyah', [
            ...$this->sharedProps($request),
            'santri' => $this->santriPilihan($this->currentGuru($request)),
            'periode' => PeriodeAkademik::query()
                ->orderByDesc('tanggal_mulai')
                ->limit(10)
                ->get()
                ->map(fn (PeriodeAkademik $p): array => ['id' => $p->id, 'nama' => $p->nama])
                ->values(),
        ]);
    }

    public function munaqasyahStore(Request $request): RedirectResponse
    {
        $guru = $this->currentGuru($request);

        $data = $request->validate([
            'santri_id' => ['required', 'integer'],
            'juz_diuji' => ['required', 'string', 'max:255'],
            'hasil' => ['required', 'in:lulus,mengulang'],
            'nilai' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'tanggal' => ['required', 'date'],
            'periode_akademik_id' => ['nullable', 'integer', 'exists:periode_akademik,id'],
            'client_ref' => ['required', 'uuid'],
        ]);

        $this->authorizeSantri($guru, (int) $data['santri_id']);

        $sudahAda = Munaqasyah::query()->where('client_ref', $data['client_ref'])->first();
        if ($sudahAda !== null) {
            abort_unless(
                $sudahAda->penguji_id === $guru->id && $sudahAda->santri_id === (int) $data['santri_id'],
                409, 'client_ref sudah dipakai record lain — muat ulang form.',
            );

            return back()->with('success', 'Hasil munaqasyah tercatat.');
        }

        try {
            // is_peserta_wisuda TIDAK diset dari PWA — keputusan admin/panel.
            Munaqasyah::create([...$data, 'penguji_id' => $guru->id]);
        } catch (UniqueConstraintViolationException) {
            // Race retry paralel — baris pertama menang, tetap sukses.
        }

        return back()->with('success', 'Hasil munaqasyah tercatat.');
    }

    /**
     * Santri yang boleh dicatat guru ini: anggota aktif halaqah binaannya;
     * guru_tahfidz tanpa halaqah binaan → semua santri aktif (pola F1 setoran).
     *
     * @return array<int, array{id:int, nis:?string, nama:string}>
     */
    private function santriPilihan(User $guru): array
    {
        $halaqah = $this->halaqahKu($guru);

        if ($halaqah->isEmpty() && $guru->hasRole('guru_tahfidz')) {
            $query = Santri::query()->where('status', 'aktif');
        } else {
            $query = Santri::query()->whereIn(
                'id',
                HalaqahSantri::query()
                    ->whereIn('halaqah_id', $halaqah->pluck('id'))
                    ->whereNull('left_at')
                    ->pluck('santri_id'),
            );
        }

        return $query->orderBy('nama')
            ->get()
            ->map(fn (Santri $s): array => [
                'id' => $s->id,
                'nis' => $s->nis,
                'nama' => $s->nama,
            ])
            ->values()
            ->all();
    }

    private function authorizeSantri(User $guru, int $santriId): void
    {
        $santri = Santri::query()->findOrFail($santriId);
        $halaqah = $this->halaqahKu($guru);

        if ($halaqah->isEmpty()) {
            // Tanpa halaqah binaan: hanya guru_tahfidz, dan santri harus aktif.
            abort_unless($guru->hasRole('guru_tahfidz') && $santri->status === 'aktif', 403);

            return;
        }

        $anggota = HalaqahSantri::query()
            ->whereIn('halaqah_id', $halaqah->pluck('id'))
            ->where('santri_id', $santri->id)
            ->whereNull('left_at')
            ->exists();

        abort_unless($anggota && $santri->status === 'aktif', 403, 'Santri bukan anggota aktif halaqah binaan Anda.');
    }
}
