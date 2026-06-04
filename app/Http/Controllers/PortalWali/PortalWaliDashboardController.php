<?php

namespace App\Http\Controllers\PortalWali;

use App\Http\Controllers\Controller;
use App\Http\Controllers\PortalWali\Concerns\ResolvesPortalWaliContext;
use App\Services\PortalWali\PortalWaliService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PortalWaliDashboardController extends Controller
{
    use ResolvesPortalWaliContext;

    public function __invoke(Request $request, PortalWaliService $portalWaliService): Response
    {
        $user = $this->currentWali($request);
        $dashboard = $portalWaliService->getDashboardPayload($user);

        return Inertia::render('PortalWali/Dashboard', [
            'wali' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
            ],
            'children' => $dashboard['children'],
            'summary' => $dashboard['summary'],
            'navigation' => $this->navigationItems($dashboard['children']),
        ]);
    }
}
