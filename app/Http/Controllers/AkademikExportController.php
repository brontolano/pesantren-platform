<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Modules\Akademik\Models\PeriodeAkademik;
use Modules\Akademik\Models\Rapor;
use Modules\Akademik\Services\KelulusanService;
use Modules\Kesantrian\Models\Alumni;
use Symfony\Component\HttpFoundation\Response;

class AkademikExportController extends Controller
{
    public function raporPrint(Request $request, Rapor $rapor): Response
    {
        abort_unless(auth()->user()?->canAny(['view_akademik', 'manage_akademik']), 403);

        $rapor->load([
            'santri.kelas',
            'periodeAkademik',
        ]);

        return response()->view('exports.rapor-print', [
            'tenantName' => tenant('name') ?? config('app.name'),
            'rapor' => $rapor,
            'printedAt' => now(),
            'periode' => $rapor->periodeAkademik ?? PeriodeAkademik::query()->find($request->integer('periode_akademik_id')),
        ]);
    }

    /** WP-08 — Cetak syahadah (sertifikat internal) dari template per lembaga. */
    public function syahadahPrint(Alumni $alumni, KelulusanService $service): Response
    {
        abort_unless(auth()->user()?->canAny(['view_santri', 'manage_santri']), 403);
        // Dokumen resmi tanpa nomor seri tidak boleh tercetak/beredar.
        abort_unless(filled($alumni->nomor_syahadah), 404);

        return response()->view('exports.syahadah-print', [
            'tenantName' => tenant('name') ?? config('app.name'),
            'alumni' => $alumni->load(['santri', 'lembaga']),
            'isi' => $service->renderSyahadah($alumni),
        ]);
    }
}
