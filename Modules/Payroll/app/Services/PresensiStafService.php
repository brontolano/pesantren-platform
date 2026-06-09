<?php

namespace Modules\Payroll\Services;

use Modules\Payroll\Models\PresensiStaf;
use RuntimeException;

/**
 * G2-11 — Presensi staf harian + rekap bulanan.
 */
class PresensiStafService
{
    public const STATUS = ['hadir', 'izin', 'sakit', 'alpa', 'cuti'];

    /** Catat/timpa presensi staf untuk satu hari (idempoten per employee+tanggal). */
    public function catat(int $employeeId, string $tanggal, string $status = 'hadir', array $opsi = []): PresensiStaf
    {
        $this->ensureTenantContext();
        if (! in_array($status, self::STATUS, true)) {
            throw new \InvalidArgumentException("Status presensi '{$status}' tidak valid.");
        }

        return PresensiStaf::updateOrCreate(
            ['employee_id' => $employeeId, 'tanggal' => $tanggal],
            [
                'status' => $status,
                'jam_masuk' => $opsi['jam_masuk'] ?? null,
                'jam_keluar' => $opsi['jam_keluar'] ?? null,
                'keterangan' => $opsi['keterangan'] ?? null,
            ],
        );
    }

    /**
     * Rekap presensi 1 bulan (Y-m) untuk satu employee: jumlah per status + total hari.
     *
     * @return array{hadir:int, izin:int, sakit:int, alpa:int, cuti:int, total:int}
     */
    public function rekapBulanan(int $employeeId, string $bulan): array
    {
        $this->ensureTenantContext();

        $rows = PresensiStaf::query()
            ->where('employee_id', $employeeId)
            ->whereYear('tanggal', (int) substr($bulan, 0, 4))
            ->whereMonth('tanggal', (int) substr($bulan, 5, 2))
            ->get();

        $rekap = ['hadir' => 0, 'izin' => 0, 'sakit' => 0, 'alpa' => 0, 'cuti' => 0];
        foreach ($rows as $r) {
            $rekap[$r->status] = ($rekap[$r->status] ?? 0) + 1;
        }
        $rekap['total'] = array_sum($rekap);

        return $rekap;
    }

    private function ensureTenantContext(): void
    {
        if (! tenancy()->initialized || tenancy()->tenant === null) {
            throw new RuntimeException('Tenant context belum aktif untuk presensi.');
        }
    }
}
