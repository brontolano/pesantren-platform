<?php

namespace Modules\Payroll\Observers;

use Modules\Payroll\Models\Jabatan;
use Modules\Payroll\Models\PenugasanPegawai;
use Spatie\Permission\Models\Role;

/**
 * WP-03 — Resync role saat admin MENGUBAH Jabatan.role_spatie padahal sudah
 * ada penugasan aktif. Tanpa ini, role lama orphan permanen di user (dan
 * saat penugasan ditutup, observer penugasan melepas role BARU — role lama
 * tak pernah tercabut).
 */
class JabatanObserver
{
    public function updated(Jabatan $jabatan): void
    {
        if (! $jabatan->wasChanged('role_spatie')) {
            return;
        }

        $roleLama = $jabatan->getOriginal('role_spatie');
        $roleBaru = $jabatan->role_spatie;
        $roleBaruTerdaftar = $roleBaru !== null && $roleBaru !== ''
            && Role::query()->where('name', $roleBaru)->exists();

        $penugasanAktif = PenugasanPegawai::query()
            ->where('jabatan_id', $jabatan->id)
            ->whereNull('tanggal_selesai')
            ->with('employee.user')
            ->get();

        foreach ($penugasanAktif as $penugasan) {
            $user = $penugasan->employee?->user;
            if ($user === null) {
                continue;
            }

            // Lepas role lama bila tak ditopang penugasan aktif jabatan LAIN.
            if ($roleLama !== null && $roleLama !== '') {
                $masihAda = PenugasanPegawai::query()
                    ->where('employee_id', $penugasan->employee_id)
                    ->where('jabatan_id', '!=', $jabatan->id)
                    ->whereNull('tanggal_selesai')
                    ->whereHas('jabatan', fn ($q) => $q->where('role_spatie', $roleLama))
                    ->exists();

                if (! $masihAda && $user->hasRole($roleLama)) {
                    $user->removeRole($roleLama);
                }
            }

            if ($roleBaruTerdaftar) {
                $user->assignRole($roleBaru);
            }
        }

        if ($roleBaru !== null && $roleBaru !== '' && ! $roleBaruTerdaftar) {
            logger()->warning('WP-03: role_spatie baru tidak terdaftar — resync grant dilewati.', [
                'jabatan_id' => $jabatan->id, 'role' => $roleBaru,
            ]);
        }
    }
}
