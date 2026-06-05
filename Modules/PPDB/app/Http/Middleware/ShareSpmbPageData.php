<?php

namespace Modules\PPDB\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Modules\PPDB\Services\PesertaBrandingService;
use Symfony\Component\HttpFoundation\Response;

/**
 * F1.2.1 — Data bersama semua halaman Portal SPMB (`/spmb/*`).
 *
 * Membagikan via Inertia::share (scoped HANYA ke group route /spmb —
 * TIDAK menyentuh HandleInertiaRequests global yang dipakai modul lain):
 *
 *  - `branding`: tema white-label dari settings tenant (kunci `branding_*`
 *    yang sama dengan PengaturanBranding di Filament & landing page CMS —
 *    configuration over customization, satu sumber kebenaran).
 *  - `flash`: pesan success/error/info dari session (closure = lazy,
 *    dievaluasi saat render sehingga flash request berjalan ikut terbaca).
 *
 * Wajib dipasang SETELAH InitializeTenancyByDomain — Setting dibaca dari
 * DB tenant aktif.
 */
class ShareSpmbPageData
{
    public function handle(Request $request, Closure $next): Response
    {
        Inertia::share([
            'branding' => fn (): array => app(PesertaBrandingService::class)->resolve(),
            'flash' => fn (): array => [
                'success' => $request->session()->get('success'),
                'error' => $request->session()->get('error'),
                'info' => $request->session()->get('info'),
            ],
        ]);

        return $next($request);
    }
}
