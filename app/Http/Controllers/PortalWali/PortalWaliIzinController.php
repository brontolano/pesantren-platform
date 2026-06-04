<?php

namespace App\Http\Controllers\PortalWali;

use App\Http\Controllers\Controller;
use App\Http\Controllers\PortalWali\Concerns\ResolvesPortalWaliContext;
use App\Http\Requests\PortalWali\StorePortalWaliIzinRequest;
use App\Services\PortalWali\PortalWaliService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PortalWaliIzinController extends Controller
{
    use ResolvesPortalWaliContext;

    public function index(Request $request, PortalWaliService $portalWaliService): Response
    {
        $wali = $this->currentWali($request);
        $dashboard = $portalWaliService->getDashboardPayload($wali);

        return Inertia::render('PortalWali/Izin', [
            'wali' => [
                'id' => $wali->id,
                'name' => $wali->name,
                'email' => $wali->email,
            ],
            'children' => $dashboard['children'],
            'navigation' => $this->navigationItems($dashboard['children']),
            ...$portalWaliService->getIzinPayload($wali),
        ]);
    }

    public function store(StorePortalWaliIzinRequest $request, PortalWaliService $portalWaliService): RedirectResponse
    {
        $portalWaliService->createIzin($this->currentWali($request), $request->validated());

        return redirect()
            ->route('tenant.portal-wali.izin', ['tenant' => tenant('id') ?? $request->route('tenant')])
            ->with('success', 'Pengajuan izin wali berhasil dikirim.');
    }
}
