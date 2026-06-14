<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Controllers\Auth\StaffLoginController;
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
 * - Guest  → halaman login bermerek dirender langsung di '/' (satu pintu).
 * - Login  → diarahkan per peran via App\Support\AppLauncher:
 *            wali/peserta/santri auto-route ke portalnya; staf admin (tanpa PWA
 *            khusus) auto-route ke /admin; staf ber-PWA melihat hub pemilih.
 */
class LauncherController extends Controller
{
    public function __invoke(): RedirectResponse|View
    {
        if (! Auth::check()) {
            // Login terpusat di "/" — render halaman login bermerek langsung di
            // sini (tidak redirect ke /masuk). StaffLoginController::show()
            // mengembalikan view yang sama (untuk tamu).
            return app(StaffLoginController::class)->show();
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
