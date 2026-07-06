<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Support\AppLauncher;
use App\Support\TenantBranding;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route as RouteFacade;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Login bermerek "satu pintu" untuk staf tenant. Form dirender di '/' (domain
 * sistem, oleh LauncherController saat tamu) DAN di '/masuk' (UNIVERSAL, semua
 * domain tenant — termasuk subdomain yang '/'-nya dipakai website publik).
 * Form mem-POST ke /masuk (tenant.masuk.store).
 *
 * Mengautentikasi guard `web` yang SAMA dengan panel Filament tenant (tanpa
 * MFA), lalu mengarahkan ke tujuan per peran via AppLauncher (staf → /admin,
 * wali/peserta → portalnya) — BUKAN selalu '/', supaya staf di tenant subdomain
 * tak nyangkut di website publik. CTA sekunder (Wali / PPDB) hanya tautan ke
 * alur login masing-masing — tidak menduplikasi logika auth.
 */
class StaffLoginController extends Controller
{
    public function show(): View|RedirectResponse
    {
        if (Auth::check()) {
            $dest = AppLauncher::primaryDestination(Auth::user(), AppLauncher::isMobileRequest(request()));

            return redirect()->to($dest['kind'] === 'redirect' ? $dest['url'] : '/');
        }

        // AlbaKids (tenant custom playful) tetap pakai login lama; tenant lain
        // dapat login "Nur" (mockup terkunci). Form/action/field identik di keduanya.
        $viewLogin = str_contains(\App\Support\TenantCustomizationRegistry::bodyClass(), 'tenant-albakids')
            ? 'tenant.login'
            : 'tenant.login-nur';

        return view($viewLogin, [
            'pesantren' => TenantBranding::name(tenant()?->name ?? 'Portal Pesantren'),
            'logoUrl' => TenantBranding::logoUrl(),
            'heroImageUrl' => TenantBranding::heroImageUrl(),
            'primaryColor' => TenantBranding::primaryColor('#0f5132'),
            'waliLoginUrl' => $this->routeOr('tenant.portal-wali.auth.login', '/wali/login'),
            'ppdbUrl' => $this->routeOr('spmb.login', '/spmb/login'),
            // Tenant custom (varian login 'albakids') DIPAGARI dari perubahan
            // desain platform — blade kini membaca kelas body langsung dari
            // registry (TenantCustomizationRegistry::bodyClass(), tahap 4 #343);
            // prop isAlbaKids tidak diperlukan lagi.
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
        ]);

        $key = Str::transliterate(Str::lower($data['email']).'|'.$request->ip());

        if (RateLimiter::tooManyAttempts($key, 5)) {
            throw ValidationException::withMessages([
                'email' => 'Terlalu banyak percobaan masuk. Coba lagi dalam '
                    .RateLimiter::availableIn($key).' detik.',
            ]);
        }

        if (! Auth::attempt(
            ['email' => $data['email'], 'password' => $data['password']],
            $request->boolean('remember'),
        )) {
            RateLimiter::hit($key, 60);

            throw ValidationException::withMessages([
                'email' => 'Email atau kata sandi salah.',
            ]);
        }

        RateLimiter::clear($key);
        $request->session()->regenerate();

        // Tujuan per peran via AppLauncher (staf → /admin, wali → portal, dst) —
        // BUKAN selalu '/', sebab pada tenant subdomain '/' = website publik
        // (bukan launcher), sehingga staf akan nyangkut di website. intended()
        // tetap dihormati untuk deep-link yang memicu login.
        // Device NYATA dari sinyal klien 'dv' (viewport/pointer, diset JS di form
        // login) DIUTAMAKAN atas User-Agent — UA bisa keliru (mis. iPad baru
        // lapor UA desktop). Disimpan di sesi agar request berikut (launcher '/',
        // /staf) konsisten memakai keputusan yang sama. Fallback UA bila 'dv'
        // kosong (JS mati).
        $dv = (string) $request->input('dv');
        $isMobile = $dv === 'm' ? true : ($dv === 'd' ? false : AppLauncher::isMobileRequest($request));
        $request->session()->put('device_mobile', $isMobile);

        $dest = AppLauncher::primaryDestination(Auth::user(), $isMobile);
        $tujuan = $dest['kind'] === 'redirect' ? $dest['url'] : '/';

        return redirect()->intended($tujuan);
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        // Login terpusat di "/" (satu pintu) — kembalikan ke sana setelah keluar.
        return redirect()->to('/');
    }

    private function routeOr(string $name, string $fallback): string
    {
        return RouteFacade::has($name) ? route($name) : url($fallback);
    }
}
