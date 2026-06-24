<?php

declare(strict_types=1);

namespace App\Services\Jemaah;

use App\Models\User;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Modules\PPDB\Models\UserOtp;
use Modules\PPDB\Services\PesertaOtpService;
use Modules\PPDB\Support\PpdbPhone;

/**
 * Provisioning akun peran publik "jemaah" (1-aplikasi, Fase 2b) — registrasi
 * MANDIRI (self-service), berbeda dari WaliAccountService yang menyalin data
 * dari santri. Jemaah hanya: buat akun by No. WA + password (lewat OTP),
 * role 'jemaah' (izin 'komentar' saja). TANPA tautan santri / multi-anak.
 *
 * NOL logika uang/nilai/posting — murni akun & OTP. Reuse PesertaOtpService
 * (issue/verify generik) + tabel user_otps + pipeline WA n8n/WAHA apa adanya.
 */
class JemaahAccountService
{
    /** Maks. akun jemaah BARU yang boleh dibuat per tenant per jendela (anti spam WA + banjir akun). */
    public const MAX_DAFTAR_PER_WINDOW = 60;

    /** Lebar jendela rate-limit penciptaan akun (menit). */
    public const DAFTAR_WINDOW_MINUTES = 60;

    public function __construct(
        private readonly PesertaOtpService $otpService,
        private readonly JemaahIdentifierResolver $resolver,
    ) {}

    /**
     * Registrasi mandiri jemaah. ANTI-ENUMERASI: respons TIDAK pernah membocorkan
     * apakah nomor/email sudah dipakai (oleh akun jemaah maupun staf/wali/peserta).
     * Pemanggil selalu menerima hasil generik yang sama; UI menampilkan pesan
     * netral "jika data valid, kode dikirim".
     *
     * Perilaku internal (senyap):
     *  - Nomor/email milik akun LAIN (staf/wali/peserta atau jemaah yang sudah aktif)
     *    → tidak melakukan apa-apa (tak membuat akun, tak kirim OTP, tak bocor).
     *  - Akun jemaah yang sudah ada tapi BELUM aktif → kirim ulang OTP aktivasi (idempoten).
     *  - Nomor benar-benar baru → buat akun (password acak, must_reset_password=true,
     *    role 'jemaah') lalu kirim OTP aktivasi; dibatasi laju per-tenant.
     *
     * @return array{phone: string} nomor ternormalisasi (untuk prefil halaman aktivasi)
     */
    public function daftar(string $name, string $phoneInput, ?string $email = null): array
    {
        $name = trim($name);

        if ($name === '') {
            throw ValidationException::withMessages(['name' => 'Nama wajib diisi.']);
        }

        $phone = PpdbPhone::normalize($phoneInput);

        if ($phone === '') {
            throw ValidationException::withMessages([
                'phone' => 'Nomor WhatsApp tidak valid. Contoh: 08123456789.',
            ]);
        }

        $email = $email !== null ? mb_strtolower(trim($email)) : null;
        $email = ($email !== null && $email !== '') ? $email : null;

        // Akun yang sudah memakai nomor/email ini (lintas peran — untuk keputusan
        // internal saja, TIDAK dibocorkan ke pemanggil).
        $existing = User::query()->where('phone', $phone)->first();

        if ($existing === null && $email !== null) {
            $existing = User::query()->where('email', $email)->first();
        }

        if ($existing !== null) {
            // Jemaah belum aktif → bantu dengan kirim ulang OTP (idempoten & aman).
            // Selain itu (jemaah aktif, atau akun staf/wali/peserta) → diam total.
            if ($existing->hasRole('jemaah') && $existing->must_reset_password) {
                $this->silentlyIssueOtp($existing, UserOtp::PURPOSE_JEMAAH_AKTIVASI, $phone, $existing->name);
            }

            return ['phone' => $phone];
        }

        // Nomor baru: batasi laju penciptaan akun per tenant (anti banjir akun &
        // spam pesan WA ke nomor pihak ketiga yang berbiaya gateway).
        $this->guardCreationRate();

        $user = new User;
        $user->name = $name;
        $user->phone = $phone;
        $user->email = $email;
        $user->password = Str::password(40);
        $user->must_reset_password = true;
        $user->save();

        $user->assignRole('jemaah');

        $this->silentlyIssueOtp($user, UserOtp::PURPOSE_JEMAAH_AKTIVASI, $phone, $name);

        return ['phone' => $phone];
    }

    /**
     * Kirim ulang OTP aktivasi untuk jemaah yang belum aktif.
     *
     * @return array{user: User, phone: string}
     */
    public function requestActivationOtp(string $identifier): array
    {
        $user = $this->mustResolve($identifier);

        if (! $user->must_reset_password) {
            throw ValidationException::withMessages([
                'identifier' => 'Akun jemaah sudah aktif. Silakan langsung masuk.',
            ]);
        }

        $phone = $this->targetPhone($user);

        $this->otpService->issue($user, UserOtp::PURPOSE_JEMAAH_AKTIVASI, $phone, ['nama' => $user->name]);

        return ['user' => $user, 'phone' => $phone];
    }

    /**
     * Kirim OTP reset password untuk jemaah yang SUDAH aktif.
     *
     * @return array{user: User, phone: string}
     */
    public function requestResetOtp(string $identifier): array
    {
        $user = $this->mustResolve($identifier);

        if ($user->must_reset_password) {
            throw ValidationException::withMessages([
                'identifier' => 'Akun jemaah belum aktif. Silakan aktivasi dulu dengan kode WhatsApp.',
            ]);
        }

        $phone = $this->targetPhone($user);

        $this->otpService->issue($user, UserOtp::PURPOSE_JEMAAH_RESET, $phone, ['nama' => $user->name]);

        return ['user' => $user, 'phone' => $phone];
    }

    /** Set password baru + tandai akun aktif (sama pola dgn wali markActivated). */
    public function markActivated(User $user, string $plainPassword): void
    {
        $user->password = $plainPassword;
        $user->must_reset_password = false;
        $user->save();
    }

    /**
     * Terbitkan OTP tanpa membocorkan kegagalan ke jalur publik daftar. Bila
     * rate-limit/cooldown menahan penerbitan, respons daftar TETAP generik —
     * jemaah dapat mencoba "kirim ulang" nanti (idempoten). Tidak menelan error
     * tak terduga (biarkan naik agar termonitor).
     */
    private function silentlyIssueOtp(User $user, string $purpose, string $phone, string $name): void
    {
        try {
            $this->otpService->issue($user, $purpose, $phone, ['nama' => $name]);
        } catch (ValidationException) {
            // rate-limit/cooldown OTP — sengaja tidak dibocorkan via daftar publik.
        }
    }

    /**
     * Batas laju penciptaan akun jemaah BARU per tenant. Limiter cache central →
     * WAJIB ber-prefix tenant (konvensi PesertaOtpService). Hanya dikenai saat
     * benar-benar membuat akun baru (existing/resend tidak menghabiskan kuota).
     */
    private function guardCreationRate(): void
    {
        $key = 'jemaah-daftar:'.(tenant()?->getTenantKey() ?? 'central');

        if (RateLimiter::tooManyAttempts($key, self::MAX_DAFTAR_PER_WINDOW)) {
            throw ValidationException::withMessages([
                'phone' => 'Pendaftaran sedang ramai dan dibatasi sementara. Silakan coba lagi beberapa saat lagi.',
            ]);
        }

        RateLimiter::hit($key, self::DAFTAR_WINDOW_MINUTES * 60);
    }

    private function mustResolve(string $identifier): User
    {
        $user = $this->resolver->resolve($identifier);

        if ($user === null) {
            throw ValidationException::withMessages([
                'identifier' => 'Akun jemaah belum ditemukan. Pastikan nomor WhatsApp / email yang dipakai benar.',
            ]);
        }

        return $user;
    }

    private function targetPhone(User $user): string
    {
        $phone = PpdbPhone::normalize((string) ($user->phone ?? ''));

        if ($phone === '') {
            throw ValidationException::withMessages([
                'identifier' => 'Nomor WhatsApp akun ini belum tersedia. Hubungi admin pesantren.',
            ]);
        }

        return $phone;
    }
}
