<?php

namespace App\Http\Middleware;

use App\Models\TenantApiKey;
use App\Support\ApiKeyExtractor;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Auth API publik headless dgn API key per tenant (lihat App\Models\TenantApiKey).
 *
 * Kunci dibaca dari header `X-Api-Key` atau `Authorization: Bearer`. Hanya kunci
 * AKTIF (revoked_at null) yang hash-nya cocok DAN milik tenant aktif yang lolos —
 * menjaga isolasi antar pesantren. Respons 401 JSON bila tidak valid.
 *
 * Wiring (follow-up, sengaja tak diubah di iterasi ini): daftarkan alias di
 * bootstrap/app.php, mis. $middleware->alias(['tenant.apikey' => AuthenticateTenantApiKey::class]),
 * lalu pasang pada grup route API publik yang ingin dilindungi.
 */
class AuthenticateTenantApiKey
{
    public function handle(Request $request, Closure $next): Response
    {
        $key = ApiKeyExtractor::fromHeaders(
            $request->header('X-Api-Key'),
            $request->header('Authorization'),
        );

        if ($key === null || ! ApiKeyExtractor::looksValid($key)) {
            return response()->json(['message' => 'API key tidak disertakan atau format salah.'], 401);
        }

        $record = TenantApiKey::query()
            ->whereNull('revoked_at')
            ->where('key_hash', TenantApiKey::hashKey($key))
            ->first();

        $tenantId = tenant()?->getTenantKey();

        // Kunci harus ada, aktif, dan (bila dalam konteks tenant) milik tenant ini.
        if ($record === null || ($tenantId !== null && $record->tenant_id !== $tenantId)) {
            return response()->json(['message' => 'API key tidak valid.'], 401);
        }

        $record->forceFill(['last_used_at' => now()])->saveQuietly();

        return $next($request);
    }
}
