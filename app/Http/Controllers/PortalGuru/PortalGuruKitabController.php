<?php

declare(strict_types=1);

namespace App\Http\Controllers\PortalGuru;

use App\Http\Controllers\Controller;
use App\Http\Controllers\PortalGuru\Concerns\ResolvesPortalGuruContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Akademik\Models\CapaianKitab;
use Modules\Akademik\Models\KajianKitab;
use Modules\Akademik\Services\CapaianKitabService;

/**
 * F2-PWA — Layar capaian kitab: sorogan per santri (posisi + lanjut/ulang +
 * riwayat) & bandongan per kelompok (satu update, santri_id NULL = kelompok).
 * Hanya kajian yang KU-ampu (pengampu_id) yang terlihat & bisa ditulis.
 */
class PortalGuruKitabController extends Controller
{
    use ResolvesPortalGuruContext;

    public function __construct(private readonly CapaianKitabService $capaianService) {}

    public function index(Request $request): Response
    {
        $guru = $this->currentGuru($request);

        $kajian = KajianKitab::query()
            ->with(['kitab', 'halaqah', 'kelas'])
            ->where('pengampu_id', $guru->id)
            ->where('aktif', true)
            ->get()
            ->map(fn (KajianKitab $k): array => [
                'id' => $k->id,
                'kitab' => $k->kitab?->nama,
                'metode' => $k->metode,
                'kelompok' => $k->halaqah?->nama ?? $k->kelas?->nama_kelas,
                'form_url' => "/guru/kitab/{$k->id}",
            ])
            ->values();

        return Inertia::render('PortalGuru/KitabIndex', [
            ...$this->sharedProps($request),
            'kajian' => $kajian,
        ]);
    }

    public function form(Request $request, int $kajian): Response
    {
        $guru = $this->currentGuru($request);
        $k = $this->resolveKajianMilikku($kajian, $guru->id);

        $peserta = $this->capaianService->peserta($k)
            ->map(fn ($s): array => ['id' => $s->id, 'nis' => $s->nis, 'nama' => $s->nama])
            ->values();

        return Inertia::render('PortalGuru/KitabForm', [
            ...$this->sharedProps($request),
            'kajian' => [
                'id' => $k->id,
                'kitab' => $k->kitab?->nama,
                'metode' => $k->metode,
                'kelompok' => $k->halaqah?->nama ?? $k->kelas?->nama_kelas,
            ],
            'peserta' => $peserta,
            'riwayatKelompok' => $k->metode === 'sorogan'
                ? []
                : $this->capaianService->riwayat($k)->map(fn (CapaianKitab $c): array => $this->riwayatRow($c))->values(),
            'riwayatPerSantri' => $k->metode === 'sorogan'
                ? CapaianKitab::query()
                    ->where('kajian_kitab_id', $k->id)
                    ->whereNotNull('santri_id')
                    ->orderByDesc('tanggal')->orderByDesc('id')
                    ->limit(50)
                    ->get()
                    ->groupBy('santri_id')
                    ->map(fn ($rows) => $rows->take(5)->map(fn (CapaianKitab $c): array => $this->riwayatRow($c))->values())
                : [],
        ]);
    }

    public function store(Request $request, int $kajian): RedirectResponse
    {
        $guru = $this->currentGuru($request);
        $k = $this->resolveKajianMilikku($kajian, $guru->id);

        $this->capaianService->catat($k, $request->only([
            'santri_id', 'posisi', 'tanggal', 'status', 'catatan',
        ]), $guru);

        return back()->with('success', 'Capaian kitab tersimpan.');
    }

    /** @return array<string, mixed> */
    private function riwayatRow(CapaianKitab $c): array
    {
        return [
            'posisi' => $c->posisi,
            'status' => $c->status,
            'tanggal' => $c->tanggal?->toDateString(),
            'catatan' => $c->catatan,
        ];
    }

    private function resolveKajianMilikku(int $kajianId, int $guruId): KajianKitab
    {
        $kajian = KajianKitab::query()
            ->with(['kitab', 'halaqah', 'kelas'])
            ->findOrFail($kajianId);

        abort_unless((int) $kajian->pengampu_id === $guruId, 403);

        return $kajian;
    }
}
