<?php

namespace Modules\Payroll\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\Payroll\Models\Employee;
use Modules\Payroll\Models\Pelamar;

/**
 * WP-19 — Rekrutmen Ringan.
 *
 * - naikkanTahap: hanya transisi sah (maju SATU tahap pipeline, atau
 *   'ditolak' dari tahap non-final). Catatan penilai disimpan per tahap
 *   tujuan di JSON catatan_penilai {tahap: catatan}.
 * - jadikanPegawai: hanya tahap 'diterima'; IDEMPOTEN — pelamar yang sudah
 *   punya employee_id ditolak (tidak pernah membuat Employee dobel).
 *   TIDAK auto-create penugasan (WP-03) — hanya hint di UI.
 */
class RekrutmenService
{
    public function naikkanTahap(Pelamar $pelamar, string $tahapBaru, ?string $catatan = null): Pelamar
    {
        $valid = $pelamar->tahapTujuanValid();

        if (! in_array($tahapBaru, $valid, true)) {
            throw ValidationException::withMessages([
                'tahap' => sprintf(
                    "Transisi tahap '%s' → '%s' tidak sah. Tahap tujuan valid: %s.",
                    $pelamar->tahap,
                    $tahapBaru,
                    $valid === [] ? '(tidak ada — tahap final)' : implode(', ', $valid),
                ),
            ]);
        }

        $catatanPenilai = $pelamar->catatan_penilai ?? [];
        if ($catatan !== null && trim($catatan) !== '') {
            $catatanPenilai[$tahapBaru] = trim($catatan);
        }

        $pelamar->forceFill([
            'tahap' => $tahapBaru,
            'catatan_penilai' => $catatanPenilai,
        ])->save();

        return $pelamar->refresh();
    }

    /**
     * Konversi pelamar diterima → Employee baru.
     *
     * Pemetaan field (schema Employee TIDAK punya kolom kontak/asal — data
     * itu tetap terarsip di baris pelamar yang bertaut employee_id):
     * nama→nama, is_alumni→asal_alumni, tanggal_masuk=today, aktif=true.
     */
    public function jadikanPegawai(Pelamar $pelamar): Employee
    {
        if ($pelamar->tahap !== 'diterima') {
            throw ValidationException::withMessages([
                'tahap' => "Hanya pelamar tahap 'diterima' yang bisa dijadikan pegawai (tahap saat ini: {$pelamar->tahap}).",
            ]);
        }

        if ($pelamar->sudahDikonversi()) {
            throw ValidationException::withMessages([
                'employee_id' => 'Pelamar ini sudah pernah dikonversi menjadi pegawai (idempoten — tidak bisa 2×).',
            ]);
        }

        return DB::transaction(function () use ($pelamar): Employee {
            // Kunci baris — dua klik paralel tidak boleh membuat 2 Employee.
            $terkunci = Pelamar::query()->lockForUpdate()->findOrFail($pelamar->id);
            if ($terkunci->employee_id !== null) {
                throw ValidationException::withMessages([
                    'employee_id' => 'Pelamar ini sudah pernah dikonversi menjadi pegawai.',
                ]);
            }

            $employee = Employee::create([
                'nama' => $terkunci->nama,
                'asal_alumni' => $terkunci->is_alumni,
                'tanggal_masuk' => today(),
                'status_kepegawaian' => 'kontrak',
                'aktif' => true,
            ]);

            $terkunci->forceFill(['employee_id' => $employee->id])->save();
            $pelamar->employee_id = $employee->id;

            return $employee;
        });
    }
}
