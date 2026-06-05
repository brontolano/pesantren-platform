<?php

namespace Modules\PPDB\Services;

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use Modules\PPDB\Jobs\SendPesertaOtpJob;
use Modules\PPDB\Models\UserOtp;

/**
 * Penerbitan & verifikasi OTP WA akun peserta SPMB (AUTH-1, AUTH-2, AUTH-4, AUTH-5).
 *
 * Patuh Hukum #2 Blueprint: ini BUKAN sistem auth baru — hanya layanan kode
 * sekali pakai di atas `users` + tabel pendukung `user_otps` (F0.4).
 * Kode TIDAK PERNAH disimpan/di-log plaintext: di DB hanya hash (bcrypt),
 * plaintext hanya hidup sesaat untuk dikirim via job WA.
 *
 * Anti-abuse (F1.1.5, AUTH-5):
 * - Permintaan OTP: maks {@see MAX_ISSUE_PER_WINDOW}× per
 *   {@see ISSUE_WINDOW_MINUTES} menit per NOMOR WA (lintas purpose).
 * - Verifikasi: 3× kode salah (lintas kode) = cooldown
 *   {@see COOLDOWN_MINUTES} menit (AUTH-4) — penerbitan kode baru untuk
 *   user+purpose tsb. juga ditahan selama cooldown.
 * - Semua percobaan (terbit/blokir/gagal verifikasi) dicatat ke log
 *   terstruktur TANPA kode plaintext.
 *
 * Catatan: limiter memakai cache central (Redis) yang dipakai bersama semua
 * tenant — semua key WAJIB diberi prefix tenant agar tidak saling mengganggu.
 */
class PesertaOtpService
{
    /** Panjang kode OTP (digit). */
    public const CODE_LENGTH = 6;

    /** Maks. permintaan OTP per jendela waktu per nomor WA (AUTH-5). */
    public const MAX_ISSUE_PER_WINDOW = 3;

    /** Lebar jendela rate limit permintaan OTP (menit). */
    public const ISSUE_WINDOW_MINUTES = 15;

    /** Maks. kode salah (lintas kode) sebelum cooldown (AUTH-4). */
    public const MAX_FAILED_VERIFY = 3;

    /** Lama cooldown setelah percobaan verifikasi habis (menit). */
    public const COOLDOWN_MINUTES = 15;

    /**
     * Terbitkan OTP baru untuk user + purpose, lalu antrekan pengiriman WA.
     *
     * Kode lama yang belum terpakai di-void (used_at diisi) agar hanya ada
     * SATU kode aktif per (user, purpose) — verifikasi selalu kode terakhir.
     *
     * @param  User  $user  akun peserta (role `peserta`)
     * @param  string  $purpose  UserOtp::PURPOSE_AKTIVASI | PURPOSE_RESET
     * @param  string  $targetPhone  nomor WA tujuan, format `62…` (PpdbPhone::normalize)
     * @param  array<string, string>  $variables  variabel tambahan template (mis. nama)
     * @param  bool  $byStaff  true = re-issue oleh PANITIA dari ERP (F1.1.7):
     *                         lewati kuota & RESET cooldown — panitia sudah
     *                         memverifikasi identitas pemohon secara manual,
     *                         dan justru dipanggil saat kuota peserta habis (R-1).
     *
     * @throws ValidationException bila rate limit / cooldown aktif (key `otp`)
     */
    public function issue(
        User $user,
        string $purpose,
        string $targetPhone,
        array $variables = [],
        bool $byStaff = false,
    ): UserOtp {
        $cooldownKey = $this->cooldownKey($user, $purpose);
        $issueKey = $this->issueKey($targetPhone);

        if ($byStaff) {
            // Jalur panitia (F1.1.7): buka blokir agar peserta bisa langsung
            // memakai kode baru. Aman — aksi ini di belakang permission
            // `manage_santri` + audit log, bukan endpoint publik.
            RateLimiter::clear($cooldownKey);
            RateLimiter::clear($issueKey);
        } else {
            // Cooldown verifikasi masih aktif? Kode baru pun tidak akan bisa
            // diverifikasi — tolak di sini agar tidak memboroskan pesan WA.
            if (RateLimiter::tooManyAttempts($cooldownKey, self::MAX_FAILED_VERIFY)) {
                $this->logAttempt('issue_blocked_cooldown', $user, $purpose);

                throw ValidationException::withMessages([
                    'otp' => $this->cooldownMessage($cooldownKey),
                ]);
            }

            // AUTH-5: maks 3 permintaan / 15 menit / NOMOR (lintas purpose & user —
            // nomor sama dipakai 2 pendaftaran tetap dihitung satu kuota).
            if (RateLimiter::tooManyAttempts($issueKey, self::MAX_ISSUE_PER_WINDOW)) {
                $this->logAttempt('issue_blocked_rate_limit', $user, $purpose);

                $menit = (int) ceil(RateLimiter::availableIn($issueKey) / 60);

                throw ValidationException::withMessages([
                    'otp' => "Permintaan kode terlalu sering. Silakan coba lagi dalam {$menit} menit.",
                ]);
            }

            RateLimiter::hit($issueKey, self::ISSUE_WINDOW_MINUTES * 60);
        }

        // Void semua kode aktif sebelumnya (single active code).
        UserOtp::query()
            ->where('user_id', $user->id)
            ->where('purpose', $purpose)
            ->whereNull('used_at')
            ->update(['used_at' => now()]);

        $plainCode = $this->generateCode();

        $otp = UserOtp::create([
            'tenant_id' => tenant()?->getTenantKey(),
            'user_id' => $user->id,
            'kode' => Hash::make($plainCode),
            'purpose' => $purpose,
            'expires_at' => now()->addMinutes(UserOtp::TTL_MINUTES),
            'attempts' => 0,
        ]);

        SendPesertaOtpJob::dispatch(
            tenantId: tenant()?->getTenantKey(),
            targetPhone: $targetPhone,
            plainCode: $plainCode,
            purpose: $purpose,
            variables: $variables,
        );

        $this->logAttempt($byStaff ? 'issued_by_staff' : 'issued', $user, $purpose);

        return $otp;
    }

    /**
     * Verifikasi kode OTP (TTL 5 menit, maks 3 percobaan per kode, sekali pakai).
     *
     * Attempts dinaikkan SEBELUM pencocokan hash — gagal cocok tetap
     * menghanguskan jatah percobaan (anti brute-force). Lintas kode,
     * 3× salah = cooldown 15 menit (AUTH-4) — ditegakkan via RateLimiter.
     *
     * @throws ValidationException pesan bahasa Indonesia awam, key `kode`
     */
    public function verify(User $user, string $purpose, string $plainCode): UserOtp
    {
        $cooldownKey = $this->cooldownKey($user, $purpose);

        if (RateLimiter::tooManyAttempts($cooldownKey, self::MAX_FAILED_VERIFY)) {
            $this->logAttempt('verify_blocked_cooldown', $user, $purpose);

            throw ValidationException::withMessages([
                'kode' => $this->cooldownMessage($cooldownKey),
            ]);
        }

        /** @var UserOtp|null $otp */
        $otp = UserOtp::query()
            ->where('user_id', $user->id)
            ->where('purpose', $purpose)
            ->whereNull('used_at')
            ->orderByDesc('id')
            ->first();

        if ($otp === null || ! $otp->isUsable()) {
            throw ValidationException::withMessages([
                'kode' => 'Kode sudah tidak berlaku. Silakan minta kode baru.',
            ]);
        }

        $otp->increment('attempts');

        if (! Hash::check($plainCode, $otp->kode)) {
            RateLimiter::hit($cooldownKey, self::COOLDOWN_MINUTES * 60);
            $this->logAttempt('verify_failed', $user, $purpose);

            $sisa = max(0, UserOtp::MAX_ATTEMPTS - $otp->attempts);

            throw ValidationException::withMessages([
                'kode' => $sisa > 0
                    ? "Kode salah. Sisa percobaan: {$sisa}."
                    : 'Kode salah dan percobaan habis. Silakan minta kode baru.',
            ]);
        }

        $otp->forceFill(['used_at' => now()])->save();

        RateLimiter::clear($cooldownKey);
        $this->logAttempt('verified', $user, $purpose);

        return $otp;
    }

    private function generateCode(): string
    {
        return (string) random_int(
            10 ** (self::CODE_LENGTH - 1),          // 100000
            (10 ** self::CODE_LENGTH) - 1,           // 999999
        );
    }

    /** Key limiter permintaan OTP — per tenant + nomor WA. */
    private function issueKey(string $targetPhone): string
    {
        return 'ppdb-otp-issue:'.(tenant()?->getTenantKey() ?? 'central').':'.$targetPhone;
    }

    /** Key cooldown verifikasi — per tenant + user + purpose. */
    private function cooldownKey(User $user, string $purpose): string
    {
        return 'ppdb-otp-cooldown:'.(tenant()?->getTenantKey() ?? 'central').":{$user->id}:{$purpose}";
    }

    private function cooldownMessage(string $cooldownKey): string
    {
        $menit = max(1, (int) ceil(RateLimiter::availableIn($cooldownKey) / 60));

        return "Terlalu banyak percobaan gagal. Demi keamanan, silakan coba lagi dalam {$menit} menit.";
    }

    /**
     * Log percobaan OTP (AUTH-5) — metadata saja, TANPA kode/no. HP penuh.
     */
    private function logAttempt(string $event, User $user, string $purpose): void
    {
        Log::info("ppdb_otp.{$event}", [
            'tenant_id' => tenant()?->getTenantKey(),
            'user_id' => $user->id,
            'purpose' => $purpose,
        ]);
    }
}
