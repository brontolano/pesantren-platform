<?php

namespace Modules\PPDB\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Modules\PPDB\Database\Seeders\PesertaRoleSeeder;
use Symfony\Component\HttpFoundation\Response;

/**
 * F1.1.6 — Gerbang area peserta `/spmb/*` (alias middleware: `peserta`).
 *
 * Hukum #2 Blueprint (SATU KEAMANAAN): middleware ini TIDAK membuat guard
 * baru — hanya memeriksa guard `web` existing + role Spatie `peserta`.
 *
 * Aturan:
 *  - Belum login  → redirect ke halaman login portal (`/spmb/login`).
 *    SENGAJA tidak memakai middleware `auth` bawaan karena ia me-redirect ke
 *    route('login') yang tidak terdaftar di domain tenant.
 *  - Login tapi BUKAN role `peserta` (mis. staf ERP/wali) → 403. Staf punya
 *    panel Filament sendiri; portal /spmb khusus calon santri.
 *
 * Batas data per-record (peserta hanya `calon_santri` miliknya) ditegakkan
 * oleh {@see \Modules\PPDB\Policies\CalonSantriPolicy}, bukan di sini.
 * Isolasi antar-tenant bersifat FISIK (database-per-tenant, Blueprint §3).
 */
class EnsureUserIsPeserta
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user === null) {
            return redirect()->guest('/spmb/login');
        }

        if (! $user->hasRole(PesertaRoleSeeder::ROLE)) {
            abort(403, 'Halaman ini khusus peserta SPMB. Silakan gunakan panel sesuai peran Anda.');
        }

        return $next($request);
    }
}
