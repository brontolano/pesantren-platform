<?php

namespace App\Http\Middleware;

use App\Models\Setting;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    protected $rootView = 'app';

    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    public function share(Request $request): array
    {
        return [
            ...parent::share($request),
            'auth' => [
                'user' => $request->user(),
            ],
            'flash' => [
                'success' => fn () => $request->session()->get('success'),
                'info' => fn () => $request->session()->get('info'),
                'error' => fn () => $request->session()->get('error'),
            ],
            // F5 (sunset instalasi lama): flag per-tenant, default OFF di semua
            // tenant. Dipakai banner "Pasang aplikasi baru" di scope /guru /wali
            // /keuangan saat display-mode standalone. Lihat plan F5.
            'pwaLegacySwSelfdestruct' => fn (): bool => (bool) Setting::getTenantValue('pwa.legacy_sw_selfdestruct', false),
        ];
    }
}
