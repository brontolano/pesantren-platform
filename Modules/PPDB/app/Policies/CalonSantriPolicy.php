<?php

namespace Modules\PPDB\Policies;

use App\Models\User;
use Modules\PPDB\Database\Seeders\PesertaRoleSeeder;
use Modules\PPDB\Models\CalonSantri;

/**
 * F1.1.6 — Policy akses data pendaftaran (`calon_santri`).
 *
 * Dua kelas pengguna pada DB tenant yang sama:
 *  - STAF ERP (panitia/TU/admin): permission `view_santri` / `manage_santri`
 *    — selaras gate existing di CalonSantriResource (Filament tetap jalan).
 *  - PESERTA (role `peserta`): HANYA record miliknya sendiri
 *    (`calon_santri.user_id = users.id`) dengan permission self-service
 *    (`view_own_ppdb_status`, `complete_own_ppdb`) — PRD §11 Keamanan.
 *
 * Peserta TIDAK pernah dapat viewAny/create/delete — tidak ada daftar
 * pendaftar lain yang bisa diintip (PRD: "tanpa data peserta lain").
 * Isolasi antar-tenant tidak diurus di sini — sudah FISIK via DB-per-tenant.
 */
class CalonSantriPolicy
{
    public function viewAny(User $user): bool
    {
        return $this->isStaf($user)
            && $user->canAny(['view_santri', 'manage_santri']);
    }

    public function view(User $user, CalonSantri $calonSantri): bool
    {
        if ($this->isPeserta($user)) {
            return $this->miliknya($user, $calonSantri)
                && $user->can('view_own_ppdb_status');
        }

        return $user->canAny(['view_santri', 'manage_santri']);
    }

    public function create(User $user): bool
    {
        return $this->isStaf($user) && $user->can('manage_santri');
    }

    public function update(User $user, CalonSantri $calonSantri): bool
    {
        if ($this->isPeserta($user)) {
            // Lengkapi/koreksi biodata sendiri (DASH-2, F1.2.5).
            return $this->miliknya($user, $calonSantri)
                && $user->can('complete_own_ppdb');
        }

        return $user->can('manage_santri');
    }

    public function delete(User $user, CalonSantri $calonSantri): bool
    {
        return $this->isStaf($user) && $user->can('manage_santri');
    }

    public function restore(User $user, CalonSantri $calonSantri): bool
    {
        return $this->isStaf($user) && $user->can('manage_santri');
    }

    public function forceDelete(User $user, CalonSantri $calonSantri): bool
    {
        // Hapus permanen data anak di bawah umur = aksi paling sensitif.
        return $user->hasRole('admin_pesantren') || $user->hasRole('super_admin');
    }

    private function isPeserta(User $user): bool
    {
        return $user->hasRole(PesertaRoleSeeder::ROLE);
    }

    private function isStaf(User $user): bool
    {
        return ! $this->isPeserta($user);
    }

    private function miliknya(User $user, CalonSantri $calonSantri): bool
    {
        return $calonSantri->user_id !== null
            && (int) $calonSantri->user_id === (int) $user->id;
    }
}
