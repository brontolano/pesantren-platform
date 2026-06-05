<?php

namespace Modules\PPDB\Services;

use App\Models\User;
use Modules\PPDB\Database\Seeders\PesertaRoleSeeder;
use Modules\PPDB\Models\CalonSantri;
use Modules\PPDB\Support\PpdbPhone;

/**
 * Resolusi identifier login peserta SPMB → akun `users` (AUTH-3, F1.1.3).
 *
 * Keputusan F0.1: username utama = NOMOR PENDAFTARAN; alternatif No. HP / email.
 * Patuh Hukum #2 Blueprint: hanya MENCARI baris `users` existing di DB tenant
 * aktif — tidak ada tabel kredensial baru.
 *
 * Urutan pencarian (penting — jangan diubah tanpa alasan):
 * 1. `calon_santri.nomor_pendaftaran` (exact, case-insensitive) → relasi user.
 *    Dicoba PERTAMA karena nomor pendaftaran bisa berupa digit semua dan
 *    akan salah-normalisasi bila diperlakukan sebagai No. HP.
 * 2. Mengandung `@` → `users.email`.
 * 3. Sisanya → normalisasi No. HP (`PpdbPhone`) → `users.phone`.
 *
 * Hanya akun ber-role `peserta` yang dikembalikan — akun staf/wali tidak
 * bisa dipakai masuk portal peserta (dan sebaliknya, F1.1.6).
 */
class PesertaIdentifierResolver
{
    public function resolve(string $identifier): ?User
    {
        $identifier = trim($identifier);

        if ($identifier === '') {
            return null;
        }

        $user = $this->byNomorPendaftaran($identifier)
            ?? $this->byEmail($identifier)
            ?? $this->byPhone($identifier);

        if ($user === null || ! $user->hasRole(PesertaRoleSeeder::ROLE)) {
            return null;
        }

        return $user;
    }

    private function byNomorPendaftaran(string $identifier): ?User
    {
        return CalonSantri::query()
            ->whereRaw('UPPER(nomor_pendaftaran) = ?', [mb_strtoupper($identifier)])
            ->first()
            ?->user;
    }

    private function byEmail(string $identifier): ?User
    {
        if (! str_contains($identifier, '@')) {
            return null;
        }

        // Case-insensitive — email tersimpan apa adanya dari formulir publik.
        return User::query()
            ->whereRaw('LOWER(email) = ?', [mb_strtolower($identifier)])
            ->first();
    }

    private function byPhone(string $identifier): ?User
    {
        $phone = PpdbPhone::normalize($identifier);

        if ($phone === '' || strlen($phone) < 9) {
            return null;
        }

        return User::query()->where('phone', $phone)->first();
    }
}
