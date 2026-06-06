<?php

declare(strict_types=1);

namespace App\Http\Controllers\PortalWali;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\PortalWali\PortalWaliBrandingService;
use App\Services\PortalWali\PortalWaliIdentifierResolver;
use App\Services\PortalWali\WaliAccountService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Modules\PPDB\Models\UserOtp;

class PortalWaliAuthController extends Controller
{
    private const LOGIN_FAILED_MESSAGE = 'Data masuk tidak cocok. Periksa kembali nomor WhatsApp / email dan password Anda.';

    private const LOGIN_MAX_ATTEMPTS = 5;

    public function __construct(
        private readonly WaliAccountService $accountService,
        private readonly PortalWaliIdentifierResolver $resolver,
        private readonly PortalWaliBrandingService $brandingService,
    ) {
    }

    public function showLogin(Request $request): Response|RedirectResponse
    {
        if ($redirect = $this->redirectIfWali($request)) {
            return $redirect;
        }

        return Inertia::render('PortalWali/Auth/Login', [
            'branding' => $this->brandingService->resolve(),
        ]);
    }

    public function showAktivasi(Request $request): Response|RedirectResponse
    {
        if ($redirect = $this->redirectIfWali($request)) {
            return $redirect;
        }

        return Inertia::render('PortalWali/Auth/Aktivasi', [
            'branding' => $this->brandingService->resolve(),
            'identifier' => (string) $request->query('identifier', ''),
        ]);
    }

    public function showLupaPassword(Request $request): Response|RedirectResponse
    {
        if ($redirect = $this->redirectIfWali($request)) {
            return $redirect;
        }

        return Inertia::render('PortalWali/Auth/LupaPassword', [
            'branding' => $this->brandingService->resolve(),
        ]);
    }

    public function aktivasi(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'identifier' => ['required', 'string', 'max:100'],
            'kode' => ['required', 'digits:6'],
            'password' => ['required', 'confirmed', Password::min(8)],
        ], [], [
            'identifier' => 'nomor WhatsApp / email wali',
            'kode' => 'kode verifikasi',
            'password' => 'password',
        ]);

        [$user] = $this->accountService->provisionByIdentifier($validated['identifier']);

        if (! $user->must_reset_password) {
            throw ValidationException::withMessages([
                'identifier' => 'Akun wali sudah aktif. Silakan langsung masuk.',
            ]);
        }

        app(\Modules\PPDB\Services\PesertaOtpService::class)
            ->verify($user, UserOtp::PURPOSE_AKTIVASI, $validated['kode']);

        $this->accountService->markActivated($user, $validated['password']);

        Auth::login($user, remember: true);
        $request->session()->regenerate();

        return redirect('/wali')->with('success', 'Akun wali berhasil diaktifkan. Bismillah, silakan lanjut.');
    }

    public function kirimUlang(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'identifier' => ['required', 'string', 'max:100'],
        ], [], [
            'identifier' => 'nomor WhatsApp / email wali',
        ]);

        $result = $this->accountService->requestActivationOtp($validated['identifier']);

        return back()->with(
            'success',
            'Kode aktivasi sudah dikirim ke WhatsApp '.$this->maskPhone($result['phone']).'.',
        );
    }

    public function login(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'identifier' => ['required', 'string', 'max:100'],
            'password' => ['required', 'string'],
            'remember' => ['nullable', 'boolean'],
        ], [], [
            'identifier' => 'nomor WhatsApp / email wali',
            'password' => 'password',
        ]);

        $throttleKey = 'wali-login:'.sha1(mb_strtolower(trim($validated['identifier'])).'|'.$request->ip());

        if (RateLimiter::tooManyAttempts($throttleKey, self::LOGIN_MAX_ATTEMPTS)) {
            $detik = RateLimiter::availableIn($throttleKey);

            throw ValidationException::withMessages([
                'identifier' => "Terlalu banyak percobaan masuk. Silakan coba lagi dalam {$detik} detik.",
            ]);
        }

        $user = $this->resolver->resolve($validated['identifier']);

        if ($user !== null && $user->must_reset_password) {
            throw ValidationException::withMessages([
                'identifier' => 'Akun wali belum aktif. Silakan aktivasi dulu dengan kode WhatsApp.',
            ]);
        }

        if ($user === null || ! Hash::check($validated['password'], $user->password) || ! $user->hasRole('wali')) {
            RateLimiter::hit($throttleKey, 60);

            throw ValidationException::withMessages([
                'identifier' => self::LOGIN_FAILED_MESSAGE,
            ]);
        }

        RateLimiter::clear($throttleKey);

        Auth::login($user, remember: (bool) ($validated['remember'] ?? false));
        $request->session()->regenerate();

        return redirect()->intended('/wali');
    }

    public function lupaPassword(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'identifier' => ['required', 'string', 'max:100'],
        ], [], [
            'identifier' => 'nomor WhatsApp / email wali',
        ]);

        $result = $this->accountService->requestResetOtp($validated['identifier']);

        return back()->with(
            'success',
            'Kode reset password sudah dikirim ke WhatsApp '.$this->maskPhone($result['phone']).'.',
        );
    }

    public function resetPassword(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'identifier' => ['required', 'string', 'max:100'],
            'kode' => ['required', 'digits:6'],
            'password' => ['required', 'confirmed', Password::min(8)],
        ], [], [
            'identifier' => 'nomor WhatsApp / email wali',
            'kode' => 'kode verifikasi',
            'password' => 'password',
        ]);

        $user = $this->resolver->resolve($validated['identifier']);

        if ($user === null || ! $user->hasRole('wali')) {
            throw ValidationException::withMessages([
                'identifier' => 'Akun wali belum ditemukan. Pastikan nomor WhatsApp atau email yang dipakai benar.',
            ]);
        }

        if ($user->must_reset_password) {
            throw ValidationException::withMessages([
                'identifier' => 'Akun wali belum aktif. Silakan aktivasi dulu dengan kode WhatsApp.',
            ]);
        }

        app(\Modules\PPDB\Services\PesertaOtpService::class)
            ->verify($user, UserOtp::PURPOSE_RESET, $validated['kode']);

        $this->accountService->markActivated($user, $validated['password']);

        Auth::login($user, remember: true);
        $request->session()->regenerate();

        return redirect('/wali')->with('success', 'Password baru sudah tersimpan dan Anda langsung masuk.');
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/wali/login')->with('success', 'Anda telah keluar dari Portal Wali.');
    }

    private function redirectIfWali(Request $request): ?RedirectResponse
    {
        $user = $request->user();

        if ($user instanceof User && $user->hasRole('wali')) {
            return redirect('/wali');
        }

        return null;
    }

    private function maskPhone(string $phone): string
    {
        if (strlen($phone) < 9) {
            return $phone;
        }

        return substr($phone, 0, 5).'•••'.substr($phone, -4);
    }
}
