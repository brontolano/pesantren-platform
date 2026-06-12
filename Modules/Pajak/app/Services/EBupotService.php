<?php

namespace Modules\Pajak\Services;

use Modules\Payroll\Models\Employee;
use Modules\Payroll\Models\SlipGaji;

/**
 * Generator e-Bupot PPh 21 (bukti potong) — format CSV Coretax DJP.
 *
 * Output: array of rows siap export ke CSV (header + data). Setiap baris = 1
 * bukti potong per pegawai per masa pajak. Format mengikuti template import
 * e-Bupot 21/26 Coretax (per Jan 2025).
 *
 * ⚠️ GATE KEPATUHAN: format CSV Coretax bisa berubah. Validasi terhadap
 *    template terbaru sebelum upload ke coretax.pajak.go.id.
 */
class EBupotService
{
    public const PERLU_REVIEW_PENASIHAT_PAJAK = true;

    /**
     * Generate data bukti potong untuk satu masa pajak.
     *
     * @return array{header: array, rows: array<int, array>, summary: array}
     */
    public function generate(string $masaPajak, ?string $npwpPemotong = null): array
    {
        $tahun = substr($masaPajak, 0, 4);
        $bulan = substr($masaPajak, 5, 2);

        $slips = SlipGaji::query()
            ->where('periode', $masaPajak)
            ->where('status', 'final')
            ->where('pph21_dipotong', '>', 0)
            ->get();

        $employeeIds = $slips->pluck('users_id')->unique();
        $employees = Employee::whereIn('users_id', $employeeIds->all())
            ->get()
            ->keyBy('users_id');

        $rows = [];
        $nomorUrut = 1;

        foreach ($slips as $slip) {
            $emp = $employees->get($slip->users_id);
            if (! $emp) {
                continue;
            }

            $rows[] = [
                'nomor_urut' => $nomorUrut++,
                'masa_pajak' => $bulan,
                'tahun_pajak' => $tahun,
                'pembetulan_ke' => 0,
                'nik' => $emp->nik ?? '',
                'npwp' => $emp->npwp ?? '',
                'nama' => $emp->nama ?? $slip->user?->name ?? '',
                'kode_objek_pajak' => '21-100-01', // penghasilan pegawai tetap
                'ptkp_status' => $emp->ptkp_status ?? 'TK0',
                'bruto' => (float) $slip->bruto,
                'pph21_dipotong' => (float) $slip->pph21_dipotong,
                'tanggal_potong' => $tahun.'-'.$bulan.'-25',
                'npwp_pemotong' => $npwpPemotong ?? '',
                'metode' => 'ter',
            ];
        }

        return [
            'header' => array_keys($rows[0] ?? $this->emptyRow()),
            'rows' => $rows,
            'summary' => [
                'masa_pajak' => $masaPajak,
                'jumlah_bukti_potong' => count($rows),
                'total_bruto' => round(array_sum(array_column($rows, 'bruto')), 2),
                'total_pph21' => round(array_sum(array_column($rows, 'pph21_dipotong')), 2),
            ],
        ];
    }

    /**
     * Export ke format CSV string (siap download).
     */
    public function toCsv(string $masaPajak, ?string $npwpPemotong = null): string
    {
        $data = $this->generate($masaPajak, $npwpPemotong);

        $output = fopen('php://temp', 'r+');
        fputcsv($output, $data['header']);

        foreach ($data['rows'] as $row) {
            fputcsv($output, array_values($row));
        }

        rewind($output);
        $csv = stream_get_contents($output);
        fclose($output);

        return $csv;
    }

    /**
     * Generate rekap tahunan (1721-A1) untuk satu pegawai.
     * Dipakai untuk bukti potong tahunan karyawan tetap.
     *
     * @return array{pegawai: array, slip_bulanan: array, ringkasan: array}
     */
    public function rekapTahunan(int $usersId, string $tahun): array
    {
        $employee = Employee::where('users_id', $usersId)->first();
        $slips = SlipGaji::where('users_id', $usersId)
            ->where('status', 'final')
            ->where('periode', 'like', $tahun.'-%')
            ->whereRaw("periode ~ '^\d{4}-(0[1-9]|1[0-2])$'")
            ->orderBy('periode')
            ->get();

        $pajakSvc = app(PajakPenghasilanService::class);
        $totalBruto = (float) $slips->sum('bruto');
        $totalPph21 = (float) $slips->sum('pph21_dipotong');

        $ptkpStatus = $employee->ptkp_status ?? 'TK0';
        $biayaJabatan = round(min($totalBruto * 0.05, 6000000), 2);
        $ptkp = $pajakSvc->ptkp($ptkpStatus);
        $pkp = floor(max(0.0, $totalBruto - $biayaJabatan - $ptkp) / 1000) * 1000;
        $pph21Tahunan = $pajakSvc->pph21Tahunan($pkp);

        return [
            'pegawai' => [
                'nama' => $employee?->nama ?? '',
                'nik' => $employee?->nik ?? '',
                'npwp' => $employee?->npwp ?? '',
                'ptkp_status' => $ptkpStatus,
                'jabatan' => $employee?->jabatan ?? '',
            ],
            'slip_bulanan' => $slips->map(fn ($s) => [
                'periode' => $s->periode,
                'bruto' => (float) $s->bruto,
                'pph21' => (float) $s->pph21_dipotong,
            ])->all(),
            'ringkasan' => [
                'tahun' => $tahun,
                'jumlah_bulan' => $slips->count(),
                'total_bruto' => round($totalBruto, 2),
                'biaya_jabatan' => $biayaJabatan,
                'ptkp' => $ptkp,
                'pkp' => $pkp,
                'pph21_terutang' => $pph21Tahunan,
                'pph21_sudah_dipotong' => round($totalPph21, 2),
                'kurang_bayar' => round($pph21Tahunan - $totalPph21, 2),
            ],
        ];
    }

    private function emptyRow(): array
    {
        return [
            'nomor_urut' => '', 'masa_pajak' => '', 'tahun_pajak' => '',
            'pembetulan_ke' => '', 'nik' => '', 'npwp' => '', 'nama' => '',
            'kode_objek_pajak' => '', 'ptkp_status' => '', 'bruto' => '',
            'pph21_dipotong' => '', 'tanggal_potong' => '', 'npwp_pemotong' => '',
            'metode' => '',
        ];
    }
}
