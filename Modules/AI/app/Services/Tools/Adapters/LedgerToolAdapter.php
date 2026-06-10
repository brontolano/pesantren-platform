<?php

namespace Modules\AI\Services\Tools\Adapters;

use App\Models\User;
use Modules\Ledger\Services\LaporanService;

/**
 * T-A033 — adapter READ-ONLY ke modul Ledger (Blueprint §6):
 * getRingkasanKeuangan → LaporanService::neraca()/labaRugi()/arusKas()
 * (arusKas = BukuBesarService::bku, sudah HANYA jurnal posted by-construction).
 *
 * DILARANG menyentuh matematika Ledger — kelas ini cuma MERANGKUM keluaran
 * service resmi, tidak menghitung ulang apa pun.
 */
class LedgerToolAdapter
{
    public function __construct(
        private readonly LaporanService $laporan,
    ) {}

    /**
     * @param  array<string, mixed>  $args
     * @return array{0: array<string, mixed>, 1: string}
     */
    public function getRingkasanKeuangan(array $args, ?User $pemanggil = null): array
    {
        $dari = isset($args['dari']) ? (string) $args['dari'] : null;
        $sampai = isset($args['sampai']) ? (string) $args['sampai'] : null;

        $neraca = $this->laporan->neraca($sampai);
        $labaRugi = $this->laporan->labaRugi($dari, $sampai);
        $arusKas = $this->laporan->arusKas($dari, $sampai);

        return [
            [
                'periode' => ['dari' => $dari, 'sampai' => $sampai],
                'neraca' => [
                    'total_aset' => $neraca['total_aset'],
                    'total_kewajiban_ekuitas' => $neraca['total_kewajiban_ekuitas'],
                    'surplus_berjalan' => $neraca['surplus_berjalan'],
                    'seimbang' => $neraca['seimbang'],
                ],
                'laba_rugi' => [
                    'total_pendapatan' => $labaRugi['total_pendapatan'],
                    'total_beban' => $labaRugi['total_beban'],
                    'surplus_defisit' => $labaRugi['surplus_defisit'],
                ],
                'arus_kas' => [
                    'saldo_awal' => $arusKas['saldo_awal'],
                    'total_masuk' => $arusKas['total_masuk'],
                    'total_keluar' => $arusKas['total_keluar'],
                    'saldo_akhir' => $arusKas['saldo_akhir'],
                ],
            ],
            'Modules\Ledger — LaporanService (jurnal posted)',
        ];
    }
}
