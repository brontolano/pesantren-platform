<?php

declare(strict_types=1);

namespace App\Http\Controllers\PortalGuru;

use App\Http\Controllers\Controller;
use App\Http\Controllers\PortalGuru\Concerns\ResolvesPortalGuruContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Akademik\Models\PeriodeAkademik;
use Modules\Akademik\Services\WaliKelasService;
use Modules\Kesantrian\Models\Kelas;
use Modules\Kesantrian\Models\Santri;

/**
 * F2-PWA — Layar wali kelas: rekap anak binaan (kehadiran %, rata nilai,
 * poin disiplin) + input catatan wali kelas utk rapor + link rubrik
 * ubudiyah (WP-10). Guru lain (bukan wali kelas tsb) → 403.
 */
class PortalGuruWaliKelasController extends Controller
{
    use ResolvesPortalGuruContext;

    public function __construct(private readonly WaliKelasService $waliService) {}

    public function index(Request $request): Response
    {
        $guru = $this->currentGuru($request);

        $kelas = $this->waliService->kelasBinaan($guru)
            ->map(fn (Kelas $k): array => [
                'id' => $k->id,
                'nama' => $k->nama_kelas,
                'tingkat' => $k->tingkat,
                'rekap_url' => "/guru/wali-kelas/{$k->id}",
            ])
            ->values();

        return Inertia::render('PortalGuru/WaliKelasIndex', [
            ...$this->sharedProps($request),
            'kelas' => $kelas,
        ]);
    }

    public function show(Request $request, int $kelas): Response
    {
        $guru = $this->currentGuru($request);
        $k = $this->resolveKelasBinaanku($kelas, $guru->id);
        $periode = $this->periodeAktif($k);
        abort_unless($periode !== null, 404, 'Belum ada periode akademik aktif.');

        return Inertia::render('PortalGuru/WaliKelas', [
            ...$this->sharedProps($request),
            'kelas' => ['id' => $k->id, 'nama' => $k->nama_kelas],
            'periode' => ['id' => $periode->id, 'nama' => $periode->nama],
            'rekap' => $this->waliService->rekap($k, $periode),
            // WP-10 — cukup link ke input rubrik ubudiyah (panel admin).
            'rubrik_ubudiyah_url' => '/admin/akademik/rapor-diniyah/isi-rubrik',
        ]);
    }

    public function simpanCatatan(Request $request, int $kelas): RedirectResponse
    {
        $guru = $this->currentGuru($request);
        $k = $this->resolveKelasBinaanku($kelas, $guru->id);
        $periode = $this->periodeAktif($k);
        abort_unless($periode !== null, 404);

        $data = $request->validate([
            'santri_id' => ['required', 'integer'],
            'catatan' => ['required', 'string', 'max:2000'],
        ]);

        $santri = Santri::query()->findOrFail($data['santri_id']);

        $this->waliService->simpanCatatan($guru, $k, $santri, $periode, $data['catatan']);

        return back()->with('success', 'Catatan wali kelas tersimpan.');
    }

    /** Periode aktif lembaga kelas (WP-01) — fallback periode NULL-lembaga. */
    private function periodeAktif(?Kelas $kelas = null): ?PeriodeAkademik
    {
        return PeriodeAkademik::query()
            ->where('is_active', true)
            ->where('lembaga_id', $kelas?->lembaga_id)
            ->orderByDesc('tanggal_mulai')
            ->first()
            ?? PeriodeAkademik::query()
                ->where('is_active', true)
                ->whereNull('lembaga_id')
                ->orderByDesc('tanggal_mulai')
                ->first();
    }

    private function resolveKelasBinaanku(int $kelasId, int $guruId): Kelas
    {
        $kelas = Kelas::query()->findOrFail($kelasId);

        abort_unless((int) $kelas->wali_kelas_id === $guruId, 403);

        return $kelas;
    }
}
