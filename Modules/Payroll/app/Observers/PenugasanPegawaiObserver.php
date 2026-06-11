<?php

namespace Modules\Payroll\Observers;

use Modules\Payroll\Models\Employee;
use Modules\Payroll\Models\Jabatan;
use Modules\Payroll\Models\PenugasanPegawai;
use Spatie\Permission\Models\Role;

/**
 * WP-03 — Sinkron role Spatie dari penugasan (Blueprint SDM S-0 poin 4).
 *
 * - Penugasan AKTIF dibuat/diaktifkan kembali + jabatan punya role_spatie
 *   → assignRole ke user yang tertaut Employee.
 * - Penugasan DITUTUP (tanggal_selesai diisi) / dihapus / pindah jabatan /
 *   pindah PEGAWAI → removeRole HANYA bila tidak ada penugasan aktif lain
 *   ber-role sama (rangkap jabatan dengan role sama tidak saling mencabut).
 * - is_primary: set jabatan utama baru AUTO-DEMOTE primary aktif lain
 *   (saving) — partial unique index tetap jadi backstop race di DB.
 * - Employee tanpa user tertaut: dilewati dengan catatan log — TIDAK error.
 * - Role yang tidak terdaftar di DB tenant: dilewati dengan catatan log —
 *   observer TIDAK menciptakan role baru (mapping hanya ke role eksisting).
 *
 * Catatan: mass update/delete via query builder TIDAK memicu observer —
 * jalur tulis penugasan harus lewat model Eloquent (Filament memenuhinya).
 */
class PenugasanPegawaiObserver
{
    /**
     * Hanya SATU jabatan utama aktif per pegawai: set primary baru
     * menurunkan primary aktif lain (last-write-wins, ramah UI) — partial
     * unique index DB tetap menjaga race dua request bersamaan.
     */
    public function saving(PenugasanPegawai $penugasan): void
    {
        if ($penugasan->is_primary && $penugasan->tanggal_selesai === null) {
            PenugasanPegawai::query()
                ->where('employee_id', $penugasan->employee_id)
                ->when($penugasan->exists, fn ($q) => $q->whereKeyNot($penugasan->getKey()))
                ->whereNull('tanggal_selesai')
                ->where('is_primary', true)
                ->update(['is_primary' => false]); // hanya kolom tampilan — tanpa efek role
        }
    }

    public function created(PenugasanPegawai $penugasan): void
    {
        if ($penugasan->tanggal_selesai === null) {
            $this->grant($penugasan);
        }
    }

    public function updated(PenugasanPegawai $penugasan): void
    {
        $jabatanLama = (int) $penugasan->getOriginal('jabatan_id');
        $employeeLama = (int) $penugasan->getOriginal('employee_id');
        $aktifSebelum = $penugasan->getOriginal('tanggal_selesai') === null;
        $aktifSesudah = $penugasan->tanggal_selesai === null;
        $pindah = $jabatanLama !== (int) $penugasan->jabatan_id
            || $employeeLama !== (int) $penugasan->employee_id; // pindah jabatan ATAU pindah pegawai

        // Lepas role lama dari PEGAWAI LAMA saat ditutup ATAU pindah (dari kondisi aktif).
        if ($aktifSebelum && (! $aktifSesudah || $pindah)) {
            $this->release($penugasan, $jabatanLama, $employeeLama);
        }

        // Beri role baru saat aktif (baru diaktifkan kembali ATAU pindah).
        if ($aktifSesudah && (! $aktifSebelum || $pindah)) {
            $this->grant($penugasan);
        }
    }

    public function deleted(PenugasanPegawai $penugasan): void
    {
        if ($penugasan->tanggal_selesai === null) {
            $this->release($penugasan, (int) $penugasan->jabatan_id, (int) $penugasan->employee_id);
        }
    }

    private function grant(PenugasanPegawai $penugasan): void
    {
        // Ambil via query (bukan relasi) — relasi bisa masih men-cache jabatan/
        // employee LAMA saat pindah (foreign key berubah pada update).
        $role = Jabatan::query()->find($penugasan->jabatan_id)?->role_spatie;
        if ($role === null || $role === '') {
            return;
        }

        $user = Employee::query()->find($penugasan->employee_id)?->user;
        if ($user === null) {
            logger()->info('WP-03: penugasan tanpa user tertaut — sinkron role dilewati.', [
                'penugasan_id' => $penugasan->id, 'employee_id' => $penugasan->employee_id,
            ]);

            return;
        }

        if (! Role::query()->where('name', $role)->exists()) {
            logger()->warning('WP-03: role_spatie tidak terdaftar — sinkron role dilewati (observer tidak membuat role baru).', [
                'penugasan_id' => $penugasan->id, 'role' => $role,
            ]);

            return;
        }

        $user->assignRole($role);
    }

    private function release(PenugasanPegawai $penugasan, int $jabatanId, int $employeeId): void
    {
        $role = Jabatan::query()->find($jabatanId)?->role_spatie;
        if ($role === null || $role === '') {
            return;
        }

        $user = Employee::query()->find($employeeId)?->user;
        if ($user === null) {
            return;
        }

        // Masih ada penugasan AKTIF lain ber-role sama untuk pegawai itu? → role bertahan.
        $masihAda = PenugasanPegawai::query()
            ->where('employee_id', $employeeId)
            ->whereKeyNot($penugasan->getKey())
            ->whereNull('tanggal_selesai')
            ->whereHas('jabatan', fn ($q) => $q->where('role_spatie', $role))
            ->exists();

        if (! $masihAda && $user->hasRole($role)) {
            $user->removeRole($role);
        }
    }
}
