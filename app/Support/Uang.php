<?php

namespace App\Support;

/**
 * Parser nominal uang yang TIDAK AMBIGU — sumber tunggal untuk semua importer.
 *
 * Menangani format campur tanpa salah-skala (bug 100x): pemisah ribuan titik
 * Indonesia ("1.500.000"), desimal koma ("1.000.000,50"), MAUPUN desimal titik
 * gaya mesin ("1500.00" hasil cast decimal:2 saat Export round-trip).
 *
 * Aturan:
 *  - Ada titik DAN koma  → pemisah TERAKHIR = desimal, yang lain = ribuan.
 *  - Hanya koma          → koma = desimal (Indonesia).
 *  - Hanya titik         → ambigu: >1 titik ATAU tepat 3 digit setelah titik
 *                          = ribuan (1.500.000 / 1.500); selain itu = desimal
 *                          (1500.00 / 1500.5).
 */
class Uang
{
    public static function parse(mixed $nilai): float
    {
        if (is_int($nilai) || is_float($nilai)) {
            return round((float) $nilai, 2);
        }

        // Sisakan digit, titik, koma, minus (buang "Rp", spasi, dll).
        $s = preg_replace('/[^0-9.,\-]/', '', (string) $nilai) ?? '';
        if ($s === '' || $s === '-') {
            return 0.0;
        }

        $adaTitik = str_contains($s, '.');
        $adaKoma = str_contains($s, ',');

        if ($adaTitik && $adaKoma) {
            if (strrpos($s, ',') > strrpos($s, '.')) {
                // Indonesia: 1.500.000,50 → titik ribuan, koma desimal.
                $s = str_replace(',', '.', str_replace('.', '', $s));
            } else {
                // Internasional: 1,500,000.50 → koma ribuan.
                $s = str_replace(',', '', $s);
            }
        } elseif ($adaKoma) {
            $s = str_replace(',', '.', $s);
        } elseif ($adaTitik) {
            $jmlTitik = substr_count($s, '.');
            $digitSetelah = strlen($s) - (int) strrpos($s, '.') - 1;
            if ($jmlTitik > 1 || $digitSetelah === 3) {
                // Ribuan: 1.500.000 / 150.000 / 1.500.
                $s = str_replace('.', '', $s);
            }
            // selain itu titik = desimal (1500.00, 1500.5) → biarkan.
        }

        return is_numeric($s) ? round((float) $s, 2) : 0.0;
    }
}
