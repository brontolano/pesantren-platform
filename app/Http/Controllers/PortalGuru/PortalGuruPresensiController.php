<?php

declare(strict_types=1);

namespace App\Http\Controllers\PortalGuru;

use App\Http\Controllers\Controller;
use App\Http\Controllers\PortalGuru\Concerns\ResolvesPortalGuruContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Akademik\Models\JadwalPelajaran;
use Modules\Akademik\Models\PresensiSantri;
use Modules\Akademik\Services\PresensiService;
use Modules\Kesantrian\Models\Santri;

/**
 * F1-PWA — Presensi kelas: daftar santri rombel sesi, default HADIR, ketuk
 * untuk ubah (izin/sakit/alpa). Simpan via PresensiService — idempoten per
 * (santri, kelas, tanggal): retry sinyal jelek meng-update, tidak dobel.
 */
class PortalGuruPresensiController extends Controller
{
    use ResolvesPortalGuruContext;

    public function __construct(
        private readonly PresensiService $presensiService,
    ) {
    }

    public function show(Request $request, int $jadwal): Response
    {
        $guru = $this->currentGuru($request);
        $sesi = $this->resolveJadwalMilikku($jadwal, $guru->id);
        $tanggal = now()->toDateString();

        $tersimpan = $this->presensiService->statusKelas($sesi, $tanggal);

        $santri = Santri::query()
            ->where('kelas_id', $sesi->kelas_id)
            ->where('status', 'aktif')
            ->orderBy('nama')
            ->get()
            ->map(fn (Santri $s): array => [
                'id' => $s->id,
                'nis' => $s->nis,
                'nama' => $s->nama,
                // Default HADIR — guru hanya menandai pengecualian.
                'status' => $tersimpan[$s->id] ?? 'hadir',
            ])
            ->values();

        return Inertia::render('PortalGuru/Presensi', [
            ...$this->sharedProps($request),
            'sesi' => [
                'id' => $sesi->id,
                'pelajaran' => $sesi->pelajaran?->nama,
                'kelas' => $sesi->kelas?->nama_kelas,
                'jam_mulai' => $sesi->jam_mulai,
                'jam_selesai' => $sesi->jam_selesai,
            ],
            'tanggal' => $tanggal,
            'santri' => $santri,
            'statusOptions' => PresensiSantri::STATUS,
        ]);
    }

    public function store(Request $request, int $jadwal): RedirectResponse
    {
        $guru = $this->currentGuru($request);
        $sesi = $this->resolveJadwalMilikku($jadwal, $guru->id);

        $data = $request->validate([
            // Jendela tanggal: maksimal 7 hari ke belakang (susulan sinyal
            // jelek), TIDAK boleh masa depan — presensi bukan ramalan.
            'tanggal' => [
                'nullable',
                'date',
                'before_or_equal:'.now()->toDateString(),
                'after_or_equal:'.now()->subDays(7)->toDateString(),
            ],
            'presensi' => ['required', 'array', 'min:1'],
            'presensi.*.santri_id' => ['required', 'integer'],
            'presensi.*.status' => ['required', 'in:'.implode(',', PresensiSantri::STATUS)],
            'presensi.*.keterangan' => ['nullable', 'string', 'max:500'],
        ]);

        $this->presensiService->catatKelas(
            $sesi,
            $data['presensi'],
            $data['tanggal'] ?? now()->toDateString(),
            $guru,
        );

        return back()->with('success', 'Presensi tersimpan.');
    }

    /** Sesi hanya boleh diakses guru pengampu pelajarannya — selain itu 403. */
    private function resolveJadwalMilikku(int $jadwalId, int $guruId): JadwalPelajaran
    {
        $jadwal = JadwalPelajaran::query()
            ->with(['kelas', 'pelajaran'])
            ->findOrFail($jadwalId);

        abort_unless((int) $jadwal->pelajaran?->guru_pengampu_id === $guruId, 403);

        return $jadwal;
    }
}
