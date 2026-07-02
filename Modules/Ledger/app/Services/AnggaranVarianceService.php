<?php

namespace Modules\Ledger\Services;

use Modules\Ledger\Models\Anggaran;
use RuntimeException;

/**
 * G1-08 — Laporan varians Anggaran (RKAM) vs Realisasi. READ-ONLY: tidak
 * memposting/mengubah apa pun. Anti-tumpang-tindih: memakai
 * AnggaranGuardService::realisasi() (LIVE), tidak menghitung ulang realisasi.
 */
class AnggaranVarianceService
{
    public function __construct(
        private readonly AnggaranGuardService $guard = new AnggaranGuardService,
    ) {}

    /**
     * Varians per pos anggaran + total + proyeksi akhir tahun (linear burn).
     *
     * @param  int  $bulanBerjalan  1..12 — basis forecast (mis. bulan berjalan).
     * @return array{tahun:string, bulan_berjalan:int, baris:array<int,array>, total:array}
     */
    public function laporan(string $tahun, int $bulanBerjalan = 12): array
    {
        $this->ensureTenantContext();
        $bulan = max(1, min(12, $bulanBerjalan));

        $baris = Anggaran::query()
            ->where('tahun_anggaran', $tahun)
            ->with(['akun', 'divisi', 'sumberDana'])
            ->orderBy('akun_id')
            ->get()
            ->map(function (Anggaran $a) use ($bulan): array {
                $pagu = (float) $a->nominal_anggaran;
                $realisasi = $this->guard->realisasi(
                    (int) $a->akun_id,
                    $a->divisi_id ? (int) $a->divisi_id : null,
                    $a->sumber_dana_id ? (int) $a->sumber_dana_id : null,
                    (string) $a->tahun_anggaran,
                );
                $sisa = round($pagu - $realisasi, 2);
                $persen = $pagu > 0.005 ? round($realisasi / $pagu * 100, 1) : 0.0;
                $forecast = round($realisasi / $bulan * 12, 2); // proyeksi linear akhir tahun

                return [
                    'id' => $a->id,
                    'akun' => trim(($a->akun?->kode ? $a->akun->kode.' · ' : '').($a->akun?->nama ?? '—')),
                    'divisi' => $a->divisi?->nama,
                    'sumber_dana' => $a->sumberDana?->nama,
                    'mode' => $a->mode,
                    'pagu' => $pagu,
                    'realisasi' => $realisasi,
                    'sisa' => $sisa,
                    'persen' => $persen,
                    'forecast' => $forecast,
                    'forecast_lampaui' => $forecast > $pagu + 0.005,
                    'status' => $this->status($persen, $realisasi, $pagu),
                ];
            })->all();

        $pagu = round(array_sum(array_column($baris, 'pagu')), 2);
        $realisasi = round(array_sum(array_column($baris, 'realisasi')), 2);

        return [
            'tahun' => $tahun,
            'bulan_berjalan' => $bulan,
            'baris' => $baris,
            'total' => [
                'pagu' => $pagu,
                'realisasi' => $realisasi,
                'sisa' => round($pagu - $realisasi, 2),
                'persen' => $pagu > 0.005 ? round($realisasi / $pagu * 100, 1) : 0.0,
            ],
        ];
    }

    /** Status pos: aman <75% · waspada 75-90% · kritis ≥90% · lampaui >pagu. */
    public function status(float $persen, float $realisasi, float $pagu): string
    {
        if ($realisasi > $pagu + 0.005) {
            return 'lampaui';
        }
        if ($persen >= 90) {
            return 'kritis';
        }
        if ($persen >= 75) {
            return 'waspada';
        }

        return 'aman';
    }

    private function ensureTenantContext(): void
    {
        if (! tenancy()->initialized || tenancy()->tenant === null) {
            throw new RuntimeException('Tenant context belum aktif untuk laporan anggaran.');
        }
    }
}
