<?php

namespace App\Support;

/**
 * Standardisasi nomor telepon Indonesia ke format kanonik `62…` (E.164 tanpa
 * '+', siap kirim WhatsApp & disimpan konsisten). SATU sumber kebenaran untuk
 * seluruh sistem — semua normalizer lain WAJIB mendelegasikan ke sini.
 *
 * IDEMPOTEN: normalize(normalize(x)) === normalize(x). Aman dipanggil berulang
 * sehingga tidak menghasilkan country code ganda (mis. "62 62 0…").
 *
 * Aturan: nomor seluler nasional Indonesia mulai dengan '8' (08…). Maka kita
 * buang SEMUA prefiks depan berupa nol dan/atau '62' (termasuk kombinasi &
 * pengulangan: "0", "62", "620", "6262", "0062"), lalu tambahkan satu '62'.
 */
class PhoneNumber
{
    /** Format kanonik `62…` (kosong bila tak ada digit). */
    public static function normalize(?string $raw): string
    {
        $digits = preg_replace('/\D/', '', (string) $raw) ?? '';
        if ($digits === '') {
            return '';
        }

        // Buang prefiks internasional/lokal di depan secara berulang:
        // 0062, 62, dan 0 (termasuk ganda) — sampai tersisa bagian nasional.
        $national = preg_replace('/^(?:00)?(?:62|0)+/', '', $digits) ?? '';

        if ($national === '') {
            return '';
        }

        return '62'.$national;
    }

    /** True bila menghasilkan nomor yang masuk akal (panjang wajar). */
    public static function isValid(?string $raw): bool
    {
        $n = self::normalize($raw);

        // 62 + 9..13 digit nasional (Indonesia: 10–13 total setelah 62).
        return $n !== '' && strlen($n) >= 10 && strlen($n) <= 15;
    }

    /** Tampilan lokal '08…' (untuk UI), atau '-' bila kosong. */
    public static function local(?string $raw): string
    {
        $n = self::normalize($raw);

        return $n === '' ? '-' : '0'.substr($n, 2);
    }
}
