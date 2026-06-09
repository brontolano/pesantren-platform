<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Stancl\Tenancy\Middleware\InitializeTenancyByDomain;
use Symfony\Component\HttpFoundation\Response;

/**
 * Inisialisasi tenancy untuk SEMUA request grup web di domain tenant —
 * termasuk endpoint Livewire (/livewire/update) yang dipakai form login &
 * interaksi panel Filament. Tanpa ini, request Livewire jalan di konteks
 * central sehingga auth tenant gagal ("kredensial tidak ditemukan").
 *
 * Domain central dilewati (tetap pakai DB central).
 */
class InitializeTenancyForWebRequests
{
    public function handle(Request $request, Closure $next): Response
    {
        $host = $request->getHost();

        if (in_array($host, config('tenancy.central_domains', []), true)) {
            return $next($request);
        }

        if (tenancy()->initialized) {
            return $next($request);
        }

        // www.<apex> bukan domain tenant maupun central → redirect permanen (301)
        // ke apex kanonik, mempertahankan path + query. Mencegah 500
        // "tenant tak teridentifikasi" untuk kunjungan www.* yang sah.
        if (str_starts_with($host, 'www.')) {
            $apex = substr($host, 4);

            return redirect()->away('https://'.$apex.$request->getRequestUri(), 301);
        }

        return app(InitializeTenancyByDomain::class)->handle($request, $next);
    }
}
