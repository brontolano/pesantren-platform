<?php

namespace Tests\Unit;

use Modules\PPDB\Models\SesiUjian;
use Modules\PPDB\Models\SoalUjian;
use Modules\PPDB\Services\UjianScoringService;
use Tests\TestCase;

/**
 * Persistensi sesi ujian + integrasi dgn hasil UjianScoringService. Tanpa DB.
 */
class SesiUjianTest extends TestCase
{
    public function test_isi_dari_skor_dengan_essay(): void
    {
        $sesi = new SesiUjian;
        $sesi->isiDariSkor([
            'auto_score' => 5,
            'max_auto_score' => 8,
            'butuh_manual' => [['soal_id' => 3, 'bobot' => 10]],
        ]);

        $this->assertSame(5, $sesi->auto_score);
        $this->assertSame(8, $sesi->max_auto_score);
        $this->assertSame(62.5, $sesi->persenAuto());
        $this->assertTrue($sesi->butuhPenilaianManual());
        $this->assertSame(SesiUjian::STATUS_SELESAI, $sesi->status);
        $this->assertTrue($sesi->isSelesai());
        $this->assertFalse($sesi->isBerlangsung());
    }

    public function test_tanpa_essay_tidak_butuh_manual(): void
    {
        $sesi = new SesiUjian;
        $sesi->isiDariSkor(['auto_score' => 4, 'max_auto_score' => 4, 'butuh_manual' => []]);

        $this->assertFalse($sesi->butuhPenilaianManual());
        $this->assertSame(100.0, $sesi->persenAuto());
    }

    public function test_persen_null_tanpa_pg(): void
    {
        $sesi = new SesiUjian;
        $sesi->isiDariSkor(['auto_score' => 0, 'max_auto_score' => 0, 'butuh_manual' => []]);

        $this->assertNull($sesi->persenAuto());
    }

    public function test_integrasi_dengan_scoring_service(): void
    {
        $soal = [
            tap(new SoalUjian(['tipe' => 'pg', 'kunci_jawaban' => 'A', 'bobot' => 5]), fn ($s) => $s->id = 1),
            tap(new SoalUjian(['tipe' => 'essay', 'bobot' => 10]), fn ($s) => $s->id = 2),
        ];
        $hasil = (new UjianScoringService)->score($soal, [1 => 'A', 2 => 'esai']);

        $sesi = (new SesiUjian)->isiDariSkor($hasil);

        $this->assertSame(5, $sesi->auto_score);
        $this->assertSame(100.0, $sesi->persenAuto());
        $this->assertTrue($sesi->butuhPenilaianManual()); // ada essay
    }
}
