<?php

namespace Modules\PPDB\Http\Middleware;

use App\Models\Setting;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Halaman PPDB iframe-friendly per tenant (task Integrasi).
 *
 * Menyetel CSP `frame-ancestors` EKSPLISIT pada form publik /ppdb:
 *  - 'self' + seluruh domain terdaftar tenant (tabel domains), plus
 *  - origin tambahan dari Setting `embed_allowed_origins` (CSV, https only) —
 *    untuk website pesantren yang TIDAK didelegasikan ke platform.
 *
 * Tanpa konfigurasi: 'self' + domain tenant → embed liar dari situs asing
 * justru DIBLOKIR (perbaikan dari kondisi sebelumnya yang tanpa kebijakan).
 */
class FrameAncestorsPpdb
{
    public function handle(Request $request, Closure $next): Response
    {
        /** @var Response $response */
        $response = $next($request);

        $ancestors = ["'self'"];

        if (tenancy()->initialized && tenancy()->tenant !== null) {
            foreach (tenancy()->tenant->domains()->pluck('domain') as $domain) {
                $ancestors[] = 'https://'.$domain;
            }

            $extra = (string) (Setting::getTenantStringValue('embed_allowed_origins') ?? '');
            foreach (array_filter(array_map('trim', explode(',', $extra))) as $origin) {
                // Hanya origin https eksplisit — tolak skema lain & wildcard liar.
                if (str_starts_with($origin, 'https://') && ! str_contains($origin, ' ')) {
                    $ancestors[] = $origin;
                }
            }
        }

        $response->headers->set(
            'Content-Security-Policy',
            'frame-ancestors '.implode(' ', array_unique($ancestors)),
        );
        // Jangan biarkan X-Frame-Options lama (bila ada dari layer lain) menimpa CSP.
        $response->headers->remove('X-Frame-Options');

        return $response;
    }
}
