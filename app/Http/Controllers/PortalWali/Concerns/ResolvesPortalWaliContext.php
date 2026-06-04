<?php

namespace App\Http\Controllers\PortalWali\Concerns;

use App\Models\User;
use App\Services\PortalWali\PortalWaliService;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Modules\Kesantrian\Models\Santri;

trait ResolvesPortalWaliContext
{
    protected function currentWali(Request $request): User
    {
        $user = $request->user();

        abort_unless($user?->can('view_own_children'), 403);

        return $user;
    }

    protected function accessibleSantri(Request $request, PortalWaliService $portalWaliService, int|string $santri): Santri
    {
        return $portalWaliService->findAccessibleSantri(
            $this->currentWali($request),
            $santri,
        );
    }

    protected function navigationItems(iterable $children, ?int $activeChildId = null): array
    {
        if ($children instanceof Collection) {
            $children = $children->all();
        }

        $firstChildId = $activeChildId ?? data_get($children, '0.id');
        $tenantId = tenant('id') ?? request()->route('tenant');

        return [
            [
                'label' => 'Beranda',
                'href' => route('tenant.portal-wali.dashboard', ['tenant' => $tenantId]),
                'active' => request()->routeIs('tenant.portal-wali.dashboard'),
            ],
            [
                'label' => 'Akademik',
                'href' => $firstChildId
                    ? route('tenant.portal-wali.academic', ['tenant' => $tenantId, 'santri' => $firstChildId])
                    : route('tenant.portal-wali.dashboard', ['tenant' => $tenantId]),
                'active' => request()->routeIs('tenant.portal-wali.academic'),
            ],
            [
                'label' => 'Keuangan',
                'href' => $firstChildId
                    ? route('tenant.portal-wali.financial', ['tenant' => $tenantId, 'santri' => $firstChildId])
                    : route('tenant.portal-wali.dashboard', ['tenant' => $tenantId]),
                'active' => request()->routeIs('tenant.portal-wali.financial'),
            ],
            [
                'label' => 'Notif',
                'href' => route('tenant.portal-wali.notifications', ['tenant' => $tenantId]),
                'active' => request()->routeIs('tenant.portal-wali.notifications', 'tenant.portal-wali.izin'),
            ],
        ];
    }
}
