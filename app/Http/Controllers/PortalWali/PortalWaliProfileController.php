<?php

namespace App\Http\Controllers\PortalWali;

use App\Http\Controllers\Controller;
use App\Http\Controllers\PortalWali\Concerns\ResolvesPortalWaliContext;
use App\Services\PortalWali\PortalWaliService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PortalWaliProfileController extends Controller
{
    use ResolvesPortalWaliContext;

    public function __invoke(Request $request, PortalWaliService $portalWaliService): Response
    {
        $wali = $this->currentWali($request);
        $child = $this->accessibleSantri($request, $portalWaliService, (string) $request->route('santri'));
        $dashboard = $portalWaliService->getDashboardPayload($wali);

        return Inertia::render('PortalWali/Profile', [
            'wali' => [
                'id' => $wali->id,
                'name' => $wali->name,
                'email' => $wali->email,
            ],
            'children' => $dashboard['children'],
            'navigation' => $this->navigationItems($dashboard['children'], $child->id),
            ...$portalWaliService->getProfilePayload($wali, $child),
        ]);
    }
}
