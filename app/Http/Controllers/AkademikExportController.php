<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Modules\Akademik\Models\PeriodeAkademik;
use Modules\Akademik\Models\Rapor;
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
}
