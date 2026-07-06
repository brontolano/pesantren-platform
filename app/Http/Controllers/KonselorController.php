<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Kesantrian\Models\Santri;
use Modules\Kesehatan\Models\SesiKonseling;

/**
 * PWA "Konselor" — role `konselor` sebelumnya TIDAK PUNYA PWA sama sekali
 * (bukan dummy-ke-nyata; ini genuinely fitur baru). Data via
 * Modules\Kesehatan\SesiKonseling, model yang SUDAH dipakai
 * SesiKonselingResource di panel admin — tak ada logika/skema baru.
 *
 * Gate: middleware('auth') + can:view_konseling — permission yang SUDAH
 * dimiliki role `konselor` (RolesAndPermissionsSeeder) dan dipakai
 * SesiKonselingResource::canAccess() di panel admin.
 */
class KonselorController extends Controller
{
    private function nav(): array
    {
        $base = '/konselor';

        $items = [
            ['key' => 'beranda', 'label' => 'Beranda', 'href' => '/staf', 'route' => 'beranda', 'icon' => 'home'],
            ['key' => 'sesi', 'label' => 'Sesi', 'href' => "{$base}/sesi", 'route' => 'sesi', 'icon' => 'chat'],
            ['key' => 'catat', 'label' => 'Catat', 'href' => "{$base}/catat", 'route' => 'catat', 'icon' => 'plus'],
        ];

        return collect($items)
            ->map(fn (array $i): array => [
                'key' => $i['key'],
                'label' => $i['label'],
                'href' => $i['href'],
                'icon' => $i['icon'],
                'active' => request()->routeIs("tenant.konselor.{$i['route']}"),
            ])
            ->values()
            ->all();
    }

    public function beranda(): Response
    {
        $terjadwal = SesiKonseling::query()->where('status', 'terjadwal')->count();
        $selesaiBulanIni = SesiKonseling::query()
            ->where('status', 'selesai')
            ->whereBetween('tanggal', [now()->startOfMonth(), now()->endOfMonth()])
            ->count();

        $sesiTerdekat = SesiKonseling::query()
            ->with('santri')
            ->where('status', 'terjadwal')
            ->orderBy('tanggal')
            ->limit(5)
            ->get()
            ->map(fn (SesiKonseling $s): array => [
                'id' => $s->id,
                'santri' => $s->santri?->nama,
                'topik' => $s->topik,
                'tanggal' => $s->tanggal?->translatedFormat('d M Y'),
            ]);

        return Inertia::render('Konselor/Beranda', [
            'navigation' => $this->nav(),
            'userName' => Auth::user()?->name,
            'stats' => [
                'terjadwal' => $terjadwal,
                'selesai_bulan_ini' => $selesaiBulanIni,
            ],
            'sesiTerdekat' => $sesiTerdekat,
        ]);
    }

    public function sesi(Request $request): Response
    {
        $status = $request->query('status', 'semua');

        $query = SesiKonseling::query()->with('santri')->orderByDesc('tanggal');

        if (! in_array($status, ['terjadwal', 'selesai', 'dibatalkan'], true)) {
            $status = 'semua';
        }

        if ($status !== 'semua') {
            $query->where('status', $status);
        }

        $sesi = $query->limit(50)->get()->map(fn (SesiKonseling $s): array => [
            'id' => $s->id,
            'santri' => $s->santri?->nama,
            'topik' => $s->topik,
            'tanggal' => $s->tanggal?->translatedFormat('d M Y'),
            'status' => $s->status,
        ]);

        return Inertia::render('Konselor/Sesi', [
            'navigation' => $this->nav(),
            'sesi' => $sesi,
            'statusAktif' => $status,
        ]);
    }

    public function catat(): Response
    {
        $santriOptions = Santri::query()
            ->where('status', 'aktif')
            ->orderBy('nama')
            ->get(['id', 'nama'])
            ->map(fn (Santri $s): array => ['value' => $s->id, 'label' => $s->nama]);

        return Inertia::render('Konselor/CatatSesi', [
            'navigation' => $this->nav(),
            'santriOptions' => $santriOptions,
        ]);
    }

    public function simpan(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'santri_id' => ['required', 'integer', 'exists:santri,id'],
            'tanggal' => ['required', 'date'],
            'topik' => ['required', 'string', 'max:255'],
            'deskripsi' => ['nullable', 'string', 'max:2000'],
            'rekomendasi' => ['nullable', 'string', 'max:2000'],
        ]);

        SesiKonseling::create([
            'santri_id' => $data['santri_id'],
            'tanggal' => $data['tanggal'],
            'topik' => $data['topik'],
            'deskripsi' => $data['deskripsi'] ?? null,
            'rekomendasi' => $data['rekomendasi'] ?? null,
            'status' => 'terjadwal',
            'konselor_id' => Auth::id(),
        ]);

        return redirect()->route('tenant.konselor.sesi')->with('success', 'Sesi konseling dicatat.');
    }
}
