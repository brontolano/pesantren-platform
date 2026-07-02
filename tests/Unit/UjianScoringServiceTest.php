<?php

namespace Tests\Unit;

use Modules\PPDB\Models\SoalUjian;
use Modules\PPDB\Services\UjianScoringService;
use Tests\TestCase;

/**
 * Skoring ujian: agregasi auto-grade PG + pemisahan essay manual. Tanpa DB.
 */
class UjianScoringServiceTest extends TestCase
{
    private function soal(int $id, string $tipe, ?string $kunci, int $bobot): SoalUjian
    {
        $s = new SoalUjian(['tipe' => $tipe, 'kunci_jawaban' => $kunci, 'bobot' => $bobot]);
        $s->id = $id;

        return $s;
    }

    public function test_skor_campuran_pg_dan_essay(): void
    {
        $soal = [
            $this->soal(1, SoalUjian::TIPE_PG, 'B', 5),    // benar → +5
            $this->soal(2, SoalUjian::TIPE_PG, 'A', 3),    // salah → +0
            $this->soal(3, SoalUjian::TIPE_ESSAY, null, 10), // manual
        ];

        $hasil = (new UjianScoringService)->score($soal, [
            1 => 'B',
            2 => 'C',
            3 => 'jawaban essay peserta',
        ]);

        $this->assertSame(5, $hasil['auto_score']);
        $this->assertSame(8, $hasil['max_auto_score']);   // 5 + 3 (PG saja)
        $this->assertSame(3, $hasil['total_soal']);
        $this->assertSame(3, $hasil['terjawab']);
        $this->assertCount(1, $hasil['butuh_manual']);
        $this->assertSame(3, $hasil['butuh_manual'][0]['soal_id']);
        $this->assertSame(10, $hasil['butuh_manual'][0]['bobot']);
        $this->assertSame(62.5, $hasil['persen_auto']);   // 5/8
    }

    public function test_semua_benar_seratus_persen(): void
    {
        $soal = [
            $this->soal(1, SoalUjian::TIPE_PG, 'A', 2),
            $this->soal(2, SoalUjian::TIPE_PG, 'D', 2),
        ];

        $hasil = (new UjianScoringService)->score($soal, [1 => 'A', 2 => 'd']);

        $this->assertSame(4, $hasil['auto_score']);
        $this->assertSame(100.0, $hasil['persen_auto']);
        $this->assertSame([], $hasil['butuh_manual']);
    }

    public function test_tidak_terjawab_dihitung(): void
    {
        $soal = [$this->soal(1, SoalUjian::TIPE_PG, 'A', 5)];

        $hasil = (new UjianScoringService)->score($soal, []); // tak ada jawaban

        $this->assertSame(0, $hasil['auto_score']);
        $this->assertSame(0, $hasil['terjawab']);
        $this->assertSame(1, $hasil['total_soal']);
    }
}
