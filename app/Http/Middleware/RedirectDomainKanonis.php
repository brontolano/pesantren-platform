<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Stancl\Tenancy\Database\Models\Domain;
use Symfony\Component\HttpFoundation\Response;

/**
 * Kanonisasi domain per-tenant (OPT-IN) — menghapus "dualitas panel admin".
 *
 * Kasus nyata: pesantren.asy-syifaa.com/admin dan asy-syifaa.pesantren.online/admin
 * adalah panel yang SAMA (dua record type='sistem' menunjuk tenant yang sama).
 * Dualitas ini merugikan: sesi login & localStorage (tema/dark mode) terpisah
 * per origin, URL yang dibagikan bercampur dua domain, landing terindeks ganda
 * (SEO), dan nanti PWA: service worker + manifest ter-scope per origin sehingga
 * pengguna bisa punya DUA instalasi PWA berbeda untuk satu pesantren.
 *
 * Perilaku: bila salah satu domain tenant ditandai is_canonical=true DAN request
 * masuk lewat domain lain milik tenant yang sama dengan `type` yang sama
 * (mis. sesama 'sistem'), request web GET/HEAD di-redirect 301 ke host kanonis
 * dengan path + query utuh. Domain ber-type beda (mis. 'website' vs 'sistem')
 * TIDAK disentuh — kontennya memang berbeda.
 *
 * Konservatif by design:
 * - Hanya GET/HEAD (301). POST/PUT/... dibiarkan lewat — tidak mengganggu
 *   Livewire, webhook, atau form submit yang sedang berjalan.
 * - Tenant tanpa domain kanonis = perilaku lama, nol perubahan (demo/albakids).
 * - Berjalan SEBELUM inisialisasi tenancy (query langsung ke tabel domains
 *   central) sehingga resolusi tenancy sama sekali tidak berubah.
 * - Rute infrastruktur dikecualikan (lihat $kecuali) — terutama signed URL
 *   (signature mengikat host; redirect lintas host mematahkan signature).
 */
class RedirectDomainKanonis
{
    /** @var list<string> Prefix path yang TIDAK boleh di-redirect. */
    protected array $kecuali = [
        'up',                // health check
        'caddy/ssl-check',   // on-demand TLS ask endpoint
        '_tenant-storage',   // serve file tenant (dipakai lintas-origin)
        'livewire',          // endpoint Livewire (update/upload/preview)
        'api',               // API stateless (dipakai app Android/website)
        'ppdb/dok',          // signed URL — signature terikat host
        'embed',             // widget embed utk situs eksternal
    ];

    public function handle(Request $request, Closure $next): Response
    {
        if (! in_array($request->getMethod(), ['GET', 'HEAD'], true)) {
            return $next($request);
        }

        $host = $request->getHost();

        if (in_array($host, config('tenancy.central_domains', []), true)) {
            return $next($request);
        }

        foreach ($this->kecuali as $prefix) {
            if ($request->is($prefix) || $request->is($prefix.'/*')) {
                return $next($request);
            }
        }

        /** @var Domain|null $saatIni */
        $saatIni = Domain::query()->where('domain', $host)->first();

        if ($saatIni === null || $saatIni->is_canonical) {
            return $next($request);
        }

        $kanonis = Domain::query()
            ->where('tenant_id', $saatIni->tenant_id)
            ->where('is_canonical', true)
            ->where('type', $saatIni->type)
            ->value('domain');

        if (! is_string($kanonis) || $kanonis === '' || $kanonis === $host) {
            return $next($request);
        }

        return redirect()->away(
            $request->getScheme().'://'.$kanonis.$request->getRequestUri(),
            301,
        );
    }
}
