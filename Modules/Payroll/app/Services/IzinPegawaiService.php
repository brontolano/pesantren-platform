<?php

namespace Modules\Payroll\Services;

use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\UniqueConstraintViolationException;
use InvalidArgumentException;
use Modules\Akademik\Models\Lembaga;
use Modules\Payroll\Models\Cuti;
use Modules\Payroll\Models\Employee;
use Spatie\Permission\Exceptions\PermissionDoesNotExist;

/**
 * WP-17 — Izin Pegawai Harian (pulang_kampung/keperluan_keluarga/dinas) di
 * atas tabel cuti existing. Default is_berbayar TRUE (tak memotong — jalur
 * WP-14 hanya potong cuti tak berbayar).
 *
 * Approval: kepala lembaga dari penugasan is_primary aktif pegawai →
 * Lembaga.kepala (users). Fallback: user pertama ber-permission run_payroll →
 * user pertama tenant (admin). Status & transisi memakai CutiService (F1).
 */
class IzinPegawaiService
{
    public function __construct(private readonly CutiService $cutiService) {}

    /** Resolusi approver: penugasan primary aktif → lembaga → kepala → fallback. */
    public function approverUntuk(Employee $employee): ?User
    {
        $penugasan = $employee->penugasan()
            ->whereNull('tanggal_selesai')
            ->where('is_primary', true)
            ->whereNotNull('lembaga_id')
            ->orderByDesc('tanggal_mulai')
            ->first();

        if ($penugasan !== null) {
            $kepala = Lembaga::query()->find($penugasan->lembaga_id)?->kepala;
            // Kepala tak boleh menyetujui izinnya sendiri — turun ke fallback.
            if ($kepala !== null && (int) $kepala->id !== (int) $employee->users_id) {
                return $kepala;
            }
        }

        return $this->fallbackApprover($employee);
    }

    /**
     * Ajukan izin harian. Idempoten via client_ref (pola WP-26): retry dengan
     * client_ref sama mengembalikan baris yang sudah ada, tanpa dobel.
     *
     * @param array{jenis:string, tanggal_mulai:string, tanggal_selesai:string,
     *     alasan?:?string, client_ref?:?string} $data
     */
    public function ajukan(Employee $employee, array $data, ?int $userId = null): Cuti
    {
        if (! array_key_exists($data['jenis'], Cuti::JENIS)) {
            throw new InvalidArgumentException('Jenis izin tidak dikenal: '.$data['jenis']);
        }

        $clientRef = $data['client_ref'] ?? null;
        if ($clientRef !== null && $clientRef !== '') {
            $existing = Cuti::query()->where('client_ref', $clientRef)->first();
            if ($existing !== null) {
                // Idempoten HANYA untuk record milik pegawai yang sama —
                // ref pegawai lain = hijack/bug klien, tolak (bukan bocorkan).
                if ($existing->employee_id !== $employee->id) {
                    throw new InvalidArgumentException('client_ref sudah dipakai pengajuan lain.');
                }

                return $existing;
            }
        }

        // Izin harian DEFAULT berbayar (tak memotong gaji — WP-14 aman).
        $isBerbayar = in_array($data['jenis'], Cuti::JENIS_IZIN_HARIAN, true) ? true : null;

        try {
            $cuti = $this->cutiService->ajukan(
                $employee->id,
                $data['jenis'],
                $data['tanggal_mulai'],
                $data['tanggal_selesai'],
                $data['alasan'] ?? null,
                $userId,
                $isBerbayar,
                $clientRef !== '' ? $clientRef : null,
            );
        } catch (UniqueConstraintViolationException) {
            // Double-submit paralel: baris pertama menang — ambil miliknya.
            $cuti = Cuti::query()
                ->where('client_ref', $clientRef)
                ->where('employee_id', $employee->id)
                ->firstOrFail();
        }

        return $cuti->fresh();
    }

    /** Setujui — HANYA approver hasil resolusi (atau pemegang run_payroll). */
    public function setujui(Cuti $cuti, User $user): Cuti
    {
        $this->pastikanApprover($cuti, $user);

        return $this->cutiService->setujui($cuti, $user->id);
    }

    /** Tolak — gate sama dengan setujui. */
    public function tolak(Cuti $cuti, User $user, ?string $alasan = null): Cuti
    {
        $this->pastikanApprover($cuti, $user);

        return $this->cutiService->tolak($cuti, $user->id, $alasan);
    }

    /** Antrean izin menunggu yang harus disetujui user ini. */
    public function antreanUntuk(User $user)
    {
        return Cuti::query()
            ->where('status', 'diajukan')
            ->with('employee')
            ->orderBy('tanggal_mulai')
            ->get()
            ->filter(fn (Cuti $c): bool => $c->employee !== null
                && (int) ($this->approverUntuk($c->employee)?->id) === (int) $user->id)
            ->values();
    }

    private function pastikanApprover(Cuti $cuti, User $user): void
    {
        $employee = $cuti->employee;
        $approver = $employee !== null ? $this->approverUntuk($employee) : null;

        if ($approver === null || (int) $approver->id !== (int) $user->id) {
            throw new AuthorizationException('Hanya approver (kepala lembaga/fallback) yang boleh memutuskan izin ini.');
        }
    }

    private function fallbackApprover(Employee $employee): ?User
    {
        // Fallback 1: pemegang permission run_payroll pertama (admin HR),
        // bukan pegawai itu sendiri. Scope Spatie melempar bila permission
        // belum di-seed di tenant — anggap tak ada kandidat.
        try {
            $kandidat = User::permission('run_payroll')
                ->orderBy('id')
                ->get()
                ->first(fn (User $u): bool => (int) $u->id !== (int) $employee->users_id);
        } catch (PermissionDoesNotExist) {
            $kandidat = null;
        }

        if ($kandidat !== null) {
            return $kandidat;
        }

        // Fallback 2: user pertama tenant (admin) selain pegawai sendiri.
        return User::query()->orderBy('id')
            ->when($employee->users_id, fn ($q) => $q->whereKeyNot($employee->users_id))
            ->first();
    }
}
