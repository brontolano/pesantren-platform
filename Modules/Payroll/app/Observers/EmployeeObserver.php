<?php

namespace Modules\Payroll\Observers;

use App\Models\User;
use Modules\Payroll\Models\Employee;
use Spatie\Permission\Models\Role;

/**
 * WP-03 — Pindah akun pegawai (users_id diganti): role hasil penugasan aktif
 * ikut PINDAH — dicabut dari user lama, diberikan ke user baru. Tanpa ini,
 * akses panel nempel di akun lama (lubang akses) dan akun baru kosong.
 *
 * WP-04 — Sinkron satu arah tanggal_selesai → aktif: mengisi tanggal_selesai
 * otomatis menonaktifkan pegawai (aktif=false). Kolom `aktif` TETAP sumber
 * kebenaran untuk semua query; tanggal_selesai hanya informasi kapan.
 * Menghapus tanggal_selesai TIDAK auto-mengaktifkan (admin sengaja).
 *
 * WP-04 (review adversarial) — Pegawai DINONAKTIFKAN (aktif true→false) →
 * SEMUA penugasan aktifnya DITUTUP (tanggal_selesai diisi) lewat model save,
 * sehingga PenugasanPegawaiObserver mencabut role Spatie-nya. Tanpa ini
 * pegawai purna masih pegang role + bisa login panel (canAccessPanel hanya
 * cek tenant_id & bukan peserta/wali) — lubang akses nyata. Mengaktifkan
 * ulang TIDAK membuka penugasan lama (buat penugasan baru — riwayat jujur).
 */
class EmployeeObserver
{
    public function saving(Employee $employee): void
    {
        if ($employee->isDirty('tanggal_selesai') && $employee->tanggal_selesai !== null) {
            $employee->aktif = false;
        }
    }

    public function updated(Employee $employee): void
    {
        // Urutan penting: pindah role dulu (users_id), baru tutup penugasan —
        // bila keduanya berubah sekaligus, release() membaca user TERBARU.
        $this->pindahkanRoleSaatGantiUser($employee);
        $this->tutupPenugasanSaatNonaktif($employee);
    }

    /** WP-04: tutup semua penugasan aktif saat pegawai dinonaktifkan (role dicabut via PenugasanPegawaiObserver). */
    private function tutupPenugasanSaatNonaktif(Employee $employee): void
    {
        if (! $employee->wasChanged('aktif') || $employee->aktif) {
            return;
        }

        $tanggal = $employee->tanggal_selesai?->toDateString() ?? now()->toDateString();

        // Lewat model (BUKAN mass update query builder) agar observer penugasan
        // terpicu dan role Spatie dicabut dari user tertaut.
        $employee->penugasan()->whereNull('tanggal_selesai')->get()
            ->each(function ($p) use ($employee, $tanggal): void {
                $p->update(['tanggal_selesai' => $tanggal]);
                logger()->info('WP-04: penugasan ditutup otomatis — pegawai dinonaktifkan.', [
                    'employee_id' => $employee->id, 'penugasan_id' => $p->id,
                ]);
            });
    }

    /** WP-03: pindah akun (users_id diganti) → role penugasan aktif ikut pindah. */
    private function pindahkanRoleSaatGantiUser(Employee $employee): void
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
