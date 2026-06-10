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
        // Delegasi ke sumber kebenaran tunggal: App\Support\PhoneNumber
        // (idempoten, menangani +62/62/0 ganda — anti country-code dobel).
        return \App\Support\PhoneNumber::normalize($raw);
    }
}
