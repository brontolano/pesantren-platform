<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
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
 * Login bermerek "satu pintu" untuk domain sistem tenant (/masuk).
 *
 * Mengautentikasi guard `web` yang SAMA dengan panel Filament tenant (yang
 * tanpa MFA), lalu selalu kembali ke '/' agar LauncherController mengarahkan
 * sesuai peran. Akun wali/peserta yang memakai form ini tetap dirutekan benar
 * ke portalnya. CTA sekunder (Wali / PPDB) hanya berupa tautan ke alur OTP
 * yang sudah ada — tidak menduplikasi logika auth.
 */
class StaffLoginController extends Controller
{
    public function show(): View|RedirectResponse
    {
        if (Auth::check()) {
            return redirect()->to('/');
        }

        return view('tenant.login', [
            'pesantren' => TenantBranding::name(tenant()?->name ?? 'Portal Pesantren'),
            'logoUrl' => TenantBranding::logoUrl(),
            'primaryColor' => TenantBranding::primaryColor('#0f5132'),
            'waliLoginUrl' => $this->routeOr('tenant.portal-wali.auth.login', '/wali/login'),
            'ppdbUrl' => $this->routeOr('spmb.login', '/spmb/login'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
        ]);

        $key = Str::transliterate(Str::lower($data['email']) . '|' . $request->ip());

        if (RateLimiter::tooManyAttempts($key, 5)) {
            throw ValidationException::withMessages([
                'email' => 'Terlalu banyak percobaan masuk. Coba lagi dalam '
                    . RateLimiter::availableIn($key) . ' detik.',
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

        // Selalu ke '/' → LauncherController memilih tujuan sesuai peran.
        return redirect()->intended('/');
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->to('/masuk');
    }

    private function routeOr(string $name, string $fallback): string
    {
        return RouteFacade::has($name) ? route($name) : url($fallback);
    }
}
