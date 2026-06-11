<?php

namespace Modules\Payroll\Services;

use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Modules\Akademik\Models\JadwalPelajaran;
use Modules\Akademik\Models\JurnalMengajar;
use Modules\Akademik\Models\Nilai;
use Modules\Payroll\Models\Employee;
use Modules\Payroll\Models\PenilaianUstadz;
use Modules\Payroll\Models\PresensiStaf;

/**
 * WP-20 — Rapor Ustadz Tahunan. Agregat READ-ONLY dari tabel existing.
 *
 * KEPUTUSAN DENOMINATOR (didokumentasikan — jadwal_pelajaran TIDAK punya
 * guru_id; guru hanya tertaut via pelajaran.guru_pengampu_id, dan jadwal
 * bersifat slot mingguan tanpa rentang tanggal):
 *
 * - kehadiran_sesi_pct: jurnal status 'terlaksana' vs TOTAL JURNAL guru
 *   tahun itu. Dipilih vs-jurnal-total (bukan vs-jadwal) karena ekspansi
 *   jadwal mingguan → jumlah sesi riil setahun bersifat spekulatif (libur,
 *   periode aktif berbeda-beda). Jurnal 'kosong'/'diganti' menurunkan angka.
 *
 * - jurnal_terisi_pct: jumlah jurnal vs EKSPEKTASI = slot jadwal mingguan
 *   milik guru (via pelajaran.guru_pengampu_id) × 52 minggu. Kasar tapi
 *   transparan; null bila guru tak punya slot jadwal.
 *
 * - nilai_input_total: count baris Nilai ber-input_by user guru, created_at
 *   tahun itu (proksi ketepatan input nilai).
 *
 * - presensi_pct: PresensiStaf status 'hadir' vs total baris tahun itu.
 */
class RaporUstadzService
{
    private const MINGGU_PER_TAHUN = 52;

    /**
     * @return array{
     *   tahun:int, jurnal_total:int, jurnal_terlaksana:int, kehadiran_sesi_pct:?float,
     *   slot_jadwal_mingguan:int, jurnal_ekspektasi:?int, jurnal_terisi_pct:?float,
     *   nilai_input_total:int, presensi_total:int, presensi_hadir:int, presensi_pct:?float,
     *   penilaian:?PenilaianUstadz
     * }
     */
    public function rekap(Employee $employee, int $tahun): array
    {
        $userId = $employee->users_id;

        $jurnalTotal = 0;
        $jurnalTerlaksana = 0;
        $slotMingguan = 0;
        $nilaiInput = 0;

        if ($userId !== null) {
            $jurnal = JurnalMengajar::query()
                ->where('guru_id', $userId)
                ->whereYear('tanggal', $tahun);
            $jurnalTotal = (clone $jurnal)->count();
            $jurnalTerlaksana = (clone $jurnal)->where('status', 'terlaksana')->count();

            $slotMingguan = JadwalPelajaran::query()
                ->whereHas('pelajaran', fn ($q) => $q->where('guru_pengampu_id', $userId))
                ->count();

            $nilaiInput = Nilai::query()
                ->where('input_by', $userId)
                ->whereYear('created_at', $tahun)
                ->count();
        }

        $presensi = PresensiStaf::query()
            ->where('employee_id', $employee->id)
            ->whereYear('tanggal', $tahun);
        $presensiTotal = (clone $presensi)->count();
        $presensiHadir = (clone $presensi)->where('status', 'hadir')->count();

        $ekspektasiJurnal = $slotMingguan > 0 ? $slotMingguan * self::MINGGU_PER_TAHUN : null;

        return [
            'tahun' => $tahun,
            'jurnal_total' => $jurnalTotal,
            'jurnal_terlaksana' => $jurnalTerlaksana,
            'kehadiran_sesi_pct' => $jurnalTotal > 0 ? round($jurnalTerlaksana / $jurnalTotal * 100, 2) : null,
            'slot_jadwal_mingguan' => $slotMingguan,
            'jurnal_ekspektasi' => $ekspektasiJurnal,
            'jurnal_terisi_pct' => $ekspektasiJurnal !== null ? round(min(100, $jurnalTotal / $ekspektasiJurnal * 100), 2) : null,
            'nilai_input_total' => $nilaiInput,
            'presensi_total' => $presensiTotal,
            'presensi_hadir' => $presensiHadir,
            'presensi_pct' => $presensiTotal > 0 ? round($presensiHadir / $presensiTotal * 100, 2) : null,
            'penilaian' => PenilaianUstadz::query()
                ->where('employee_id', $employee->id)
                ->where('tahun', $tahun)
                ->first(),
        ];
    }

    /**
     * Simpan/perbarui penilaian kualitatif mudir. GATE: hanya pemegang
     * manage_payroll/run_payroll — pegawai biasa 403 (AuthorizationException).
     *
     * @param  array<string, int|float|string>  $skor  {kriteria: skor 0..100}
     */
    public function simpanPenilaian(Employee $employee, int $tahun, array $skor, ?string $catatan, User $penilai): PenilaianUstadz
    {
        if (! $penilai->canAny(['manage_payroll', 'run_payroll'])) {
            throw new AuthorizationException('Hanya mudir/atasan (manage_payroll/run_payroll) yang boleh menilai.');
        }

        return PenilaianUstadz::query()->updateOrCreate(
            ['employee_id' => $employee->id, 'tahun' => $tahun],
            ['skor' => $skor, 'catatan' => $catatan, 'dinilai_oleh' => $penilai->id],
        );
    }

    /** Boleh lihat rapor: mudir/atasan ATAU pegawai ybs (read-only). */
    public function bolehLihat(Employee $employee, User $user): bool
    {
        return $user->canAny(['manage_payroll', 'run_payroll'])
            || ($employee->users_id !== null && $employee->users_id === $user->id);
    }
}
