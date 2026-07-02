<?php

namespace Modules\Kesantrian\Services;

use App\Models\User;
use App\Services\PortalWali\WaliAccountService;
use Illuminate\Support\Str;
use Modules\Kesantrian\Models\Santri;
use RuntimeException;
use Throwable;

/**
 * App Santri — konversi santri AKTIF (hasil daftar ulang/LULUS) menjadi akun
 * login santri + tautan akun wali.
 *
 *  - provision(): buat User ber-role 'santri' (idempoten by santri.user_id;
 *    email sintetis unik dari NIS, password acak — kredensial dikirim staf
 *    via aksi "Kirim Kredensial"/reset, BUKAN dipulangkan ke UI).
 *  - tautkanWali(): pakai WaliAccountService::provisionByIdentifier(no_hp_wali)
 *    — akun wali dibuat/ditemukan + akses santri ter-attach; isi wali_user_id.
 */
class SantriAccountService
{
    public function __construct(private readonly WaliAccountService $waliAccounts) {}

    /** Buat/ambil akun login untuk santri. Idempoten. */
    public function provision(Santri $santri): User
    {
        $this->ensureTenantContext();

        if ($santri->user_id) {
            return User::withoutGlobalScopes()->findOrFail($santri->user_id);
        }

        $nis = trim((string) $santri->nis);
        if ($nis === '') {
            throw new RuntimeException('Santri belum punya NIS — akun tidak bisa dibuat.');
        }

        $email = Str::lower($nis).'@'.tenant('id').'.santri.localhost';

        $user = User::withoutGlobalScopes()->firstOrCreate(
            ['email' => $email, 'tenant_id' => tenant('id')],
            [
                'name' => (string) $santri->nama,
                'password' => Str::password(24), // di-hash cast; reset via staf/OTP
            ],
        );

        if (! $user->hasRole('santri')) {
            $user->assignRole('santri');
        }

        $santri->update(['user_id' => $user->id]);

        return $user;
    }

    /**
     * Setel ulang PIN login App Santri (6 digit numerik, mudah dihafal anak).
     * Kembalikan [User, pin-plaintext] — PIN dipakai SEKALI untuk dikirim ke
     * wali; JANGAN di-log/simpan plaintext.
     *
     * @return array{0: User, 1: string}
     */
    public function resetPin(Santri $santri): array
    {
        return [$this->setPin($santri, str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT)), $this->lastPin];
    }

    private string $lastPin = '';

    /**
     * Setel PIN login santri ke nilai TERTENTU (mis. dipilih wali di Portal Wali).
     * Wajib 6 digit numerik. Akun dibuat bila belum ada (idempoten).
     */
    public function setPin(Santri $santri, string $pin): User
    {
        if (! preg_match('/^\d{6}$/', $pin)) {
            throw new \InvalidArgumentException('PIN harus 6 digit angka.');
        }

        $user = $this->provision($santri);
        $user->password = $pin; // cast 'hashed' meng-hash saat di-set
        $user->save();
        $this->lastPin = $pin;

        return $user;
    }

    /**
     * Tautkan akun WALI (buat bila belum ada) berdasarkan no_hp_wali santri.
     * Best-effort: kegagalan (mis. nomor kosong/tak valid) TIDAK melempar.
     */
    public function tautkanWali(Santri $santri): ?User
    {
        $this->ensureTenantContext();

        $phone = trim((string) ($santri->no_hp_wali ?? ''));
        if ($phone === '') {
            return null;
        }

        try {
            [$wali] = $this->waliAccounts->provisionByIdentifier($phone);

            if ($santri->wali_user_id === null) {
                $santri->update(['wali_user_id' => $wali->id]);
            }

            return $wali;
        } catch (Throwable $e) {
            report($e);

            return null;
        }
    }

    private function ensureTenantContext(): void
    {
        if (! tenancy()->initialized || tenancy()->tenant === null) {
            throw new RuntimeException('Tenant context belum aktif.');
        }
    }
}
