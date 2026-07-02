<?php

namespace Modules\Notifikasi\Services;

use App\Models\User;
use Illuminate\Support\Collection;
use Modules\Kesantrian\Models\Santri;

class RecipientResolver
{
    public function resolve(string $targetGroup, array $filters = []): Collection
    {
        return match ($targetGroup) {
            'santri' => $this->resolveSantri($filters),
            'wali' => $this->resolveWali($filters),
            'staff', 'user' => $this->resolveUsers($filters),
            default => $this->resolveCustomTargets($filters),
        };
    }

    private function resolveCustomTargets(array $filters): Collection
    {
        $targets = $filters['targets'] ?? [];
        $variables = is_array($filters['variables'] ?? null) ? $filters['variables'] : [];

        if (is_string($targets)) {
            $targets = collect(explode(',', $targets))
                ->map(fn (string $target): string => trim($target))
                ->filter()
                ->values()
                ->all();
        }

        return collect($targets)
            ->filter(fn ($target): bool => filled($target))
            ->map(fn ($target) => [
                'target' => $target,
                'variables' => array_merge($variables, ['target' => $target]),
            ]);
    }

    private function resolveSantri(array $filters): Collection
    {
        return Santri::query()
            ->when($filters['kelas_id'] ?? null, fn ($query, $kelasId) => $query->where('kelas_id', $kelasId))
            ->when($filters['status'] ?? null, fn ($query, $status) => $query->where('status', $status))
            ->whereNotNull('no_hp_wali')
            ->get()
            ->map(fn (Santri $santri) => [
                'target' => $santri->no_hp_wali,
                'variables' => [
                    'nama_santri' => $santri->nama,
                    'nis' => $santri->nis,
                    'kelas_id' => $santri->kelas_id,
                ],
            ]);
    }

    private function resolveWali(array $filters): Collection
    {
        return Santri::query()
            ->when($filters['kelas_id'] ?? null, fn ($query, $kelasId) => $query->where('kelas_id', $kelasId))
            ->whereNotNull('no_hp_wali')
            ->get()
            ->map(fn (Santri $santri) => [
                'target' => $santri->no_hp_wali,
                'variables' => [
                    'nama_santri' => $santri->nama,
                    'nama_wali' => $santri->nama_ayah ?: $santri->nama_ibu ?: 'Wali Santri',
                    'nis' => $santri->nis,
                ],
            ])
            ->unique('target')
            ->values();
    }

    private function resolveUsers(array $filters): Collection
    {
        return User::query()
            ->when($filters['role'] ?? null, fn ($query, $role) => $query->role($role))
            ->get()
            ->map(fn (User $user) => [
                'target' => $filters['channel'] === 'email' ? $user->email : ($user->phone_number ?? $user->email),
                'variables' => [
                    'nama_user' => $user->name,
                    'email_user' => $user->email,
                ],
            ])
            ->filter(fn (array $recipient): bool => filled($recipient['target']))
            ->values();
    }
}
