<?php

declare(strict_types=1);

namespace App\Services\PortalWali;

use App\Models\User;
use Modules\Kesantrian\Models\Santri;
use Modules\PPDB\Support\PpdbPhone;

class PortalWaliIdentifierResolver
{
    public function resolve(string $identifier): ?User
    {
        $identifier = trim($identifier);

        if ($identifier === '') {
            return null;
        }

        if (filter_var($identifier, FILTER_VALIDATE_EMAIL)) {
            return User::query()
                ->where('email', mb_strtolower($identifier))
                ->whereHas('roles', fn ($query) => $query->where('name', 'wali'))
                ->first();
        }

        $phone = PpdbPhone::normalize($identifier);

        if ($phone === '' || strlen($phone) < 9) {
            return null;
        }

        $directUser = User::query()
            ->where('phone', $phone)
            ->whereHas('roles', fn ($query) => $query->where('name', 'wali'))
            ->first();

        if ($directUser !== null) {
            return $directUser;
        }

        $linkedUserId = Santri::query()
            ->where('status', 'aktif')
            ->whereIn('no_hp_wali', $this->phoneCandidates($identifier))
            ->whereNotNull('wali_user_id')
            ->value('wali_user_id');

        if ($linkedUserId !== null) {
            return User::query()
                ->whereKey($linkedUserId)
                ->whereHas('roles', fn ($query) => $query->where('name', 'wali'))
                ->first();
        }

        $accessUserId = Santri::query()
            ->where('status', 'aktif')
            ->whereIn('no_hp_wali', $this->phoneCandidates($identifier))
            ->join('access_wali', 'access_wali.santri_id', '=', 'santri.id')
            ->value('access_wali.wali_id');

        if ($accessUserId === null) {
            return null;
        }

        return User::query()
            ->whereKey($accessUserId)
            ->whereHas('roles', fn ($query) => $query->where('name', 'wali'))
            ->first();
    }

    /**
     * @return array<int, string>
     */
    private function phoneCandidates(string $identifier): array
    {
        $raw = trim($identifier);
        $normalized = PpdbPhone::normalize($raw);
        $candidates = array_filter([
            $raw,
            $normalized,
            str_starts_with($normalized, '62') ? '0'.substr($normalized, 2) : null,
        ]);

        return array_values(array_unique($candidates));
    }
}
