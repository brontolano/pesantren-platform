<?php

declare(strict_types=1);

namespace App\Services\Jemaah;

use App\Models\User;
use Modules\PPDB\Support\PpdbPhone;

/**
 * Resolusi identifier (No. WhatsApp / email) → akun User ber-role "jemaah".
 *
 * Versi ramping dari PortalWaliIdentifierResolver: jemaah TIDAK terkait santri,
 * jadi tanpa fallback wali_user_id / access_wali — cukup lookup langsung di
 * tabel users (sudah auto-scoped per tenant via BelongsToTenant). Hanya akun
 * ber-role jemaah yang dikembalikan; selain itu null (anti-enumerasi di login).
 */
class JemaahIdentifierResolver
{
    public function resolve(string $identifier): ?User
    {
        $identifier = trim($identifier);

        if ($identifier === '') {
            return null;
        }

        if (filter_var($identifier, FILTER_VALIDATE_EMAIL)) {
            $user = User::query()->where('email', mb_strtolower($identifier))->first();

            return $this->onlyPureJemaah($user);
        }

        $phone = PpdbPhone::normalize($identifier);

        if ($phone === '') {
            return null;
        }

        $user = User::query()
            ->whereIn('phone', $this->phoneCandidates($identifier, $phone))
            ->first();

        return $this->onlyPureJemaah($user);
    }

    /**
     * Kembalikan user HANYA bila peran jemaah eksklusif (tak merangkap staf/wali/
     * peserta). Pertahanan berlapis: permukaan auth publik jemaah (reset password
     * tanpa kredensial lama) TIDAK boleh berlaku pada akun yang memegang peran
     * berhak-lebih — meski saat ini tak ada jalur kode yang membuat akun rangkap.
     */
    private function onlyPureJemaah(?User $user): ?User
    {
        if ($user === null || ! $user->hasRole('jemaah')) {
            return null;
        }

        $rolesLain = $user->getRoleNames()->reject(fn (string $name): bool => $name === 'jemaah');

        return $rolesLain->isEmpty() ? $user : null;
    }

    /**
     * Kandidat nomor untuk lookup (62…, 0…, mentah) — cermin pola wali agar
     * nomor yang disimpan saat daftar pasti cocok saat login.
     *
     * @return array<int, string>
     */
    private function phoneCandidates(string $raw, string $normalized): array
    {
        $candidates = array_filter([
            $normalized,
            trim($raw),
            str_starts_with($normalized, '62') ? '0'.substr($normalized, 2) : null,
        ]);

        return array_values(array_unique($candidates));
    }
}
