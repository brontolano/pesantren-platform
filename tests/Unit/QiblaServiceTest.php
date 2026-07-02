<?php

namespace Tests\Unit;

use App\Services\QiblaService;
use PHPUnit\Framework\TestCase;

class QiblaServiceTest extends TestCase
{
    public function test_arah_kiblat_indonesia_barat_laut(): void
    {
        $q = new QiblaService;

        // Jakarta (-6.2, 106.816) → kiblat ≈ 295° (barat-laut). Toleransi ±3°.
        $jakarta = $q->bearing(-6.2, 106.816);
        $this->assertGreaterThan(292.0, $jakarta);
        $this->assertLessThan(298.0, $jakarta);

        // Surabaya (-7.25, 112.75) → kiblat ≈ 294°.
        $surabaya = $q->bearing(-7.25, 112.75);
        $this->assertGreaterThan(291.0, $surabaya);
        $this->assertLessThan(297.0, $surabaya);
    }

    public function test_hasil_selalu_0_sampai_360(): void
    {
        $q = new QiblaService;
        foreach ([[-6.2, 106.8], [21.4225, 39.8262], [51.5, -0.12], [-33.8, 151.2]] as [$lat, $lng]) {
            $b = $q->bearing($lat, $lng);
            $this->assertGreaterThanOrEqual(0.0, $b);
            $this->assertLessThan(360.0, $b);
        }
    }
}
