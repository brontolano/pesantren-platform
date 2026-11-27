<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class TenantLoginController extends Controller
{
    public function create(): View
    {
        return view('auth.tenant-login', ['organization' => app('current-organization')]);
    }

    public function store(Request $request): RedirectResponse
    {
        $credentials = $request->validate(['email' => ['required', 'email'], 'password' => ['required', 'string']]);
        $organization = app('current-organization');
        $key = 'tenant-login:'.$organization->id.':'.strtolower($credentials['email']).'|'.$request->ip();
        if (RateLimiter::tooManyAttempts($key, 5)) {
            throw ValidationException::withMessages(['email' => 'Terlalu banyak percobaan. Coba kembali beberapa menit lagi.']);
        }
        if (! Auth::guard('tenant')->attempt($credentials, false) || ! Auth::guard('tenant')->user()?->is_active) {
            Auth::guard('tenant')->logout();
            RateLimiter::hit($key, 300);
            throw ValidationException::withMessages(['email' => 'Email atau kata sandi tidak cocok.']);
        }

        $request->session()->regenerate();
        $request->session()->put('tenant_context_id', $organization->id);
        RateLimiter::clear($key);

        return redirect()->intended(route('tenant.dashboard'));
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('tenant')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('tenant.login');
    }
}
