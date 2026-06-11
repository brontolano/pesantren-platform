<?php

namespace Modules\Payroll\Services;

use App\Models\Setting;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Modules\Payroll\Models\Employee;
use Modules\Payroll\Models\SlipGaji;
use RuntimeException;

/**
 * WP-15 — THR (Tunjangan Hari Raya), MONEY WP.
 *
 * Formula per tenant via Setting (policy-first):
 *   - 'nominal_tetap'     → payroll.thr_nominal (rupiah, sama untuk semua)
 *   - 'kali_honor_pokok'  → honor_pokok struktur gaji × payroll.thr_pengali (default 1)
 *   - 'manual'            → input per pegawai di layar Generate THR ($override)
 *
 * Slip THR: tipe='thr', periode='THR-<tahun>' (sengaja BUKAN YYYY-MM agar:
 * (1) tak bentrok unique (tenant_id, users_id, periode) slip bulanan, dan
 * (2) hitungPotonganCuti() WP-14 return 0 — guard periode non-YYYY-MM).
 * Draft dibuat TANPA PPh21/BPJS otomatis (0) — bendahara dapat menjalankan
 * PayrollService::terapkanPotongan() bila kebijakan pesantren memotong THR.
 * Finalisasi memakai jalur existing PayrollService::finalisasi() (tak ada
 * jalur posting baru).
 */
class ThrService
{
    public const SETTING_FORMULA = 'payroll.thr_formula';

    public const SETTING_NOMINAL = 'payroll.thr_nominal';

    public const SETTING_PENGALI = 'payroll.thr_pengali';

    public const FORMULA = ['nominal_tetap', 'kali_honor_pokok', 'manual'];

    /** Formula aktif tenant; parsing toleran, fallback 'nominal_tetap'. */
    public function formula(): string
    {
        $nilai = $this->settingScalar(self::SETTING_FORMULA, 'nominal_tetap');
        $nilai = is_string($nilai) ? trim($nilai) : 'nominal_tetap';

        return in_array($nilai, self::FORMULA, true) ? $nilai : 'nominal_tetap';
    }

    /**
     * Nominal THR untuk satu pegawai menurut formula tenant.
     * $override[employee_id] (rupiah) menang bila terisi — wajib utk 'manual'.
     */
    public function nominalUntuk(Employee $employee, array $override = []): float
    {
        if (array_key_exists($employee->id, $override) && is_numeric($override[$employee->id])) {
            return round(max(0.0, (float) $override[$employee->id]), 2);
        }

        return match ($this->formula()) {
            'kali_honor_pokok' => round(
                $employee->strukturGaji()['honor_pokok'] * $this->angka(self::SETTING_PENGALI, 1.0),
                2,
            ),
            'manual' => 0.0, // tanpa input → 0, dilewati saat generate.
            default => round($this->angka(self::SETTING_NOMINAL, 0.0), 2),
        };
    }

    /**
     * Pratinjau utk layar Generate THR: semua pegawai AKTIF + nominal per
     * formula + status (sudah punya THR tahun ini / tanpa akun login).
     *
     * @return list<array{employee: Employee, nominal: float, sudah_ada: bool, tanpa_akun: bool}>
     */
    public function pratinjau(int $tahun, array $override = []): array
    {
        $this->ensureTenantContext();

        $sudahAda = SlipGaji::query()
            ->where('tipe', 'thr')->where('tahun_thr', $tahun)
            ->pluck('users_id')->all();

        return Employee::query()->where('aktif', true)->orderBy('nama')->get()
            ->map(fn (Employee $e): array => [
                'employee' => $e,
                'nominal' => $this->nominalUntuk($e, $override),
                'sudah_ada' => $e->users_id !== null && in_array($e->users_id, $sudahAda, true),
                'tanpa_akun' => $e->users_id === null,
            ])->all();
    }

    /**
     * Generate slip THR draft massal — IDEMPOTEN: pegawai yang sudah punya
     * THR tahun itu dilewati (cek app-level + partial unique index DB sebagai
     * pagar race). Pegawai non-aktif & tanpa users_id TIDAK kebagian.
     *
     * @param  array<int, float|int|string>  $override  employee_id => nominal (formula manual / koreksi)
     * @return array{dibuat: int, dilewati: int, tanpa_akun: int, nominal_nol: int}
     */
    public function generateMassal(int $tahun, array $override = [], ?int $createdBy = null): array
    {
        $this->ensureTenantContext();
        if ($tahun < 2000 || $tahun > 2100) {
            throw new InvalidArgumentException("Tahun THR tak wajar: {$tahun}.");
        }

        $ringkasan = ['dibuat' => 0, 'dilewati' => 0, 'tanpa_akun' => 0, 'nominal_nol' => 0];

        foreach (Employee::query()->where('aktif', true)->orderBy('id')->get() as $employee) {
            if (! $employee->users_id) {
                $ringkasan['tanpa_akun']++;

                continue;
            }

            $sudahAda = SlipGaji::query()
                ->where('tipe', 'thr')->where('tahun_thr', $tahun)
                ->where('users_id', $employee->users_id)->exists();
            if ($sudahAda) {
                $ringkasan['dilewati']++;

                continue;
            }

            $nominal = $this->nominalUntuk($employee, $override);
            if ($nominal <= 0) {
                $ringkasan['nominal_nol']++; // manual tanpa input / nominal_tetap belum diset.

                continue;
            }

            try {
                DB::transaction(fn () => SlipGaji::create([
                    'users_id' => $employee->users_id,
                    'periode' => 'THR-'.$tahun,
                    'tipe' => 'thr',
                    'tahun_thr' => $tahun,
                    'honor_pokok' => $nominal, // bruto = formula; komponen lain 0.
                    'bruto' => $nominal,
                    'pph21_dipotong' => 0, // SENGAJA 0 — opt-in via terapkanPotongan().
                    'neto' => $nominal,
                    'status' => 'draft',
                    'created_by' => $createdBy,
                ]));
                $ringkasan['dibuat']++;
            } catch (QueryException $e) {
                // Race generate paralel: unique violation (partial THR-index
                // ATAU unique periode lama) = sudah ada → dilewati, bukan error.
                if ($e->getCode() === '23505') {
                    $ringkasan['dilewati']++;

                    continue;
                }
                throw $e;
            }
        }

        return $ringkasan;
    }

    /** Setting toleran: string angka / array{value} / scalar. */
    private function settingScalar(string $key, mixed $default): mixed
    {
        $nilai = Setting::getTenantValue($key, $default);
        if (is_array($nilai)) {
            $nilai = $nilai['value'] ?? $default;
        }

        return $nilai;
    }

    private function angka(string $key, float $default): float
    {
        $nilai = $this->settingScalar($key, $default);

        return is_numeric($nilai) ? (float) $nilai : $default;
    }

    private function ensureTenantContext(): void
    {
        if (! tenancy()->initialized || tenancy()->tenant === null) {
            throw new RuntimeException('Tenant context belum aktif.');
        }
    }
}
