<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Paksa peserta dengan `must_reset_password = true` mengganti password sebelum
 * mengakses halaman portal lain (kredensial sementara dari panitia harus diganti).
 * Dipasang pada authMiddleware panel `portal`.
 */
class EnsurePesertaPasswordReset
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user instanceof User && ($user->must_reset_password ?? false)) {
            $current = $request->path(); // tanpa leading slash, mis. "portal/ganti-password"

            // Izinkan halaman ganti password + proses logout agar tidak loop redirect.
            if ($current !== 'portal/ganti-password' && ! str_ends_with($current, '/logout')) {
                return redirect()->to('/portal/ganti-password');
            }
        }

        return $next($request);
    }
}
