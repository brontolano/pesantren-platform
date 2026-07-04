<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Pembiayaan\Models\AreaPiket;
use Modules\Pembiayaan\Models\CeklisPiket;
use Modules\Pembiayaan\Models\LaporanKerusakan;
use Modules\Pembiayaan\Services\SarprasService;
use Throwable;

/**
 * PWA "Sarpras" — role `pengelola_sarpras` sebelumnya TIDAK PUNYA PWA sama
 * sekali (bukan dummy-ke-nyata; ini genuinely fitur baru). Data via
 * Modules\Pembiayaan\SarprasService/LaporanKerusakan/CeklisPiket, backend
 * yang SUDAH dipakai LaporanKerusakanResource/AreaPiketResource di panel
 * admin DAN PwaGuruSarprasController (WP-33, untuk persona guru) — tak ada
 * logika/skema baru, hanya persona baru (petugas sarpras sendiri) yang
 * mengonsumsi service yang sama.
 *
 * Gate: middleware('auth') + can:manage_sarpras — permission yang SUDAH
 * dimiliki role `pengelola_sarpras` (RolesAndPermissionsSeeder) dan dipakai
 * AreaPiketResource/LaporanKerusakanResource di panel admin.
 *
 * Cakupan (jujur, tanpa fitur baru): PWA ini utk PENGELOLA menindaklanjuti
 * laporan (ubah status/biaya/catatan) & mencatat ceklis piket. Pelaporan
 * kerusakan awal (dgn foto wajib) TETAP jadi tugas pelapor lapangan (guru,
 * via PwaGuruSarprasController/API JSON yang sudah ada) — tidak diduplikasi
 * di sini demi menghindari dua jalur input utk data yang sama.
 */
class SarprasController extends Controller
{
    public function __construct(private SarprasService $service) {}

    private function nav(): array
    {
        $base = '/sarpras';

        $items = [
            ['key' => 'beranda', 'label' => 'Beranda', 'href' => $base, 'route' => 'beranda', 'icon' => 'home'],
            ['key' => 'kerusakan', 'label' => 'Kerusakan', 'href' => "{$base}/kerusakan", 'route' => 'kerusakan', 'icon' => 'wrench'],
            ['key' => 'piket', 'label' => 'Piket', 'href' => "{$base}/piket", 'route' => 'piket', 'icon' => 'check-circle'],
        ];

        return collect($items)
            ->map(fn (array $i): array => [
                'key' => $i['key'],
                'label' => $i['label'],
                'href' => $i['href'],
                'icon' => $i['icon'],
                'active' => request()->routeIs("tenant.sarpras.{$i['route']}"),
            ])
            ->values()
            ->all();
    }

    public function beranda(): Response
    {
        $masuk = LaporanKerusakan::query()->where('status', 'masuk')->count();
        $dikerjakan = LaporanKerusakan::query()->where('status', 'dikerjakan')->count();
        $selesaiBulanIni = LaporanKerusakan::query()
            ->where('status', 'selesai')
            ->whereBetween('updated_at', [now()->startOfMonth(), now()->endOfMonth()])
            ->count();

        $laporanTerbaru = LaporanKerusakan::query()
            ->orderByDesc('created_at')
            ->limit(5)
            ->get()
            ->map(fn (LaporanKerusakan $l): array => [
                'id' => $l->id,
                'lokasi' => $l->lokasi,
                'status' => $l->status,
                'dibuat' => $l->created_at?->translatedFormat('d M Y H:i'),
            ]);

        return Inertia::render('Sarpras/Beranda', [
            'navigation' => $this->nav(),
            'userName' => Auth::user()?->name,
            'stats' => [
                'masuk' => $masuk,
                'dikerjakan' => $dikerjakan,
                'selesai_bulan_ini' => $selesaiBulanIni,
            ],
            'laporanTerbaru' => $laporanTerbaru,
        ]);
    }

    public function kerusakan(Request $request): Response
    {
        $status = $request->query('status', 'semua');

        $query = LaporanKerusakan::query()->orderByDesc('created_at');

        if (in_array($status, LaporanKerusakan::STATUS, true)) {
            $query->where('status', $status);
        } else {
            $status = 'semua';
        }

        $laporan = $query->limit(50)->get()->map(fn (LaporanKerusakan $l): array => [
            'id' => $l->id,
            'lokasi' => $l->lokasi,
            'deskripsi' => $l->deskripsi,
            'status' => $l->status,
            'foto_url' => $l->fotoUrl(),
            'dibuat' => $l->created_at?->translatedFormat('d M Y H:i'),
        ]);

        return Inertia::render('Sarpras/Kerusakan', [
            'navigation' => $this->nav(),
            'laporan' => $laporan,
            'statusAktif' => $status,
        ]);
    }

    public function ubahStatus(Request $request, LaporanKerusakan $laporan): RedirectResponse
    {
        $data = $request->validate([
            'status' => ['required', 'in:'.implode(',', LaporanKerusakan::STATUS)],
            'biaya' => ['nullable', 'numeric', 'min:0'],
            'catatan' => ['nullable', 'string', 'max:1000'],
        ]);

        try {
            $this->service->ubahStatus($laporan, $data['status'], [
                'biaya' => $data['biaya'] ?? null,
                'catatan' => $data['catatan'] ?? null,
            ]);
        } catch (Throwable $e) {
            return back()->withErrors(['status' => $e->getMessage()]);
        }

        return redirect()->route('tenant.sarpras.kerusakan')->with('success', 'Status laporan diperbarui.');
    }

    public function piket(): Response
    {
        $area = AreaPiket::query()->where('aktif', true)->orderBy('nama')->get(['id', 'nama', 'kode_qr']);

        $hariIni = CeklisPiket::query()
            ->with('area', 'petugas')
            ->whereDate('tanggal', now()->toDateString())
            ->orderByDesc('jam')
            ->get()
            ->map(fn (CeklisPiket $c): array => [
                'id' => $c->id,
                'area' => $c->area?->nama,
                'petugas' => $c->petugas?->name,
                'status' => $c->status,
                'jam' => $c->jam?->format('H:i'),
            ]);

        return Inertia::render('Sarpras/Piket', [
            'navigation' => $this->nav(),
            'areaOptions' => $area,
            'ceklisHariIni' => $hariIni,
        ]);
    }

    public function scanPiket(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'kode_qr' => ['required', 'string', 'max:100'],
            'status' => ['required', 'in:'.implode(',', CeklisPiket::STATUS)],
            'catatan' => ['nullable', 'string', 'max:500'],
        ]);

        try {
            $this->service->scanPiket(Auth::user(), $data['kode_qr'], $data['status'], $data['catatan'] ?? null);
        } catch (Throwable $e) {
            return back()->withErrors(['kode_qr' => $e->getMessage()]);
        }

        return redirect()->route('tenant.sarpras.piket')->with('success', 'Ceklis piket dicatat.');
    }
}
