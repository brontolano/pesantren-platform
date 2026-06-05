<?php

namespace Modules\PPDB\Support;

/**
 * Normalisasi No. HP Indonesia ke format `62…` (E.164 tanpa '+').
 *
 * Dipakai bersama oleh: API pendaftaran publik, service penerbitan kredensial,
 * dan halaman login portal — WAJIB identik agar nomor yang diterbitkan saat
 * issuance bisa dipakai login (mis. '08…' dan '+62…' → '62…').
 */
class PpdbPhone
{
    public static function normalize(string $raw): string
    {
        $digits = preg_replace('/[^\d+]/', '', $raw) ?? '';
        if ($digits === '') {
            return '';
        }
        if (str_starts_with($digits, '+62')) {
            $digits = '62'.substr($digits, 3);
        }
        if (str_starts_with($digits, '0')) {
            $digits = '62'.substr($digits, 1);
        }

        return $digits;
    }
}
