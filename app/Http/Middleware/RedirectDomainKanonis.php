<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Stancl\Tenancy\Database\Models\Domain;
use Symfony\Component\HttpFoundation\Response;

/**
 * Kanonisasi domain per-tenant (OTOMATIS + override manual) — menghapus
 * "dualitas panel admin".
 *
 * Kasus nyata: pesantren.asy-syifaa.com/admin dan asy-syifaa.pesantren.online/admin
 * adalah panel yang SAMA (dua record type='sistem' menunjuk tenant yang sama).
 * Dualitas ini merugikan: sesi login & localStorage (tema/dark mode) terpisah
 * per origin, URL yang dibagikan bercampur dua domain, landing terindeks ganda
 * (SEO), dan nanti PWA: service worker + manifest ter-scope per origin sehingga
 * pengguna bisa punya DUA instalasi PWA berbeda untuk satu pesantren.
 *
 * Aturan resolusi target kanonis (keputusan owner 2026-07-03: "subdomain
 * *.pesantren.online DISABLE bila ada custom domain" — otomatis, bukan opt-in):
 * 1. Bila salah satu domain tenant (type sama) ditandai is_canonical=true →
 *    pakai itu (override manual via aksi UI "Domain Utama").
 * 2. Bila TIDAK ada is_canonical: OTOMATIS — bila host request adalah
 *    subdomain platform (*.{suffix central, dari config tenancy.central_domains})
 *    DAN tenant punya ≥1 domain CUSTOM (bukan subdomain platform) dengan `type`
 *    SAMA → 301 ke custom domain itu. Bila lebih dari satu custom ber-type
 *    sama, pilihan deterministik: is_primary=true dulu, lalu ID terkecil
 *    (yang paling lama didaftarkan).
 * 3. Custom domain TIDAK PERNAH di-redirect otomatis — hanya oleh
 *    is_canonical eksplisit.
 * Tenant yang hanya punya subdomain platform (demo, albakids) → nol perubahan.
 *
 * Konservatif by design:
 * - Hanya GET/HEAD (301). POST/PUT/... dibiarkan lewat — tidak mengganggu
 *   Livewire, webhook, atau form submit yang sedang berjalan.
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

        $kanonis = $this->resolveTargetKanonis($saatIni, $host);

        if (! is_string($kanonis) || $kanonis === '' || $kanonis === $host) {
            return $next($request);
        }

        return redirect()->away(
            $request->getScheme().'://'.$kanonis.$request->getRequestUri(),
            301,
        );
    }

    /**
     * Resolusi target kanonis untuk host saat ini (lihat aturan di docblock kelas).
     */
    protected function resolveTargetKanonis(Domain $saatIni, string $host): ?string
    {
        // Aturan 1: override manual (is_canonical) menang — berlaku dari host
        // apa pun, termasuk custom domain.
        $eksplisit = Domain::query()
            ->where('tenant_id', $saatIni->tenant_id)
            ->where('is_canonical', true)
            ->where('type', $saatIni->type)
            ->value('domain');

        if (is_string($eksplisit) && $eksplisit !== '') {
            return $eksplisit;
        }

        // Aturan 3: custom domain tidak pernah di-redirect otomatis.
        if (! $this->adalahSubdomainPlatform($host)) {
            return null;
        }

        // Aturan 2: otomatis — subdomain platform → custom domain ber-type sama.
        // Deterministik: is_primary dulu, lalu ID terkecil (terdaftar paling lama).
        return Domain::query()
            ->where('tenant_id', $saatIni->tenant_id)
            ->where('type', $saatIni->type)
            ->where('domain', '!=', $host)
            ->orderByDesc('is_primary')
            ->orderBy('id')
            ->get(['domain', 'is_primary'])
            // Record label-telanjang tanpa titik (mis. 'albakids' — artefak
            // identifikasi subdomain stancl/tenancy) BUKAN host valid dan tak
            // boleh jadi target kanonis (regresi: 301 ke http://albakids/).
            ->first(fn (Domain $d): bool => str_contains($d->domain, '.')
                && ! $this->adalahSubdomainPlatform($d->domain))
            ?->domain;
    }

    /**
     * Host adalah subdomain platform bila berakhiran ".{central_domain}"
     * (suffix dari config tenancy.central_domains — tidak di-hardcode).
     */
    protected function adalahSubdomainPlatform(string $host): bool
    {
        foreach (config('tenancy.central_domains', []) as $suffix) {
            if (is_string($suffix) && $suffix !== '' && str_ends_with($host, '.'.$suffix)) {
                return true;
            }
        }

        return false;
    }
}
