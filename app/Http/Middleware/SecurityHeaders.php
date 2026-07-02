<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Header keamanan global yang AMAN & non-breaking (red-team batch-1 / HIGH-002).
 *
 * SENGAJA tidak menyetel Content-Security-Policy maupun X-Frame-Options di sini:
 *  - X-Frame-Options global akan MERUSAK embed widget CMS + form PPDB yang memang
 *    dirancang untuk di-iframe lintas-origin (kebijakan frame-nya diatur per-rute
 *    via CSP frame-ancestors di middleware masing-masing).
 *  - CSP ketat berisiko mematahkan Filament/Inertia/Vue (inline) — disiapkan
 *    terpisah (report-only dulu) di batch lanjutan.
 *
 * Yang diset di sini hanya yang universal-aman: anti MIME-sniff, kebijakan
 * referrer, dan HSTS (semua domain sudah HTTPS via Caddy). Tak menimpa header
 * yang sudah diset middleware lain (defensif).
 */
class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);
        $headers = $response->headers;

        if (! $headers->has('X-Content-Type-Options')) {
            $headers->set('X-Content-Type-Options', 'nosniff');
        }

        if (! $headers->has('Referrer-Policy')) {
            $headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        }

        // HSTS hanya pada koneksi aman; jangan kirim di HTTP (mencegah pin keliru).
        if ($request->isSecure() && ! $headers->has('Strict-Transport-Security')) {
            $headers->set('Strict-Transport-Security', 'max-age=15768000; includeSubDomains');
        }

        return $response;
    }
}
