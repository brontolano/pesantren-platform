<?php

namespace App\Http\Controllers\PortalWali;

use App\Http\Controllers\Controller;
use App\Http\Controllers\PortalWali\Concerns\ResolvesPortalWaliContext;
use App\Services\PortalWali\PortalWaliService;
use Illuminate\Http\Request;
use Modules\Akademik\Models\Rapor;
use Symfony\Component\HttpFoundation\Response;

class PortalWaliRaporDownloadController extends Controller
{
    use ResolvesPortalWaliContext;

    public function __invoke(Request $request, PortalWaliService $portalWaliService): Response
    {
        $child = $this->accessibleSantri($request, $portalWaliService, (string) $request->route('santri'));
        $rapor = Rapor::query()->findOrFail($request->route('rapor'));

        abort_unless($rapor->santri_id === $child->id, 404);

        $rapor->load(['santri.kelas', 'periodeAkademik']);

        return response()->view('exports.rapor-print', [
            'tenantName' => tenant('name') ?? config('app.name'),
            'rapor' => $rapor,
            'printedAt' => now(),
            'periode' => $rapor->periodeAkademik,
        ]);
    }
}
