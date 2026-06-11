<?php

namespace App\Http\Controllers\PortalWali;

use App\Http\Controllers\Controller;
use App\Http\Controllers\PortalWali\Concerns\ResolvesPortalWaliContext;
use App\Services\PortalWali\PortalWaliService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use InvalidArgumentException;
use Modules\Kegiatan\Models\PengajuanJenguk;
use Modules\Kegiatan\Services\PenjengukanService;

/**
 * WP-30 — Portal Wali: ajukan slot jenguk + lihat status pengajuan.
 * Wali hanya untuk anaknya sendiri (access_wali) — dijaga di
 * PenjengukanService::ajukan (403) + daftar anak dari payload dashboard.
 */
class PortalWaliJengukController extends Controller
{
    use ResolvesPortalWaliContext;

    public function __construct(private readonly PenjengukanService $penjengukan) {}

    public function index(Request $request, PortalWaliService $portalWaliService): Response
    {
        $wali = $this->currentWali($request);
        $dashboard = $portalWaliService->getDashboardPayload($wali);
        $jadwal = $this->penjengukan->jadwal();

        $pengajuan = PengajuanJenguk::query()
            ->with('santri:id,nama,nis')
            ->where('pengaju_id', $wali->id)
            ->orderByDesc('tanggal')
            ->limit(30)
            ->get()
            ->map(fn (PengajuanJenguk $p): array => [
                'id' => $p->id,
                'santri' => $p->santri?->nama,
                'tanggal' => $p->tanggal->format('d-m-Y'),
                'status' => $p->status,
                'catatan' => $p->catatan,
            ])->values();

        return Inertia::render('PortalWali/Jenguk', [
            'wali' => ['id' => $wali->id, 'name' => $wali->name, 'email' => $wali->email],
            'children' => $dashboard['children'],
            'navigation' => $this->navigationItems($dashboard['children']),
            'jadwal' => $jadwal,
            'pengajuan' => $pengajuan,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'santri_id' => ['required', 'integer'],
            'tanggal' => ['required', 'date'],
            'catatan' => ['nullable', 'string', 'max:500'],
            'client_ref' => ['required', 'uuid'],
        ]);

        try {
            $pengajuan = $this->penjengukan->ajukan($this->currentWali($request), $data);
        } catch (InvalidArgumentException $e) {
            return back()->withErrors(['tanggal' => $e->getMessage()]);
        }

        $pesan = $pengajuan->status === 'dikonfirmasi'
            ? 'Slot jenguk DIKONFIRMASI. Sampai jumpa di pesantren.'
            : 'Pengajuan jenguk terkirim — menunggu konfirmasi pengurus.';

        return back()->with('success', $pesan);
    }

    /** Batalkan pengajuan milik sendiri (diajukan|dikonfirmasi → batal). */
    public function batal(Request $request, PengajuanJenguk $pengajuan): RedirectResponse
    {
        try {
            $this->penjengukan->batal($pengajuan, $this->currentWali($request));
        } catch (InvalidArgumentException $e) {
            return back()->withErrors(['batal' => $e->getMessage()]);
        }

        return back()->with('success', 'Pengajuan jenguk dibatalkan.');
    }
}
