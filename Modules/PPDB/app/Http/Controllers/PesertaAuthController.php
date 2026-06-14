<?php

namespace Modules\PPDB\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Modules\PPDB\Database\Seeders\PesertaRoleSeeder;
use Modules\PPDB\Models\CalonSantri;
use Modules\PPDB\Models\UserOtp;
use Modules\PPDB\Services\PesertaAccountService;
use Modules\PPDB\Services\PesertaIdentifierResolver;
use Modules\PPDB\Services\PesertaOtpService;
use Modules\PPDB\Support\PpdbPhone;

/**
 * Auth peserta SPMB: aktivasi OTP WA (AUTH-2), login multi-identifier
 * (AUTH-3, F1.1.3), lupa password via OTP WA (AUTH-4, F1.1.4).
 *
 * Memakai guard `web` existing (Hukum #2 — tidak ada guard baru).
 * Halaman Vue dibangun di F1.2.x; endpoint di sini siap dipakai.
 */
class PesertaAuthController extends Controller
{
    /**
     * Pesan error login generik — AUTH-3: TIDAK membocorkan identifier mana
     * yang salah (anti user-enumeration saat login).
     */
    private const LOGIN_FAILED_MESSAGE =
        'Data masuk tidak cocok. Periksa kembali nomor pendaftaran / No. HP / email dan password Anda.';

    /** Maks. percobaan login gagal per identifier+IP per menit. */
    private const LOGIN_MAX_ATTEMPTS = 5;

    public function __construct(
        private readonly PesertaAccountService $accountService,
        private readonly PesertaOtpService $otpService,
        private readonly PesertaIdentifierResolver $resolver,
    ) {
    }

    /*
    |----------------------------------------------------------------------
    | Halaman GET (Inertia + Vue) — F1.2.2, F1.2.3
    |----------------------------------------------------------------------
    | Branding white-label + flash dibagikan oleh middleware
    | ShareSpmbPageData (F1.2.1) — tidak perlu dikirim per halaman.
    | Peserta yang sudah login diarahkan langsung ke dashboard.
    */

    /**
     * GET /spmb/login — halaman masuk portal (F1.2.3).
     */
    public function showLogin(Request $request): Response|RedirectResponse
    {
        if ($redirect = $this->redirectIfPeserta($request)) {
            return $redirect;
        }

        // "Isi Formulir Pendaftaran": tenant dgn pendaftaran via website eksternal
        // (Setting ppdb_external_url, mis. asy-syifaa) → link ke URL itu; selain itu
        // form internal /ppdb.
        $external = trim((string) (\App\Models\Setting::getTenantStringValue('ppdb_external_url') ?? ''));

        return Inertia::render('PPDB/Portal/Login', [
            'ppdbUrl' => $external !== '' ? $external : '/ppdb',
        ]);
    }

    /**
     * GET /spmb/aktivasi — halaman aktivasi akun (F1.2.2).
     * Nomor pendaftaran bisa di-prefill via query string (?pendaftaran=…)
     * — dipakai redirect pasca-submit formulir publik.
     */
    public function showAktivasi(Request $request): Response|RedirectResponse
    {
        if ($redirect = $this->redirectIfPeserta($request)) {
            return $redirect;
        }

        return Inertia::render('PPDB/Portal/Aktivasi', [
            'pendaftaran' => (string) $request->query('pendaftaran', ''),
        ]);
    }

    /**
     * GET /spmb/lupa-password — halaman lupa password via OTP WA (F1.2.3).
     */
    public function showLupaPassword(Request $request): Response|RedirectResponse
    {
        if ($redirect = $this->redirectIfPeserta($request)) {
            return $redirect;
        }

        return Inertia::render('PPDB/Portal/LupaPassword');
    }

    /**
     * Halaman tamu (login/aktivasi/lupa password) tidak relevan bagi
     * peserta yang sudah masuk → langsung ke dashboard. User login
     * NON-peserta (staf/wali) dibiarkan melihat halaman tamu — mereka
     * akan ditolak middleware `peserta` bila memaksa masuk area peserta.
     */
    private function redirectIfPeserta(Request $request): ?RedirectResponse
    {
        $user = $request->user();

        if ($user !== null && $user->hasRole(PesertaRoleSeeder::ROLE)) {
            return redirect('/spmb');
        }

        return null;
    }

    /**
     * POST /spmb/aktivasi — verifikasi OTP + set password + auto-login.
     */
    public function aktivasi(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'pendaftaran' => ['required', 'string', 'max:50'],
            'kode' => ['required', 'digits:'.PesertaOtpService::CODE_LENGTH],
            'password' => ['required', 'confirmed', Password::min(8)],
        ], [], [
            'pendaftaran' => 'nomor pendaftaran',
            'kode' => 'kode verifikasi',
            'password' => 'password',
        ]);

        $calon = $this->resolveCalon($validated['pendaftaran']);
        $user = $this->resolvePesertaUser($calon);

        if ($user === null) {
            throw ValidationException::withMessages([
                'pendaftaran' => 'Akun untuk pendaftaran ini belum dibuat. Silakan minta kode baru atau hubungi panitia.',
            ]);
        }

        if (! $user->must_reset_password) {
            throw ValidationException::withMessages([
                'pendaftaran' => 'Akun sudah aktif. Silakan langsung masuk (login).',
            ]);
        }

        // TTL 5 menit, maks percobaan & cooldown — ditegakkan di service.
        $this->otpService->verify($user, UserOtp::PURPOSE_AKTIVASI, $validated['kode']);

        $this->accountService->markActivated($user, $validated['password']);

        Auth::login($user, remember: true);
        $request->session()->regenerate();

        return redirect('/spmb')
            ->with('success', 'Akun berhasil diaktifkan. Selamat datang!');
    }

    /**
     * POST /spmb/aktivasi/kirim-ulang — kirim ulang OTP aktivasi.
     * Rate limit per-nomor ditegakkan di PesertaOtpService (F1.1.5);
     * throttle route = lapisan tambahan per IP.
     */
    public function kirimUlang(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'pendaftaran' => ['required', 'string', 'max:50'],
        ], [], [
            'pendaftaran' => 'nomor pendaftaran',
        ]);

        $calon = $this->resolveCalon($validated['pendaftaran']);
        $user = $this->resolvePesertaUser($calon);

        if ($user !== null && ! $user->must_reset_password) {
            throw ValidationException::withMessages([
                'pendaftaran' => 'Akun sudah aktif. Silakan langsung masuk (login).',
            ]);
        }

        // Idempotent: provision bila akun belum terbit (pendaftaran lama /
        // kegagalan saat submit), lalu terbitkan kode baru (kode lama hangus).
        $this->accountService->provisionAndSendActivationOtp($calon);

        $maskedPhone = $this->maskPhone(PpdbPhone::normalize((string) $calon->no_hp_wali));

        return back()->with(
            'success',
            "Kode verifikasi baru telah dikirim ke WhatsApp {$maskedPhone}.",
        );
    }

    /**
     * POST /spmb/login — login multi-identifier (AUTH-3, F1.1.3).
     *
     * Identifier: nomor pendaftaran (username utama, F0.1) / No. HP / email.
     * Pesan gagal SELALU generik — tidak membocorkan identifier mana yang
     * salah. Brute-force ditahan RateLimiter per identifier+IP.
     */
    public function login(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'identifier' => ['required', 'string', 'max:100'],
            'password' => ['required', 'string'],
            'remember' => ['nullable', 'boolean'],
        ], [], [
            'identifier' => 'nomor pendaftaran / No. HP / email',
            'password' => 'password',
        ]);

        $throttleKey = 'spmb-login:'.sha1(mb_strtolower(trim($validated['identifier'])).'|'.$request->ip());

        if (RateLimiter::tooManyAttempts($throttleKey, self::LOGIN_MAX_ATTEMPTS)) {
            $detik = RateLimiter::availableIn($throttleKey);

            throw ValidationException::withMessages([
                'identifier' => "Terlalu banyak percobaan masuk. Silakan coba lagi dalam {$detik} detik.",
            ]);
        }

        $user = $this->resolver->resolve($validated['identifier']);

        // Akun ada tapi belum aktivasi → arahkan ke aktivasi, bukan error generik
        // (persona ortu awam, mitigasi R-4). Password placeholder tidak diketahui
        // siapa pun sehingga jalur login normal memang mustahil.
        if ($user !== null && $user->must_reset_password) {
            throw ValidationException::withMessages([
                'identifier' => 'Akun Anda belum diaktifkan. Silakan aktivasi dulu dengan kode yang dikirim ke WhatsApp, atau minta kode baru di halaman aktivasi.',
            ]);
        }

        if ($user === null || ! Hash::check($validated['password'], $user->password)) {
            RateLimiter::hit($throttleKey, 60);

            throw ValidationException::withMessages([
                'identifier' => self::LOGIN_FAILED_MESSAGE,
            ]);
        }

        RateLimiter::clear($throttleKey);

        Auth::login($user, remember: (bool) ($validated['remember'] ?? false));
        $request->session()->regenerate();

        return redirect()->intended('/spmb');
    }

    /**
     * POST /spmb/logout — akhiri sesi peserta.
     */
    public function logout(Request $request): RedirectResponse
    {
        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/spmb/login')
            ->with('success', 'Anda telah keluar. Sampai jumpa kembali.');
    }

    /**
     * POST /spmb/lupa-password — kirim OTP reset ke WA terdaftar (AUTH-4, F1.1.4).
     *
     * Rate limit per-nomor + cooldown 15 menit setelah 3× gagal ditegakkan
     * di PesertaOtpService (F1.1.5).
     */
    public function lupaPassword(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'identifier' => ['required', 'string', 'max:100'],
        ], [], [
            'identifier' => 'nomor pendaftaran / No. HP / email',
        ]);

        $user = $this->resolver->resolve($validated['identifier']);

        if ($user === null) {
            throw ValidationException::withMessages([
                'identifier' => 'Data tidak ditemukan. Periksa kembali, atau hubungi panitia bila Anda yakin sudah mendaftar.',
            ]);
        }

        if ($user->must_reset_password) {
            throw ValidationException::withMessages([
                'identifier' => 'Akun Anda belum diaktifkan. Silakan aktivasi dulu lewat halaman aktivasi — tidak perlu reset password.',
            ]);
        }

        $targetPhone = $this->resolveOtpPhone($user);

        if ($targetPhone === '') {
            throw ValidationException::withMessages([
                'identifier' => 'Nomor WhatsApp Anda tidak ditemukan di data kami. Silakan hubungi panitia untuk pembaruan nomor.',
            ]);
        }

        $this->otpService->issue(
            $user,
            UserOtp::PURPOSE_RESET,
            $targetPhone,
            ['nama' => (string) $user->name],
        );

        return back()->with(
            'success',
            'Kode reset password telah dikirim ke WhatsApp '.$this->maskPhone($targetPhone).'.',
        );
    }

    /**
     * POST /spmb/lupa-password/reset — verifikasi OTP reset + password baru.
     * Setelah sukses, peserta langsung masuk (tanpa login ulang).
     */
    public function resetPassword(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'identifier' => ['required', 'string', 'max:100'],
            'kode' => ['required', 'digits:'.PesertaOtpService::CODE_LENGTH],
            'password' => ['required', 'confirmed', Password::min(8)],
        ], [], [
            'identifier' => 'nomor pendaftaran / No. HP / email',
            'kode' => 'kode verifikasi',
            'password' => 'password baru',
        ]);

        $user = $this->resolver->resolve($validated['identifier']);

        if ($user === null || $user->must_reset_password) {
            throw ValidationException::withMessages([
                'identifier' => 'Data tidak ditemukan atau akun belum aktif. Periksa kembali, atau hubungi panitia.',
            ]);
        }

        // Cooldown 3× gagal = 15 menit (AUTH-4) — ditegakkan di service.
        $this->otpService->verify($user, UserOtp::PURPOSE_RESET, $validated['kode']);

        $this->accountService->markActivated($user, $validated['password']);

        Auth::login($user, remember: true);
        $request->session()->regenerate();

        return redirect('/spmb')
            ->with('success', 'Password berhasil diganti. Selamat datang kembali!');
    }

    private function resolveCalon(string $nomorPendaftaran): CalonSantri
    {
        $calon = CalonSantri::query()
            ->where('nomor_pendaftaran', trim($nomorPendaftaran))
            ->first();

        if ($calon === null) {
            throw ValidationException::withMessages([
                'pendaftaran' => 'Nomor pendaftaran tidak ditemukan. Periksa kembali nomor Anda.',
            ]);
        }

        return $calon;
    }

    private function resolvePesertaUser(CalonSantri $calon): ?User
    {
        if ($calon->user_id === null) {
            return null;
        }

        return User::query()->find($calon->user_id);
    }

    /**
     * Nomor WA tujuan OTP reset: `users.phone`, fallback `no_hp_wali` dari
     * pendaftaran tertaut (kasus nomor dipakai ≥ 2 pendaftaran sehingga
     * `phone` akun ke-2 kosong — lihat PesertaAccountService::claimablePhone).
     */
    private function resolveOtpPhone(User $user): string
    {
        if (! empty($user->phone)) {
            return PpdbPhone::normalize((string) $user->phone);
        }

        $calon = CalonSantri::query()->where('user_id', $user->id)->first();

        return PpdbPhone::normalize((string) ($calon?->no_hp_wali ?? ''));
    }

    /** 6281234567890 → 62812•••7890 (jangan bocorkan nomor penuh di layar publik). */
    private function maskPhone(string $phone): string
    {
        if (strlen($phone) < 9) {
            return 'wali yang terdaftar';
        }

        return substr($phone, 0, 5).'•••'.substr($phone, -4);
    }
}
