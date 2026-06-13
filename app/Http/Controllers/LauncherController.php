<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\User;
use App\Support\AppLauncher;
use App\Support\TenantBranding;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

/**
 * Otak "launcher" untuk domain tenant bertipe `sistem` (mis.
 * pesantren.asy-syifaa.com). Dipanggil dari route `home` di routes/web.php.
 *
 * - Guest  → dikunci ke halaman login bermerek (/masuk), intended = '/'.
 * - Login  → diarahkan per peran via App\Support\AppLauncher:
 *            wali/peserta/santri auto-route ke portalnya; staf melihat hub.
 */
class LauncherController extends Controller
{
    public function __invoke(): RedirectResponse|View
    {
        if (! Auth::check()) {
            return redirect()->guest('/masuk');
        }

        /** @var User $user */
        $user = Auth::user();
        $dest = AppLauncher::primaryDestination($user);

        if ($dest['kind'] === 'redirect') {
            return redirect()->to($dest['url']);
        }

        return view('tenant.hub', [
            'pesantren' => TenantBranding::name(tenant()?->name ?? 'Portal Pesantren'),
            'logoUrl' => TenantBranding::logoUrl(),
            'primaryColor' => TenantBranding::primaryColor('#0f5132'),
            'userName' => $user->name,
            'pwaHome' => AppLauncher::pwaHome($user),
            'adminPanelUrl' => AppLauncher::adminPanelUrl($user),
            'websiteUrl' => AppLauncher::websiteUrl(),
        ]);
    }
}
