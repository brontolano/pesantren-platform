<?php

declare(strict_types=1);

namespace App\Services;

use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;

/**
 * Hitung jadwal sholat HARIAN secara LOKAL di PHP (tanpa API eksternal).
 *
 * Algoritma: port setia dari PrayTimes.org (Hamid Zarrabi-Zadeh) — algoritma
 * astronomi standar yang dipakai mayoritas aplikasi jadwal sholat Indonesia.
 * Parameter mengikuti metode Kemenag RI:
 *   - Subuh  : matahari -20° di ufuk timur
 *   - Isya   : matahari -18° di ufuk barat
 *   - Ashar  : bayangan = 1× tinggi benda (mazhab Syafi'i)
 *   - Imsak  : 10 menit sebelum Subuh
 *   - Ihtiyati (pengaman) : +2 menit pada Subuh/Dzuhur/Ashar/Maghrib/Isya
 *
 * Referensi konsep (REST API Kemenag): https://github.com/renomureza/waktu-sholat
 * Di sini dihitung offline agar tidak bergantung pada uptime/rate-limit pihak ketiga.
 */
class PrayerTimesService
{
    private float $fajrAngle = 20.0;     // Kemenag

    private float $ishaAngle = 18.0;     // Kemenag

    private float $asrFactor = 1.0;      // Syafi'i: bayangan 1× tinggi benda

    private float $riseSetAngle = 0.833; // refraksi + radius matahari

    private float $ihtiyati = 2.0;       // menit pengaman

    /**
     * Jadwal sholat untuk satu tanggal & lokasi.
     *
     * @param  float  $lat  Lintang (derajat, selatan negatif)
     * @param  float  $lng  Bujur (derajat, timur positif)
     * @param  float  $tz   Offset zona waktu jam (WIB = 7)
     * @return array<string,string>  label => "HH:MM"
     */
    public function times(
        float $lat,
        float $lng,
        float $tz = 7.0,
        ?DateTimeInterface $date = null,
    ): array {
        $date = $date
            ? DateTimeImmutable::createFromInterface($date)
            : new DateTimeImmutable('now', new DateTimeZone('Asia/Jakarta'));

        $y = (int) $date->format('Y');
        $m = (int) $date->format('n');
        $d = (int) $date->format('j');

        // Julian date di tengah hari lokal (dikoreksi bujur).
        $jDate = $this->julian($y, $m, $d) - $lng / (15 * 24);

        // Tebakan awal porsi hari (jam/24) — sesuai default PrayTimes.
        $fajr    = $this->sunAngleTime($this->fajrAngle, $jDate, $lat, 5 / 24, true);
        $sunrise = $this->sunAngleTime($this->riseSetAngle, $jDate, $lat, 6 / 24, true);
        $dhuhr   = $this->midDay($jDate, 12 / 24);
        $asr     = $this->asrTime($this->asrFactor, $jDate, $lat, 13 / 24);
        $sunset  = $this->sunAngleTime($this->riseSetAngle, $jDate, $lat, 18 / 24, false);
        $isha    = $this->sunAngleTime($this->ishaAngle, $jDate, $lat, 18 / 24, false);

        // Konversi waktu surya → waktu jam lokal.
        $offset = $tz - $lng / 15;
        $fajr += $offset;
        $sunrise += $offset;
        $dhuhr += $offset;
        $asr += $offset;
        $sunset += $offset;
        $isha += $offset;

        // Ihtiyati (pengaman) ala jadwal Kemenag.
        $ih = $this->ihtiyati / 60;
        $fajr += $ih;
        $dhuhr += $ih;
        $asr += $ih;
        $maghrib = $sunset + $ih;
        $isha += $ih;

        $imsak = $fajr - 10 / 60;

        return [
            'imsak'   => $this->fmt($imsak),
            'subuh'   => $this->fmt($fajr),
            'terbit'  => $this->fmt($sunrise),
            'dzuhur'  => $this->fmt($dhuhr),
            'ashar'   => $this->fmt($asr),
            'maghrib' => $this->fmt($maghrib),
            'isya'    => $this->fmt($isha),
        ];
    }

    /**
     * Lima waktu sholat wajib saja (untuk panel ringkas), berurutan.
     *
     * @return array<int,array{key:string,label:string,time:string}>
     */
    public function fivePrayers(
        float $lat,
        float $lng,
        float $tz = 7.0,
        ?DateTimeInterface $date = null,
    ): array {
        $t = $this->times($lat, $lng, $tz, $date);

        return [
            ['key' => 'subuh',   'label' => 'Subuh',   'time' => $t['subuh']],
            ['key' => 'dzuhur',  'label' => 'Dzuhur',  'time' => $t['dzuhur']],
            ['key' => 'ashar',   'label' => 'Ashar',   'time' => $t['ashar']],
            ['key' => 'maghrib', 'label' => 'Maghrib', 'time' => $t['maghrib']],
            ['key' => 'isya',    'label' => 'Isya',    'time' => $t['isya']],
        ];
    }

    /**
     * Tentukan sholat berikutnya dari waktu "sekarang" (HH:MM lokal).
     * Mengembalikan key sholat berikutnya (subuh/dzuhur/ashar/maghrib/isya),
     * atau 'subuh' bila semua sudah lewat (besok subuh).
     *
     * @param  array<int,array{key:string,label:string,time:string}>  $prayers
     */
    public function nextPrayerKey(array $prayers, ?DateTimeInterface $now = null): string
    {
        $now = $now
            ? DateTimeImmutable::createFromInterface($now)
            : new DateTimeImmutable('now', new DateTimeZone('Asia/Jakarta'));
        $nowMin = (int) $now->format('G') * 60 + (int) $now->format('i');

        foreach ($prayers as $p) {
            [$h, $mi] = array_map('intval', explode(':', $p['time']));
            if ($h * 60 + $mi > $nowMin) {
                return $p['key'];
            }
        }

        return $prayers[0]['key'] ?? 'subuh';
    }

    // ---- internal astronomy ------------------------------------------------

    private function sunAngleTime(float $angle, float $jDate, float $lat, float $t, bool $ccw): float
    {
        $decl = $this->sunPosition($jDate + $t)[0];
        $noon = $this->midDay($jDate, $t);

        $x = (-$this->dsin($angle) - $this->dsin($decl) * $this->dsin($lat))
            / ($this->dcos($decl) * $this->dcos($lat));
        $x = max(-1.0, min(1.0, $x));

        $tt = (1 / 15) * $this->darccos($x);

        return $noon + ($ccw ? -$tt : $tt);
    }

    private function asrTime(float $factor, float $jDate, float $lat, float $t): float
    {
        $decl = $this->sunPosition($jDate + $t)[0];
        $angle = -$this->darccot($factor + $this->dtan(abs($lat - $decl)));

        return $this->sunAngleTime($angle, $jDate, $lat, $t, false);
    }

    private function midDay(float $jDate, float $t): float
    {
        $eqt = $this->sunPosition($jDate + $t)[1];

        return $this->fixhour(12 - $eqt);
    }

    /**
     * @return array{0:float,1:float}  [deklinasi (deg), equation of time (jam)]
     */
    private function sunPosition(float $jd): array
    {
        $D = $jd - 2451545.0;
        $g = $this->fixangle(357.529 + 0.98560028 * $D);
        $q = $this->fixangle(280.459 + 0.98564736 * $D);
        $L = $this->fixangle($q + 1.915 * $this->dsin($g) + 0.020 * $this->dsin(2 * $g));

        $e = 23.439 - 0.00000036 * $D;
        $RA = $this->darctan2($this->dcos($e) * $this->dsin($L), $this->dcos($L)) / 15;
        $decl = $this->darcsin($this->dsin($e) * $this->dsin($L));
        $eqt = $q / 15 - $this->fixhour($RA);

        return [$decl, $eqt];
    }

    private function julian(int $y, int $m, int $d): float
    {
        if ($m <= 2) {
            $y -= 1;
            $m += 12;
        }
        $a = floor($y / 100);
        $b = 2 - $a + floor($a / 4);

        return floor(365.25 * ($y + 4716)) + floor(30.6001 * ($m + 1)) + $d + $b - 1524.5;
    }

    private function dsin(float $x): float
    {
        return sin(deg2rad($x));
    }

    private function dcos(float $x): float
    {
        return cos(deg2rad($x));
    }

    private function dtan(float $x): float
    {
        return tan(deg2rad($x));
    }

    private function darcsin(float $x): float
    {
        return rad2deg(asin($x));
    }

    private function darccos(float $x): float
    {
        return rad2deg(acos($x));
    }

    private function darctan2(float $y, float $x): float
    {
        return rad2deg(atan2($y, $x));
    }

    private function darccot(float $x): float
    {
        return rad2deg(atan2(1, $x));
    }

    private function fixangle(float $a): float
    {
        $a -= 360 * floor($a / 360);

        return $a < 0 ? $a + 360 : $a;
    }

    private function fixhour(float $a): float
    {
        $a -= 24 * floor($a / 24);

        return $a < 0 ? $a + 24 : $a;
    }

    private function fmt(float $t): string
    {
        $t = $this->fixhour($t + 0.5 / 60); // bulatkan ke menit terdekat
        $h = (int) floor($t);
        $mi = (int) floor(($t - $h) * 60);

        return sprintf('%02d:%02d', $h, $mi);
    }
}
