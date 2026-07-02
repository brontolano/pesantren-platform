<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Gerbang area jemaah (1-aplikasi, Fase 2b/2c). Pola cermin EnsureUserIsWali:
 * tamu diarahkan ke login jemaah, non-jemaah / tanpa izin 'komentar' ditolak.
 * Dipakai untuk endpoint area-jemaah (logout kini; komentar menyusul Fase 2c).
 */
class EnsureUserIsJemaah
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user instanceof User) {
            return redirect()->guest('/jemaah/login');
        }

        abort_unless($user->hasRole('jemaah') && $user->can('komentar'), 403);

        return $next($request);
    }
}
