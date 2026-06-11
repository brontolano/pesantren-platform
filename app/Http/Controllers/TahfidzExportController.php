<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Modules\Akademik\Models\PeriodeAkademik;
use Modules\Tahfidz\Models\Munaqasyah;

/**
 * WP-11 — Cetak daftar peserta wisuda tahfidz per periode (pola rapor-print):
 * HANYA munaqasyah hasil='lulus' DAN is_peserta_wisuda=true.
 */
class TahfidzExportController extends Controller
{
    public function wisudaPrint(Request $request): Response
    {
        abort_unless(auth()->user()?->canAny(['view_akademik', 'manage_akademik']), 403);

        $periode = PeriodeAkademik::query()->findOrFail($request->integer('periode_akademik_id'));

        $peserta = Munaqasyah::query()
            ->pesertaWisuda()
            ->where('periode_akademik_id', $periode->id)
            ->with(['santri.kelas', 'penguji'])
            ->orderBy('tanggal')
            ->get();

        return response()->view('exports.daftar-wisuda-print', [
            'tenantName' => tenant('name') ?? config('app.name'),
            'periode' => $periode,
            'peserta' => $peserta,
            'printedAt' => now(),
        ]);
    }
}
