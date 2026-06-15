<?php

namespace Tests\Unit;

use App\Support\Uang;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Parser nominal tak ambigu: ribuan Indonesia, desimal koma, DAN desimal titik
 * gaya mesin (cast decimal:2) — yang dulu bikin bug inflasi 100x saat round-trip
 * Export ("1500.00") di-import lagi.
 */
class UangParseTest extends TestCase
{
    #[DataProvider('kasus')]
    public function test_parse(mixed $input, float $expected): void
    {
        $this->assertEqualsWithDelta($expected, Uang::parse($input), 0.001, 'gagal untuk: '.var_export($input, true));
    }

    /** @return array<string, array{0:mixed,1:float}> */
    public static function kasus(): array
    {
        return [
            'ribuan titik' => ['1.500.000', 1500000.0],
            'ribuan + desimal koma' => ['1.000.000,50', 1000000.5],
            'desimal koma saja' => ['1500,75', 1500.75],
            'desimal titik mesin (FIX)' => ['1500.00', 1500.0],        // bug 100x dulu → 150000
            'round-trip decimal:2 (FIX)' => ['150000.00', 150000.0],    // bug 100x dulu → 15000000
            'titik tunggal 3 digit (ID)' => ['1.500', 1500.0],          // ribuan Indonesia
            'desimal titik 1 digit' => ['1500.5', 1500.5],
            'polos tanpa pemisah' => ['1500000', 1500000.0],
            'internasional koma ribuan' => ['1,500,000.50', 1500000.5],
            'ada Rp + spasi' => ['Rp 2.000.000', 2000000.0],
            'integer' => [1500000, 1500000.0],
            'float' => [1500.5, 1500.5],
            'kosong' => ['', 0.0],
            'strip non-numerik' => ['-', 0.0],
            'besar (150jt)' => ['150.000.000', 150000000.0],
        ];
    }
}
