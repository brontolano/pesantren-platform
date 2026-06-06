<?php

namespace Modules\PPDB\Services;

use Modules\PPDB\Models\SoalUjian;

/**
 * Skoring ujian PPDB dari jawaban peserta.
 *
 * Menjumlahkan auto-grade pilihan ganda (SoalUjian::nilaiUntukJawaban) dan
 * memisahkan soal essay yang masih butuh penilaian manual. Pure & deterministik
 * (tanpa DB) — pemanggil yang menyiapkan koleksi soal & menyimpan hasilnya.
 * Tidak menyentuh HasilUjian / PpdbExamResultService milik tim.
 */
class UjianScoringService
{
    /**
     * @param  iterable<SoalUjian>  $soalList  daftar soal (punya id) untuk satu paket ujian
     * @param  array<int|string, ?string>  $jawaban  map: soal id → jawaban peserta
     * @return array{
     *     auto_score:int,
     *     max_auto_score:int,
     *     total_soal:int,
     *     terjawab:int,
     *     butuh_manual:list<array{soal_id:int|string|null, bobot:int}>,
     *     persen_auto:float
     * }
     */
    public function score(iterable $soalList, array $jawaban): array
    {
        $autoScore = 0;
        $maxAuto = 0;
        $total = 0;
        $terjawab = 0;
        $manual = [];

        foreach ($soalList as $soal) {
            $total++;
            $id = $soal->getKey();
            $jwb = $jawaban[$id] ?? null;

            if ($jwb !== null && trim((string) $jwb) !== '') {
                $terjawab++;
            }

            $nilai = $soal->nilaiUntukJawaban($jwb);

            if ($nilai === null) {
                // Essay → dinilai manual oleh penguji.
                $manual[] = ['soal_id' => $id, 'bobot' => (int) $soal->bobot];

                continue;
            }

            $autoScore += $nilai;
            $maxAuto += (int) $soal->bobot;
        }

        return [
            'auto_score' => $autoScore,
            'max_auto_score' => $maxAuto,
            'total_soal' => $total,
            'terjawab' => $terjawab,
            'butuh_manual' => $manual,
            'persen_auto' => $maxAuto > 0 ? round($autoScore / $maxAuto * 100, 1) : 0.0,
        ];
    }
}
