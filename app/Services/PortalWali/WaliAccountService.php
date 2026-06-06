<?php

declare(strict_types=1);

namespace App\Services\PortalWali;

use App\Models\AccessWali;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Modules\Kesantrian\Models\Santri;
use Modules\PPDB\Models\UserOtp;
use Modules\PPDB\Services\PesertaOtpService;
use Modules\PPDB\Support\PpdbPhone;

class WaliAccountService
{
    public function __construct(
        private readonly PesertaOtpService $otpService,
        private readonly PortalWaliIdentifierResolver $resolver,
    ) {
    }

    /**
     * @return array{user: User, phone: string, children_count: int}
     */
    public function requestActivationOtp(string $identifier): array
    {
        [$user, $santriList, $phone] = $this->provisionByIdentifier($identifier);

        if (! $user->must_reset_password) {
            throw ValidationException::withMessages([
                'identifier' => 'Akun wali sudah aktif. Silakan langsung masuk atau gunakan lupa password.',
            ]);
        }

        $this->otpService->issue(
            $user,
            UserOtp::PURPOSE_AKTIVASI,
            $phone,
            ['nama' => $user->name],
        );

        return [
            'user' => $user,
            'phone' => $phone,
            'children_count' => $santriList->count(),
        ];
    }

    /**
     * @return array{user: User, phone: string}
     */
    public function requestResetOtp(string $identifier): array
    {
        $user = $this->resolver->resolve($identifier);

        if ($user === null) {
            throw ValidationException::withMessages([
                'identifier' => 'Akun wali belum ditemukan. Pastikan nomor WhatsApp atau email sudah terdaftar di data santri.',
            ]);
        }

        if ($user->must_reset_password) {
            throw ValidationException::withMessages([
                'identifier' => 'Akun wali ini belum diaktifkan. Silakan aktivasi dulu dengan kode WhatsApp.',
            ]);
        }

        $phone = $this->resolveTargetPhone($user);

        if ($phone === '') {
            throw ValidationException::withMessages([
                'identifier' => 'Nomor WhatsApp wali belum tersedia. Hubungi admin pesantren untuk perbarui data wali.',
            ]);
        }

        $this->otpService->issue(
            $user,
            UserOtp::PURPOSE_RESET,
            $phone,
            ['nama' => $user->name],
        );

        return [
            'user' => $user,
            'phone' => $phone,
        ];
    }

    /**
     * @return array{0: User, 1: Collection<int, Santri>, 2: string}
     */
    public function provisionByIdentifier(string $identifier): array
    {
        $identifier = trim($identifier);

        if ($identifier === '') {
            throw ValidationException::withMessages([
                'identifier' => 'Masukkan nomor WhatsApp atau email wali terlebih dahulu.',
            ]);
        }

        $existing = $this->resolver->resolve($identifier);
        $santriList = $this->resolveActiveSantri($identifier);

        if ($santriList->isEmpty() && $existing === null) {
            throw ValidationException::withMessages([
                'identifier' => 'Data wali belum ditemukan di santri aktif. Cek kembali nomor WhatsApp atau email yang dipakai.',
            ]);
        }

        $phone = $this->resolveIdentifierPhone($identifier, $santriList, $existing);

        if ($phone === '') {
            throw ValidationException::withMessages([
                'identifier' => 'Nomor WhatsApp wali belum tersedia. Hubungi admin pesantren untuk melengkapi data santri.',
            ]);
        }

        $user = $existing ?? $this->resolveReusableUser($santriList, $phone, $identifier);

        if ($user === null) {
            $user = $this->createWaliUser($santriList, $phone, $identifier);
        } else {
            $this->syncWaliIdentity($user, $santriList, $phone, $identifier);
        }

        if (! $user->hasRole('wali')) {
            $user->assignRole('wali');
        }

        $this->attachSantriAccess($user, $santriList);

        return [$user->fresh(), $santriList, $phone];
    }

    public function markActivated(User $user, string $plainPassword): void
    {
        $user->password = $plainPassword;
        $user->must_reset_password = false;
        $user->save();
    }

    private function resolveActiveSantri(string $identifier): Collection
    {
        $query = Santri::query()->where('status', 'aktif');

        if (filter_var($identifier, FILTER_VALIDATE_EMAIL)) {
            return $query
                ->where('email_wali', mb_strtolower($identifier))
                ->orderBy('id')
                ->get();
        }

        $phone = PpdbPhone::normalize($identifier);

        if ($phone === '') {
            return collect();
        }

        return $query
            ->whereIn('no_hp_wali', $this->phoneCandidates($identifier))
            ->orderBy('id')
            ->get();
    }

    private function resolveIdentifierPhone(string $identifier, Collection $santriList, ?User $user): string
    {
        $phone = PpdbPhone::normalize($identifier);

        if ($phone !== '') {
            return $phone;
        }

        if ($user !== null && ! empty($user->phone)) {
            return PpdbPhone::normalize((string) $user->phone);
        }

        return PpdbPhone::normalize((string) $santriList->firstWhere('no_hp_wali')?->no_hp_wali);
    }

    private function resolveReusableUser(Collection $santriList, string $phone, string $identifier): ?User
    {
        $linkedUserId = $santriList
            ->pluck('wali_user_id')
            ->filter()
            ->first();

        if ($linkedUserId !== null) {
            return User::query()->find($linkedUserId);
        }

        $accessUserId = AccessWali::query()
            ->whereIn('santri_id', $santriList->pluck('id'))
            ->value('wali_id');

        if ($accessUserId !== null) {
            return User::query()->find($accessUserId);
        }

        $existingByPhone = User::query()->where('phone', $phone)->first();
        if ($existingByPhone !== null) {
            return $existingByPhone;
        }

        if (filter_var($identifier, FILTER_VALIDATE_EMAIL)) {
            return User::query()->where('email', mb_strtolower($identifier))->first();
        }

        $santriEmail = $santriList
            ->pluck('email_wali')
            ->filter(fn (?string $email) => filled($email))
            ->map(fn (string $email) => mb_strtolower($email))
            ->first();

        return $santriEmail
            ? User::query()->where('email', $santriEmail)->first()
            : null;
    }

    private function createWaliUser(Collection $santriList, string $phone, string $identifier): User
    {
        $user = new User();
        $user->name = $this->resolveWaliName($santriList);
        $user->phone = $this->claimablePhone($phone);
        $user->email = $this->claimableEmail($this->resolveEmail($identifier, $santriList));
        $user->password = Str::password(40);
        $user->must_reset_password = true;
        $user->save();

        return $user;
    }

    private function syncWaliIdentity(User $user, Collection $santriList, string $phone, string $identifier): void
    {
        $dirty = false;

        if (blank($user->phone) && $this->claimablePhone($phone) === $phone) {
            $user->phone = $phone;
            $dirty = true;
        }

        $email = $this->resolveEmail($identifier, $santriList);
        if (blank($user->email) && $email !== null && $this->claimableEmail($email) === $email) {
            $user->email = $email;
            $dirty = true;
        }

        if (blank($user->name)) {
            $user->name = $this->resolveWaliName($santriList);
            $dirty = true;
        }

        if ($dirty) {
            $user->save();
        }
    }

    private function attachSantriAccess(User $user, Collection $santriList): void
    {
        foreach ($santriList as $santri) {
            if ($santri->wali_user_id === null) {
                $santri->wali_user_id = $user->id;
                $santri->save();
            }

            AccessWali::query()->firstOrCreate(
                [
                    'tenant_id' => tenant()?->getTenantKey(),
                    'wali_id' => $user->id,
                    'santri_id' => $santri->id,
                ],
                [
                    'relationship_type' => 'wali',
                    'is_primary' => $santri->wali_user_id === $user->id,
                ],
            );
        }
    }

    private function resolveTargetPhone(User $user): string
    {
        if (! empty($user->phone)) {
            return PpdbPhone::normalize((string) $user->phone);
        }

        $santri = Santri::query()
            ->where('status', 'aktif')
            ->where(function ($query) use ($user): void {
                $query->where('wali_user_id', $user->id)
                    ->orWhereHas('accessWali', fn ($access) => $access->where('wali_id', $user->id));
            })
            ->orderBy('id')
            ->first();

        return PpdbPhone::normalize((string) ($santri?->no_hp_wali ?? ''));
    }

    private function resolveWaliName(Collection $santriList): string
    {
        $first = $santriList->first();
        $name = trim((string) ($first?->nama_ayah ?: $first?->nama_ibu ?: ''));

        return $name !== '' ? $name : 'Wali '.$first?->nama;
    }

    private function resolveEmail(string $identifier, Collection $santriList): ?string
    {
        if (filter_var($identifier, FILTER_VALIDATE_EMAIL)) {
            return mb_strtolower($identifier);
        }

        $email = $santriList
            ->pluck('email_wali')
            ->filter(fn (?string $value) => filled($value))
            ->map(fn (string $value) => mb_strtolower($value))
            ->first();

        return $email ?: null;
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
