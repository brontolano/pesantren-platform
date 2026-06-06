<?php

namespace Tests\Unit;

use Modules\PPDB\Models\SoalUjian;
use Tests\TestCase;

/**
 * Auto-grading bank soal ujian PPDB. Murni logika model (tanpa DB).
 */
class SoalUjianGradingTest extends TestCase
{
    private function pg(string $kunci, int $bobot): SoalUjian
    {
        return new SoalUjian([
            'tipe' => SoalUjian::TIPE_PG,
            'kunci_jawaban' => $kunci,
            'bobot' => $bobot,
        ]);
    }

    public function test_pg_jawaban_benar_dapat_bobot(): void
    {
        $soal = $this->pg('B', 5);

        $this->assertTrue($soal->isPg());
        $this->assertSame(5, $soal->nilaiUntukJawaban('B'));
        $this->assertSame(5, $soal->nilaiUntukJawaban('b'));   // abaikan kapital
        $this->assertSame(5, $soal->nilaiUntukJawaban('  b ')); // abaikan spasi
    }

    public function test_pg_jawaban_salah_atau_kosong_nol(): void
    {
        $soal = $this->pg('B', 5);

        $this->assertSame(0, $soal->nilaiUntukJawaban('A'));
        $this->assertSame(0, $soal->nilaiUntukJawaban(null));
        $this->assertSame(0, $soal->nilaiUntukJawaban(''));
    }

    public function test_essay_dinilai_manual_null(): void
    {
        $soal = new SoalUjian(['tipe' => SoalUjian::TIPE_ESSAY, 'bobot' => 10]);

        $this->assertTrue($soal->isEssay());
        $this->assertNull($soal->nilaiUntukJawaban('jawaban panjang apa pun'));
    }

    public function test_pg_tanpa_kunci_nol(): void
    {
        $soal = $this->pg('', 5);

        $this->assertSame(0, $soal->nilaiUntukJawaban('A'));
    }
}
