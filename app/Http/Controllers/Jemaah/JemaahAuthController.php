<?php

declare(strict_types=1);

namespace App\Http\Controllers\Jemaah;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Jemaah\JemaahAccountService;
use App\Services\Jemaah\JemaahBrandingService;
use App\Services\Jemaah\JemaahIdentifierResolver;
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
use Modules\PPDB\Services\PesertaOtpService;

/**
 * Auth peran publik "jemaah" (1-aplikasi, Fase 2b): registrasi mandiri via
 * No. WhatsApp + password (OTP WA), login, lupa-password. Pola disalin dari
 * PortalWaliAuthController namun SELF-SERVICE (tanpa data santri) dan berlabuh
 * ke Beranda publik '/' setelah masuk. Guard 'web' yang sama, scoping per
 * tenant via BelongsToTenant. NOL logika uang/nilai.
 */
class JemaahAuthController extends Controller
{
    private const LOGIN_FAILED_MESSAGE = 'Data masuk tidak cocok. Periksa kembali nomor WhatsApp / email dan password Anda.';

    private const LOGIN_MAX_ATTEMPTS = 5;

    public function __construct(
        private readonly JemaahAccountService $accountService,
        private readonly JemaahIdentifierResolver $resolver,
        private readonly JemaahBrandingService $brandingService,
    ) {}

    public function showLogin(Request $request): Response|RedirectResponse
    {
        if ($redirect = $this->redirectIfJemaah($request)) {
            return $redirect;
        }

        return Inertia::render('Jemaah/Auth/Login', [
            'branding' => $this->brandingService->resolve(),
        ]);
    }

    public function showDaftar(Request $request): Response|RedirectResponse
    {
        if ($redirect = $this->redirectIfJemaah($request)) {
            return $redirect;
        }

        return Inertia::render('Jemaah/Auth/Daftar', [
            'branding' => $this->brandingService->resolve(),
        ]);
    }

    public function showAktivasi(Request $request): Response|RedirectResponse
    {
        if ($redirect = $this->redirectIfJemaah($request)) {
            return $redirect;
        }

        return Inertia::render('Jemaah/Auth/Aktivasi', [
            'branding' => $this->brandingService->resolve(),
            'identifier' => (string) $request->query('identifier', ''),
        ]);
    }

    public function showLupaPassword(Request $request): Response|RedirectResponse
    {
        if ($redirect = $this->redirectIfJemaah($request)) {
            return $redirect;
        }

        return Inertia::render('Jemaah/Auth/LupaPassword', [
            'branding' => $this->brandingService->resolve(),
        ]);
    }

    public function daftar(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'phone' => ['required', 'string', 'max:20'],
            'email' => ['nullable', 'email', 'max:120'],
        ], [], [
            'name' => 'nama',
            'phone' => 'nomor WhatsApp',
            'email' => 'email',
        ]);

        $result = $this->accountService->daftar(
            $validated['name'],
            $validated['phone'],
            $validated['email'] ?? null,
        );

        // Pesan NETRAL & seragam (anti-enumerasi): tidak mengungkap apakah nomor
        // sudah terdaftar. Pemilik nomor yang sah menerima kode di WA-nya.
        return redirect('/jemaah/aktivasi?identifier='.urlencode($result['phone']))
            ->with('success', 'Jika data Anda valid, kode aktivasi 6 angka dikirim ke WhatsApp. Masukkan kode itu di bawah ini.');
    }

    public function aktivasi(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'identifier' => ['required', 'string', 'max:120'],
            'kode' => ['required', 'digits:6'],
            'password' => ['required', 'confirmed', Password::min(8)],
        ], [], [
            'identifier' => 'nomor WhatsApp / email',
            'kode' => 'kode verifikasi',
            'password' => 'password',
        ]);

        $user = $this->resolver->resolve($validated['identifier']);

        // Anti-enumerasi: jangan bedakan "tak ditemukan" vs "sudah aktif" lewat
        // field identifier — keduanya jadi galat netral pada field kode.
        if ($user === null || ! $user->must_reset_password) {
            throw ValidationException::withMessages([
                'kode' => 'Kode tidak valid atau akun tidak dapat diaktivasi. Periksa kembali atau minta kode baru.',
            ]);
        }

        app(PesertaOtpService::class)
            ->verify($user, UserOtp::PURPOSE_JEMAAH_AKTIVASI, $validated['kode']);

        $this->accountService->markActivated($user, $validated['password']);

        Auth::login($user, remember: true);
        $request->session()->regenerate();

        return redirect('/')->with('success', 'Akun jemaah berhasil diaktifkan. Selamat datang.');
    }

    public function kirimUlang(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'identifier' => ['required', 'string', 'max:120'],
        ], [], [
            'identifier' => 'nomor WhatsApp / email',
        ]);

        // Anti-enumerasi: respons selalu seragam, tak peduli akun ada/aktif/tidak.
        try {
            $this->accountService->requestActivationOtp($validated['identifier']);
        } catch (ValidationException) {
            // jangan bocorkan eksistensi/status atau rate-limit.
        }

        return back()->with(
            'success',
            'Jika akun yang belum aktif terkait data itu ada, kode aktivasi dikirim ulang ke WhatsApp-nya.',
        );
    }

    public function login(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'identifier' => ['required', 'string', 'max:120'],
            'password' => ['required', 'string'],
            'remember' => ['nullable', 'boolean'],
        ], [], [
            'identifier' => 'nomor WhatsApp / email',
            'password' => 'password',
        ]);

        // Key limiter cache central → WAJIB ber-prefix tenant agar tak saling
        // mengunci lintas tenant (konvensi PesertaOtpService).
        $throttleKey = 'jemaah-login:'.(tenant()?->getTenantKey() ?? 'central').':'
            .sha1(mb_strtolower(trim($validated['identifier'])).'|'.$request->ip());

        if (RateLimiter::tooManyAttempts($throttleKey, self::LOGIN_MAX_ATTEMPTS)) {
            $detik = RateLimiter::availableIn($throttleKey);

            throw ValidationException::withMessages([
                'identifier' => "Terlalu banyak percobaan masuk. Silakan coba lagi dalam {$detik} detik.",
            ]);
        }

        $user = $this->resolver->resolve($validated['identifier']);

        // Gagal SERAGAM (anti-enumerasi): akun tak ada / bukan jemaah / belum aktif
        // (must_reset_password) / password salah → satu pesan generik, dan SELALU
        // menghabiskan jatah throttle (status "belum aktif" tak lagi bisa diintip
        // tanpa biaya). Halaman login menyediakan tautan Daftar/Aktivasi.
        if ($user === null
            || $user->must_reset_password
            || ! $user->hasRole('jemaah')
            || ! Hash::check($validated['password'], $user->password)
        ) {
            RateLimiter::hit($throttleKey, 60);

            throw ValidationException::withMessages([
                'identifier' => self::LOGIN_FAILED_MESSAGE,
            ]);
        }

        RateLimiter::clear($throttleKey);

        Auth::login($user, remember: (bool) ($validated['remember'] ?? false));
        $request->session()->regenerate();

        return redirect()->intended('/');
    }

    public function lupaPassword(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'identifier' => ['required', 'string', 'max:120'],
        ], [], [
            'identifier' => 'nomor WhatsApp / email',
        ]);

        // Anti-enumerasi: respons selalu seragam, tak mengungkap eksistensi/status akun.
        try {
            $this->accountService->requestResetOtp($validated['identifier']);
        } catch (ValidationException) {
            // jangan bocorkan eksistensi/status atau rate-limit.
        }

        return back()->with(
            'success',
            'Jika akun jemaah aktif terkait data itu ada, kode reset password dikirim ke WhatsApp-nya.',
        );
    }

    public function resetPassword(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'identifier' => ['required', 'string', 'max:120'],
            'kode' => ['required', 'digits:6'],
            'password' => ['required', 'confirmed', Password::min(8)],
        ], [], [
            'identifier' => 'nomor WhatsApp / email',
            'kode' => 'kode verifikasi',
            'password' => 'password',
        ]);

        $user = $this->resolver->resolve($validated['identifier']);

        // Anti-enumerasi: "tak ditemukan" & "belum aktif" jadi galat netral pada kode.
        if ($user === null || ! $user->hasRole('jemaah') || $user->must_reset_password) {
            throw ValidationException::withMessages([
                'kode' => 'Kode tidak valid atau akun tidak dapat direset. Periksa kembali atau minta kode baru.',
            ]);
        }

        app(PesertaOtpService::class)
            ->verify($user, UserOtp::PURPOSE_JEMAAH_RESET, $validated['kode']);

        $this->accountService->markActivated($user, $validated['password']);

        Auth::login($user, remember: true);
        $request->session()->regenerate();

        return redirect('/')->with('success', 'Password baru sudah tersimpan dan Anda langsung masuk.');
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/jemaah/login')->with('success', 'Anda telah keluar.');
    }

    private function redirectIfJemaah(Request $request): ?RedirectResponse
    {
        $user = $request->user();

        if ($user instanceof User && $user->hasRole('jemaah')) {
            return redirect('/');
        }

        return null;
    }
}
