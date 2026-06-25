<?php

namespace Modules\Pajak\Services;

use Modules\Payroll\Models\Employee;
use Modules\Payroll\Models\SlipGaji;

/**
 * SPT Masa PPh 21 (formulir 1721) — rekapitulasi bulanan.
 *
 * Menghasilkan data terstruktur siap ditampilkan atau diekspor ke format
 * yang bisa diimport ke Coretax DJP (SPT Masa PPh 21/26).
 */
class SptMasaService
{
    /**
     * Rekap SPT Masa 1721 untuk satu masa pajak.
     *
     * @return array{masa_pajak: string, bagian_a: array, bagian_b: array, lampiran: array}
     */
    public function rekap(string $masaPajak): array
    {
        $slips = SlipGaji::query()
            ->where('periode', $masaPajak)
            ->where('status', 'final')
            ->with('user')
            ->get();

        $employees = Employee::whereIn('users_id', $slips->pluck('users_id')->unique()->all())
            ->get()
            ->keyBy('users_id');

        $pegawaiTetap = $slips->filter(fn ($s) => $this->isPegawaiTetap($employees->get($s->users_id)));
        $pegawaiTidakTetap = $slips->filter(fn ($s) => ! $this->isPegawaiTetap($employees->get($s->users_id)));

        return [
            'masa_pajak' => $masaPajak,
            'bagian_a' => [
                'pegawai_tetap' => [
                    'jumlah_penerima' => $pegawaiTetap->count(),
                    'bruto' => round((float) $pegawaiTetap->sum('bruto'), 2),
                    'pph21' => round((float) $pegawaiTetap->sum('pph21_dipotong'), 2),
                ],
                'pegawai_tidak_tetap' => [
                    'jumlah_penerima' => $pegawaiTidakTetap->count(),
                    'bruto' => round((float) $pegawaiTidakTetap->sum('bruto'), 2),
                    'pph21' => round((float) $pegawaiTidakTetap->sum('pph21_dipotong'), 2),
                ],
                'total' => [
                    'jumlah_penerima' => $slips->count(),
                    'bruto' => round((float) $slips->sum('bruto'), 2),
                    'pph21' => round((float) $slips->sum('pph21_dipotong'), 2),
                ],
            ],
            'bagian_b' => [
                'pph21_disetor' => round((float) $slips->sum('pph21_dipotong'), 2),
                'kode_map' => '411121',   // MAP PPh 21
                'kode_kjs' => '100',      // KJS setoran masa
            ],
            'lampiran' => $slips->map(fn (SlipGaji $s) => [
                'nama' => $employees->get($s->users_id)?->nama ?? $s->user?->name ?? '—',
                'npwp' => $employees->get($s->users_id)?->npwp ?? '',
                'nik' => $employees->get($s->users_id)?->nik ?? '',
                'kode_objek' => $this->isPegawaiTetap($employees->get($s->users_id)) ? '21-100-01' : '21-100-02',
                'bruto' => (float) $s->bruto,
                'pph21' => (float) $s->pph21_dipotong,
            ])->values()->all(),
        ];
    }

    /**
     * Rekap SPT Masa setahun (12 masa) — ringkasan per bulan.
     */
    public function rekapTahunan(string $tahun): array
    {
        $bulanan = [];
        $totalBruto = 0.0;
        $totalPph21 = 0.0;
        $totalPenerima = 0;

        for ($b = 1; $b <= 12; $b++) {
            $masa = $tahun.'-'.str_pad($b, 2, '0', STR_PAD_LEFT);
            $r = $this->rekap($masa);
            $bulanan[] = [
                'masa_pajak' => $masa,
                'jumlah_penerima' => $r['bagian_a']['total']['jumlah_penerima'],
                'bruto' => $r['bagian_a']['total']['bruto'],
                'pph21' => $r['bagian_a']['total']['pph21'],
            ];
            $totalBruto += $r['bagian_a']['total']['bruto'];
            $totalPph21 += $r['bagian_a']['total']['pph21'];
            $totalPenerima += $r['bagian_a']['total']['jumlah_penerima'];
        }

        return [
            'tahun' => $tahun,
            'bulanan' => $bulanan,
            'total_bruto' => round($totalBruto, 2),
            'total_pph21' => round($totalPph21, 2),
            'total_penerima' => $totalPenerima,
        ];
    }

    private function isPegawaiTetap(?Employee $emp): bool
    {
        if (! $emp) {
            return true;
        }

        return in_array($emp->status_kepegawaian, ['gty', 'pns_dpk', 'pppk'], true);
    }
}
