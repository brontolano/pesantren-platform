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

        abort_unless(
            $user instanceof User
            && $user->hasRole('wali')
            && $user->can('view_own_children'),
            403,
        );

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
        $dashboardHref = route('tenant.portal-wali.dashboard', ['tenant' => $tenantId]);
        $financialHref = $firstChildId
            ? route('tenant.portal-wali.financial', ['tenant' => $tenantId, 'santri' => $firstChildId])
            : $dashboardHref;
        $financialSection = request()->query('section', 'tagihan');

        return [
            [
                'key' => 'beranda',
                'label' => 'Beranda',
                'href' => $dashboardHref,
                'active' => request()->routeIs('tenant.portal-wali.dashboard'),
            ],
            [
                'key' => 'tagihan',
                'label' => 'Tagihan',
                'href' => $firstChildId
                    ? "{$financialHref}?section=tagihan#tagihan"
                    : $dashboardHref,
                'active' => request()->routeIs('tenant.portal-wali.financial') && $financialSection !== 'tabungan',
            ],
            [
                'key' => 'pembayaran',
                'label' => 'Pembayaran',
                'href' => route('tenant.portal-wali.pembayaran.index', ['tenant' => $tenantId]),
                'active' => request()->routeIs('tenant.portal-wali.pembayaran.*'),
            ],
            [
                'key' => 'tabungan',
                'label' => 'Tabungan',
                'href' => $firstChildId
                    ? "{$financialHref}?section=tabungan#tabungan"
                    : $dashboardHref,
                'active' => request()->routeIs('tenant.portal-wali.financial') && $financialSection === 'tabungan',
            ],
            [
                'key' => 'akun',
                'label' => 'Akun',
                'href' => route('tenant.portal-wali.notifications', ['tenant' => $tenantId]),
                'active' => request()->routeIs('tenant.portal-wali.izin'),
            ],
        ];
    }
}
