<?php

declare(strict_types=1);

namespace App\Services;

/**
 * Hitung ARAH KIBLAT (bearing awal great-circle) dari satu titik menuju
 * Ka'bah (21.4225 LU, 39.8262 BT). Hasil: derajat 0–360 dari arah UTARA,
 * searah jarum jam. Dihitung lokal di PHP, tanpa API eksternal.
 */
class QiblaService
{
    private const KAABA_LAT = 21.4225;

    private const KAABA_LNG = 39.8262;

    public function bearing(float $lat, float $lng): float
    {
        $phi1 = deg2rad($lat);
        $phi2 = deg2rad(self::KAABA_LAT);
        $dLng = deg2rad(self::KAABA_LNG - $lng);

        $y = sin($dLng);
        $x = cos($phi1) * tan($phi2) - sin($phi1) * cos($dLng);

        return fmod(rad2deg(atan2($y, $x)) + 360.0, 360.0);
    }
}
