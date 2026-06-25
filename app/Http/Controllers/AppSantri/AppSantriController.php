<?php

namespace App\Http\Controllers\AppSantri;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\SantriLoginMethod;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Kesantrian\Models\Santri;
use Modules\Keuangan\Services\TabunganService;

/**
 * App Santri (/app) — login pakai NIS (bukan email; santri hafal NIS-nya)
 * + dashboard ringkas. Akun dibuat SantriAccountService (role 'santri').
 * Tahap lanjut: WebAuthn fingerprint (mode dev) → smart card NFC.
 */
class AppSantriController extends Controller
{
    public function showLogin(): Response
    {
        if (Auth::check() && Auth::user()->hasRole('santri')) {
            return Inertia::render('AppSantri/Home', $this->homeProps())->toResponse(request());
        }

        return Inertia::render('AppSantri/Login', [
            'metodeLogin' => SantriLoginMethod::forLogin(),
        ]);
    }

    public function login(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'nis' => ['required', 'string', 'max:50'],
            'password' => ['required', 'string'],
        ]);

        // Anti brute-force per NIS+IP.
        $throttleKey = 'app-santri:'.$request->ip().':'.$data['nis'];
        if (RateLimiter::tooManyAttempts($throttleKey, 5)) {
            return back()->withErrors(['nis' => 'Terlalu banyak percobaan. Coba lagi dalam satu menit.']);
        }
        RateLimiter::hit($throttleKey, 60);

        $santri = Santri::query()->where('nis', $data['nis'])->first();
        $user = $santri?->user_id
            ? User::withoutGlobalScopes()->find($santri->user_id)
            : null;

        if ($user === null || ! Auth::attempt(['email' => $user->email, 'password' => $data['password']])) {
            return back()->withErrors(['nis' => 'NIS atau password salah.']);
        }

        RateLimiter::clear($throttleKey);
        $request->session()->regenerate();

        return redirect()->route('tenant.app-santri.home', ['tenant' => tenant('id')]);
    }

    public function home(Request $request): Response|RedirectResponse
    {
        if (! $request->user()?->hasRole('santri')) {
            Auth::logout();

            return redirect()->route('tenant.app-santri.login', ['tenant' => tenant('id')]);
        }

        return Inertia::render('AppSantri/Home', $this->homeProps());
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('tenant.app-santri.login', ['tenant' => tenant('id')]);
    }

    /** @return array<string, mixed> */
    private function homeProps(): array
    {
        $santri = Santri::query()->where('user_id', Auth::id())->first();

        $saldo = 0.0;
        if ($santri !== null) {
            try {
                $saldo = app(TabunganService::class)->getSaldo($santri);
            } catch (\Throwable) {
                // Modul Keuangan belum aktif — tampilkan 0.
            }
        }

        return [
            'nama' => $santri?->nama ?? Auth::user()?->name ?? 'Santri',
            'nis' => $santri?->nis ?? '-',
            'status' => $santri?->status ?? '-',
            'saldo' => $saldo,
        ];
    }
}
