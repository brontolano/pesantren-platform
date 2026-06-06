<?php

namespace App\Support;

/**
 * Aturan rate-limit API publik headless per tenant. Pure & teruji tanpa DB —
 * dipakai untuk mendaftarkan named limiter Laravel `public-api`.
 *
 * Kunci throttle dipilih dari yang paling spesifik:
 *  1. per API key (prefix)         — pemegang kunci dibatasi sendiri-sendiri
 *  2. per tenant + IP              — anonim/by-domain, tetap terisolasi antar tenant
 *  3. per IP                       — fallback (tak ada konteks tenant)
 */
class PublicApiThrottle
{
    public const LIMITER = 'public-api';

    public static function resolveKey(?string $tenantId, ?string $apiKeyPrefix, string $ip): string
    {
        $apiKeyPrefix = $apiKeyPrefix !== null ? trim($apiKeyPrefix) : '';
        if ($apiKeyPrefix !== '') {
            return 'pubapi:key:'.$apiKeyPrefix;
        }

        $tenantId = $tenantId !== null ? trim($tenantId) : '';
        if ($tenantId !== '') {
            return 'pubapi:tenant:'.$tenantId.':ip:'.$ip;
        }

        return 'pubapi:ip:'.$ip;
    }

    /** Batas request per menit (minimal 1). */
    public static function perMinute(): int
    {
        return max(1, (int) config('services.public_api.rate_per_minute', 60));
    }
}
