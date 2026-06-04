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
        if (in_array($request->getHost(), config('tenancy.central_domains', []), true)) {
            return $next($request);
        }

        if (tenancy()->initialized) {
            return $next($request);
        }

        return app(InitializeTenancyByDomain::class)->handle($request, $next);
    }
}
