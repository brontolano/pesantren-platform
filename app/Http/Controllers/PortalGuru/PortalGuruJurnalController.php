<?php

declare(strict_types=1);

namespace App\Http\Controllers\PortalGuru;

use App\Http\Controllers\Controller;
use App\Http\Controllers\PortalGuru\Concerns\ResolvesPortalGuruContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Modules\Akademik\Models\JadwalPelajaran;
use Modules\Akademik\Services\JurnalKbmService;

/**
 * F2-PWA — Jurnal KBM 30-detik: form mini muncul SETELAH presensi tersimpan
 * (layar Presensi PWA). Upsert per (guru, jadwal, tanggal) — retry aman.
 */
class PortalGuruJurnalController extends Controller
{
    use ResolvesPortalGuruContext;

    public function __construct(private readonly JurnalKbmService $jurnalService) {}

    public function store(Request $request, int $jadwal): RedirectResponse
    {
        $guru = $this->currentGuru($request);

        $sesi = JadwalPelajaran::query()->with('pelajaran')->findOrFail($jadwal);
        abort_unless((int) $sesi->pelajaran?->guru_pengampu_id === $guru->id, 403);

        $this->jurnalService->simpan($guru, $sesi, $request->only([
            'tanggal', 'materi', 'metode', 'status', 'pengganti_id', 'catatan',
        ]));

        return back()->with('success', 'Jurnal KBM tersimpan.');
    }
}
