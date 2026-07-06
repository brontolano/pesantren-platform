<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Controllers\Auth\StaffLoginController;
use App\Models\User;
use App\Support\AppLauncher;
use App\Support\TenantBranding;
use App\Support\TenantCustomizationRegistry;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use Inertia\Response as InertiaResponse;
use Modules\CMS\Http\Controllers\TenantLandingPageController;

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
    public function __invoke(): RedirectResponse|View|InertiaResponse
    {
        if (! Auth::check()) {
            // Satu pintu: TAMU melihat Beranda publik (landing CMS) dengan tombol
            // "Masuk" → /masuk; tidak lagi dipaksa login di "/". Tenant custom
            // (registry 'guest_home' = 'login', mis. albakids) dipertahankan:
            // tamu langsung ke form login bermerek (tahap 3 #343).
            // Fallback defensif: bila landing gagal render (mis. modul CMS belum
            // siap di tenant) → form login (perilaku lama) agar tak pernah 500.
            // Login tetap selalu terjangkau di /masuk (route universal).
            if (TenantCustomizationRegistry::stringValue('guest_home') === 'login') {
                return app(StaffLoginController::class)->show();
            }

            try {
                return app()->call([app(TenantLandingPageController::class), '__invoke']);
            } catch (\Throwable $e) {
                return app(StaffLoginController::class)->show();
            }
        }

        /** @var User $user */
        $user = Auth::user();

        // Jemaah (peran publik) berlabuh di Beranda publik. JANGAN ikuti
        // redirect('/') dari AppLauncher di sini: route ini SENDIRI adalah '/'
        // pada domain 'sistem' → akan loop tak berujung (ERR_TOO_MANY_REDIRECTS).
        // Render landing langsung (sama seperti jalur tamu). Tenant custom
        // ('guest_home' = 'login') tidak punya peran jemaah → fallback login.
        if ($user->hasRole('jemaah')) {
            if (TenantCustomizationRegistry::stringValue('guest_home') === 'login') {
                return app(StaffLoginController::class)->show();
            }

            try {
                return app()->call([app(TenantLandingPageController::class), '__invoke']);
            } catch (\Throwable $e) {
                return app(StaffLoginController::class)->show();
            }
        }

        $dest = AppLauncher::primaryDestination($user, AppLauncher::isMobileRequest(request()));

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
