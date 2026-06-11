<?php

namespace Modules\Payroll\Observers;

use App\Models\User;
use Modules\Payroll\Models\Employee;
use Spatie\Permission\Models\Role;

/**
 * WP-03 — Pindah akun pegawai (users_id diganti): role hasil penugasan aktif
 * ikut PINDAH — dicabut dari user lama, diberikan ke user baru. Tanpa ini,
 * akses panel nempel di akun lama (lubang akses) dan akun baru kosong.
 */
class EmployeeObserver
{
    public function updated(Employee $employee): void
    {
        if (! $employee->wasChanged('users_id')) {
            return;
        }

        // Role turunan penugasan AKTIF pegawai ini.
        $roles = $employee->penugasan()
            ->whereNull('tanggal_selesai')
            ->with('jabatan')
            ->get()
            ->pluck('jabatan.role_spatie')
            ->filter(fn ($r) => $r !== null && $r !== '')
            ->unique();

        if ($roles->isEmpty()) {
            return;
        }

        $userLama = User::query()->find($employee->getOriginal('users_id'));
        $userBaru = $employee->users_id ? User::query()->find($employee->users_id) : null;

        foreach ($roles as $role) {
            if ($userLama !== null && $userLama->hasRole($role)) {
                $userLama->removeRole($role);
            }

            if ($userBaru !== null && Role::query()->where('name', $role)->exists()) {
                $userBaru->assignRole($role);
            }
        }
    }
}
