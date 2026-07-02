<?php

namespace Modules\PPDB\Services;

use App\Models\User;
use Illuminate\Support\Str;
use Modules\PPDB\Models\CalonSantri;
use Modules\PPDB\Support\PpdbPhone;

/**
 * Penerbitan & reset kredensial akun PORTAL PESERTA.
 *
 * Opsi A (Hukum #2 Blueprint): akun peserta = baris `users` + role `peserta`,
 * ditautkan ke pendaftaran lewat calon_santri.user_id (1 akun = 1 pendaftaran, F0.3).
 * Password sementara dikembalikan PLAINTEXT sekali pakai (untuk dikirim via WA /
 * dibacakan staf). Di DB hanya tersimpan HASH (cast 'hashed' pada model User).
 */
class PpdbApplicantAccountService
{
    /**
     * Buat akun peserta bila belum ada, atau reset password bila sudah ada.
     * Selalu set `must_reset_password = true` (paksa ganti saat login pertama).
     *
     * @return array{username: string, password: string, login_url: string}
     */
    public function issueOrResetCredentials(CalonSantri $calon, string $rawPhone): array
    {
        $phone = PpdbPhone::normalize($rawPhone);
        if ($phone === '') {
            throw new \InvalidArgumentException('Nomor WhatsApp tidak valid.');
        }

        // Password sementara: alfanumerik tanpa simbol (mudah dibacakan via WA).
        $plain = Str::password(10, letters: true, numbers: true, symbols: false);

        $user = $calon->user;

        if ($user === null) {
            $user = new User;
            $user->name = $calon->nama;
            $user->email = null;                 // peserta login via No. HP, email tak wajib
            $user->phone = $phone;
            $user->password = $plain;            // cast 'hashed' meng-hash saat di-set
            $user->must_reset_password = true;
            $user->save();                       // BelongsToTenant mengisi tenant_id otomatis
            $user->assignRole('peserta');

            $calon->user_id = $user->id;
            $calon->save();
        } else {
            $user->phone = $phone;               // koreksi nomor bila peserta salah input
            $user->password = $plain;
            $user->must_reset_password = true;
            $user->save();

            if (! $user->hasRole('peserta')) {
                $user->assignRole('peserta');
            }
        }

        return [
            'username' => $phone,
            'password' => $plain,
            'login_url' => url('/spmb/login'),
        ];
    }
}
