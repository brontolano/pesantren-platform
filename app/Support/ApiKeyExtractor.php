<?php

namespace App\Support;

use App\Models\TenantApiKey;

/**
 * Ekstraksi API key dari header request. Pure (tanpa DB) — dipisah agar
 * mudah diuji & dipakai ulang oleh middleware auth API publik.
 */
class ApiKeyExtractor
{
    /**
     * Ambil API key dari header `X-Api-Key` atau `Authorization: Bearer <key>`.
     * Mengembalikan null bila tak ada.
     */
    public static function fromHeaders(?string $xApiKey, ?string $authorization): ?string
    {
        if (is_string($xApiKey) && trim($xApiKey) !== '') {
            return trim($xApiKey);
        }

        if (is_string($authorization) && preg_match('/^Bearer\s+(.+)$/i', trim($authorization), $m)) {
            $token = trim($m[1]);

            return $token !== '' ? $token : null;
        }

        return null;
    }

    /**
     * Validasi bentuk kunci sebelum query DB (hindari lookup sia-sia):
     * harus ber-prefix benar & panjang wajar.
     */
    public static function looksValid(string $key): bool
    {
        return str_starts_with($key, TenantApiKey::PLAIN_PREFIX) && strlen($key) >= 20;
    }
}
