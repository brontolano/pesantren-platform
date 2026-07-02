<?php

namespace Modules\PPDB\Services;

use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Modules\PPDB\Database\Seeders\PesertaRoleSeeder;
use Modules\PPDB\Models\CalonSantri;
use Modules\PPDB\Models\UserOtp;
use Modules\PPDB\Support\PpdbPhone;

/**
 * Akun peserta PWA SPMB — auto-provision saat formulir publik disubmit (AUTH-1, F1.1.1).
 *
 * Hukum #2 Blueprint (SATU sistem auth): akun peserta = baris `users` + role
 * Spatie `peserta`, ditautkan via `calon_santri.user_id` (unique, F0.3).
 * BUKAN guard/tabel login baru.
 *
 * Alur: daftar → akun terbit (password placeholder acak, BELUM bisa dipakai) →
 * OTP aktivasi dikirim ke WA wali → peserta verifikasi OTP + set password
 * sendiri (F1.1.2) → login.
 *
 * Username utama = NOMOR PENDAFTARAN (keputusan F0.1) — di-resolve lewat
 * `calon_santri.nomor_pendaftaran → user_id`, TANPA kolom username baru.
 * Login alternatif: No. HP (`users.phone`) / email (`users.email`) — F1.1.3.
 */
class PesertaAccountService
{
    public function __construct(
        private readonly PesertaOtpService $otpService,
    ) {}

    /**
     * Provision akun peserta untuk sebuah pendaftaran + kirim OTP aktivasi.
     * Dipanggil dari PublicPpdbController@store. Idempotent — aman dipanggil
     * ulang (mis. re-issue OTP oleh panitia, F1.1.7).
     */
    public function provisionAndSendActivationOtp(CalonSantri $calon): User
    {
        $user = $this->provisionForRegistration($calon);

        $this->otpService->issue(
            $user,
            UserOtp::PURPOSE_AKTIVASI,
            PpdbPhone::normalize((string) $calon->no_hp_wali),
            ['nama' => (string) $calon->nama],
        );

        return $user;
    }

    /**
     * Buat user role `peserta` + tautkan `calon_santri.user_id` (bila belum ada).
     */
    public function provisionForRegistration(CalonSantri $calon): User
    {
        $user = $calon->user_id !== null
            ? User::query()->find($calon->user_id)
            : null;

        if ($user !== null) {
            if (! $user->hasRole(PesertaRoleSeeder::ROLE)) {
                $user->assignRole(PesertaRoleSeeder::ROLE);
            }

            return $user;
        }

        $user = new User;
        $user->name = (string) $calon->nama;
        // phone & email unik per DB tenant — klaim hanya bila belum dipakai akun
        // lain (kasus: 1 wali mendaftarkan 2 anak → anak ke-2 login pakai nomor
        // pendaftaran; multi-anak per akun = backlog pasca-v1).
        $user->phone = $this->claimablePhone(PpdbPhone::normalize((string) $calon->no_hp_wali));
        $user->email = $this->claimableEmail($calon->email_wali);
        // Placeholder acak — TIDAK dikirim ke siapa pun; password asli di-set
        // peserta sendiri saat aktivasi OTP (F1.1.2). Cast 'hashed' meng-hash.
        $user->password = Str::password(40);
        $user->must_reset_password = true; // belum aktivasi
        $user->save();                     // BelongsToTenant mengisi tenant_id

        $user->assignRole(PesertaRoleSeeder::ROLE);

        $calon->user_id = $user->id;
        $calon->save();

        return $user;
    }

    /** Tandai akun selesai aktivasi (password sudah di-set peserta). */
    public function markActivated(User $user, string $plainPassword): void
    {
        $user->password = $plainPassword;       // cast 'hashed'
        $user->must_reset_password = false;
        $user->save();
    }

    /**
     * F1.1.7 — Re-issue OTP + koreksi nomor WA oleh PANITIA dari ERP
     * (mitigasi R-1 "OTP tidak sampai" & R-2 "salah tulis nomor").
     *
     * Aksi dalam satu langkah:
     *  1. Simpan nomor WA terkoreksi ke `calon_santri.no_hp_wali`.
     *  2. Sinkronkan `users.phone` (identifier login alternatif): di-update
     *     bila slot bebas; di-KOSONGKAN bila nomor dipakai akun lain — nomor
     *     lama yang salah TIDAK boleh tertinggal (orang yang memegang nomor
     *     lama bisa menyalahgunakan jalur lupa-password).
     *  3. Provision akun bila belum ada (pendaftaran lama), lalu kirim OTP:
     *     purpose `aktivasi` bila belum aktivasi, `reset` bila sudah —
     *     dengan bypass rate limit (jalur panitia justru dipakai saat kuota
     *     peserta habis).
     *  4. Catat audit log (Blueprint §9 — aksi sensitif).
     *
     * @param  Authenticatable|null  $actor  staf ERP pelaku aksi (untuk audit)
     * @return array{purpose: string, phone: string} untuk pesan konfirmasi panitia
     *
     * @throws ValidationException bila nomor tidak valid
     */
    public function reissueOtpByStaff(CalonSantri $calon, string $rawPhone, ?Authenticatable $actor = null): array
    {
        $phone = PpdbPhone::normalize($rawPhone);

        if ($phone === '' || strlen($phone) < 9) {
            throw ValidationException::withMessages([
                'no_hp' => 'Nomor WhatsApp tidak valid. Tulis seperti 08xxxxxxxxxx.',
            ]);
        }

        $phoneLama = PpdbPhone::normalize((string) $calon->no_hp_wali);

        if ($phone !== $phoneLama) {
            $calon->no_hp_wali = $phone;
            $calon->save();
        }

        $user = $this->provisionForRegistration($calon);

        // Sinkron identifier login No. HP — jangan tinggalkan nomor salah.
        if ($user->phone !== $phone) {
            $dipakaiAkunLain = User::query()
                ->where('phone', $phone)
                ->whereKeyNot($user->id)
                ->exists();

            $user->phone = $dipakaiAkunLain ? null : $phone;
            $user->save();
        }

        $purpose = $user->must_reset_password
            ? UserOtp::PURPOSE_AKTIVASI
            : UserOtp::PURPOSE_RESET;

        $this->otpService->issue(
            $user,
            $purpose,
            $phone,
            ['nama' => (string) $calon->nama],
            byStaff: true,
        );

        app(AuditLogger::class)->log('ppdb.peserta_otp_reissued', $actor, $calon, [
            'purpose' => $purpose,
            'phone_changed' => $phone !== $phoneLama,
            'phone_lama' => $phoneLama,
            'phone_baru' => $phone,
        ]);

        return ['purpose' => $purpose, 'phone' => $phone];
    }

    private function claimablePhone(string $phone): ?string
    {
        if ($phone === '') {
            return null;
        }

        return User::query()->where('phone', $phone)->exists() ? null : $phone;
    }

    private function claimableEmail(?string $email): ?string
    {
        $email = trim((string) $email);

        if ($email === '') {
            return null;
        }

        return User::query()->where('email', $email)->exists() ? null : $email;
    }
}
