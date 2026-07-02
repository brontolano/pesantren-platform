<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Cek apakah tenant aktif.
 * Jika is_active = false → tampilkan halaman suspension (bukan 404).
 * Sesuai Blueprint §3b.
 */
class CheckTenantActive
{
    public function handle(Request $request, Closure $next): Response
    {
        $tenant = tenant();

        if ($tenant && ! $tenant->is_active) {
            return response()->view('tenant.suspended', [
                'tenantName' => $tenant->name ?? 'Pesantren',
            ], 503);
        }

        return $next($request);
    }
}
