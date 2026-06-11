<?php

declare(strict_types=1);

namespace App\Http\Controllers\PortalGuru;

use App\Http\Controllers\Controller;
use App\Http\Controllers\PortalGuru\Concerns\ResolvesPortalGuruContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Akademik\Models\JadwalPelajaran;
use Modules\Akademik\Models\Pelajaran;
use Modules\Akademik\Services\NilaiGridService;
use Modules\Kesantrian\Models\Kelas;

/**
 * F2-PWA — Grid nilai massal: pilih kelas × pelajaran → tabel santri ×
 * komponen nilai. Autosave PER BARIS (debounce di Vue, POST JSON per baris)
 * lewat NilaiGridService → NilaiService (validasi 0-100 + komponen asing +
 * input_by + upsert idempoten SEMUA di server, F0).
 */
class PortalGuruNilaiController extends Controller
{
    use ResolvesPortalGuruContext;

    public function __construct(private readonly NilaiGridService $gridService) {}

    /** Pilihan kelas × pelajaran dari jadwal yang KU-ampu. */
    public function index(Request $request): Response
    {
        $guru = $this->currentGuru($request);

        $kombinasi = JadwalPelajaran::query()
            ->with(['kelas', 'pelajaran'])
            ->whereHas('pelajaran', fn ($q) => $q->where('guru_pengampu_id', $guru->id))
            ->get()
            ->unique(fn (JadwalPelajaran $j): string => $j->kelas_id.'-'.$j->pelajaran_id)
            ->map(fn (JadwalPelajaran $j): array => [
                'kelas_id' => $j->kelas_id,
                'kelas' => $j->kelas?->nama_kelas,
                'pelajaran_id' => $j->pelajaran_id,
                'pelajaran' => $j->pelajaran?->nama,
                'grid_url' => "/guru/nilai/{$j->kelas_id}/{$j->pelajaran_id}",
            ])
            ->values();

        return Inertia::render('PortalGuru/NilaiIndex', [
            ...$this->sharedProps($request),
            'kombinasi' => $kombinasi,
        ]);
    }

    public function grid(Request $request, int $kelas, int $pelajaran): Response
    {
        $guru = $this->currentGuru($request);
        $mapel = $this->resolvePelajaranMilikku($pelajaran, $guru->id, $kelas);
        $periode = $this->gridService->periodeAktif(Kelas::find($kelas));
        abort_unless($periode !== null, 404, 'Belum ada periode akademik aktif.');

        $grid = $this->gridService->dataGrid($kelas, $pelajaran, $periode->id);

        return Inertia::render('PortalGuru/NilaiGrid', [
            ...$this->sharedProps($request),
            'kelas_id' => $kelas,
            'kelas' => $mapel->jadwal_kelas_nama,
            'pelajaran_id' => $pelajaran,
            'pelajaran' => $mapel->nama,
            'periode' => ['id' => $periode->id, 'nama' => $periode->nama],
            'komponen' => $grid['komponen'],
            'baris' => $grid['baris'],
        ]);
    }

    /** Autosave satu baris — idempoten (upsert santri+pelajaran+periode). */
    public function simpanBaris(Request $request, int $kelas, int $pelajaran): JsonResponse
    {
        $guru = $this->currentGuru($request);
        $this->resolvePelajaranMilikku($pelajaran, $guru->id, $kelas);
        $periode = $this->gridService->periodeAktif(Kelas::find($kelas));
        abort_unless($periode !== null, 404);

        $nilai = $this->gridService->simpanBaris([
            'santri_id' => $request->input('santri_id'),
            'pelajaran_id' => $pelajaran,
            'periode_akademik_id' => $periode->id,
            'kelas_id' => $kelas,
            'nilai_komponen' => $request->input('nilai_komponen'),
            'nilai_harian' => $request->input('nilai_harian'),
            'nilai_uts' => $request->input('nilai_uts'),
            'nilai_uas' => $request->input('nilai_uas'),
            'catatan' => $request->input('catatan'),
        ], $guru);

        return response()->json([
            'ok' => true,
            'santri_id' => $nilai->santri_id,
            'nilai_akhir' => $nilai->nilai_akhir,
        ]);
    }

    /**
     * Pelajaran hanya boleh diakses guru pengampunya DAN harus punya jadwal
     * di kelas tsb — selain itu 403/404.
     */
    private function resolvePelajaranMilikku(int $pelajaranId, int $guruId, int $kelasId): Pelajaran
    {
        $pelajaran = Pelajaran::query()->findOrFail($pelajaranId);

        abort_unless((int) $pelajaran->guru_pengampu_id === $guruId, 403);

        $jadwal = JadwalPelajaran::query()
            ->with('kelas')
            ->where('kelas_id', $kelasId)
            ->where('pelajaran_id', $pelajaranId)
            ->first();

        abort_unless($jadwal !== null, 403);

        $pelajaran->jadwal_kelas_nama = $jadwal->kelas?->nama_kelas;

        return $pelajaran;
    }
}
