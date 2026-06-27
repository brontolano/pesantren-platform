<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Proteksi clickjacking untuk rute SENSITIF (login, panel Filament admin/tenant).
 *
 * Latar: SecurityHeaders SENGAJA tidak menyetel kebijakan frame global karena
 * form PPDB & widget CMS memang di-iframe lintas-origin. Niatnya kebijakan frame
 * diatur PER-RUTE. Namun sebelumnya hanya rute yang frameable (PPDB) yang punya
 * kebijakan; rute sensitif (/masuk, /admin, /platform) justru TANPA proteksi →
 * bisa di-clickjack. Middleware ini menutup celah itu khusus di rute sensitif.
 *
 * Menyetel `frame-ancestors 'self'` (boleh di-iframe oleh origin sendiri, tolak
 * lintas-origin). Pakai guard `! has(CSP)` agar TIDAK menimpa kebijakan eksplisit
 * dari rute embeddable (mis. FrameAncestorsPpdb yang menjalankan kebijakannya
 * sendiri) bila kelak middleware ini terpasang berlapis.
 */
class FrameGuard
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);
        $headers = $response->headers;

        if (! $headers->has('Content-Security-Policy')) {
            $headers->set('Content-Security-Policy', "frame-ancestors 'self'");
        }

        // X-Frame-Options sebagai jaring pengaman untuk peramban lama; selaras
        // dengan frame-ancestors 'self'.
        if (! $headers->has('X-Frame-Options')) {
            $headers->set('X-Frame-Options', 'SAMEORIGIN');
        }

        return $response;
    }
}
