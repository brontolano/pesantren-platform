<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Stancl\Tenancy\Database\Models\Domain;
use Symfony\Component\HttpFoundation\Response;

/**
 * Kanonisasi domain per-tenant (OPT-IN MURNI via pengaturan platform) —
 * menghapus "dualitas panel admin" HANYA bila owner memutuskannya secara
 * eksplisit per tenant, bukan otomatis untuk semua tenant ber-custom-domain.
 *
 * Kasus nyata: pesantren.asy-syifaa.com/admin dan asy-syifaa.pesantren.online/admin
 * adalah panel yang SAMA (dua record type='sistem' menunjuk tenant yang sama).
 * Dualitas ini merugikan: sesi login & localStorage (tema/dark mode) terpisah
 * per origin, URL yang dibagikan bercampur dua domain, landing terindeks ganda
 * (SEO), dan nanti PWA: service worker + manifest ter-scope per origin sehingga
 * pengguna bisa punya DUA instalasi PWA berbeda untuk satu pesantren.
 *
 * REVISI 2026-07-04 (insiden produksi): versi awal (2026-07-03) membuat aturan
 * ini OTOMATIS — subdomain platform di-redirect begitu tenant punya ≥1 custom
 * domain ber-type sama, TANPA perlu owner mengeset apa pun. Ini menyebabkan
 * tenant albakids (custom domain type='sistem' berlabel-telanjang, artefak
 * identifikasi stancl tanpa titik) ikut ter-redirect ke host TIDAK VALID
 * (`http://albakids/`) → situs klien DOWN. Owner memutuskan: kanonisasi
 * TIDAK BOLEH otomatis — harus selalu berupa PENGATURAN eksplisit di platform
 * (aksi "Domain Utama" per tenant), tidak pernah menyimpulkan sendiri dari
 * keberadaan custom domain.
 *
 * Aturan resolusi target kanonis (opt-in murni):
 * 1. Redirect HANYA terjadi bila salah satu domain tenant (type sama)
 *    ditandai is_canonical=true secara eksplisit oleh owner (aksi UI
 *    "Domain Utama" di /platform → Daftar Pesantren) → host lain (subdomain
 *    ATAUPUN custom domain lain) ber-type sama di-301 ke domain itu.
 * 2. Tanpa is_canonical yang diset: TIDAK ADA redirect sama sekali, apa pun
 *    kombinasi domain yang dimiliki tenant. Ini default untuk SEMUA tenant
 *    (demo, albakids, asy-syifaa sebelum diset ulang, dan tenant baru).
 * Owner mengaktifkan kanonisasi per tenant HANYA saat memang menghendakinya
 * (mis. asy-syifaa yang keluhan dualitas panelnya jadi alasan fitur ini ada).
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
     * Resolusi target kanonis untuk host saat ini — OPT-IN MURNI (lihat
     * docblock kelas). Tidak ada penyimpulan otomatis dari keberadaan custom
     * domain; hanya is_canonical eksplisit yang menghasilkan redirect.
     */
    protected function resolveTargetKanonis(Domain $saatIni, string $host): ?string
    {
        $eksplisit = Domain::query()
            ->where('tenant_id', $saatIni->tenant_id)
            ->where('is_canonical', true)
            ->where('type', $saatIni->type)
            ->where('domain', '!=', $host)
            // Jaring pengaman independen (insiden 2026-07-04): target kanonis
            // wajib berupa host valid (mengandung titik) — walau seharusnya
            // owner tidak akan menandai label-telanjang sebagai utama via UI,
            // pertahankan guard ini agar kelas kegagalan yang sama mustahil
            // terulang meski data domains tercemar record aneh.
            ->value('domain');

        if (is_string($eksplisit) && $eksplisit !== '' && str_contains($eksplisit, '.')) {
            return $eksplisit;
        }

        return null;
    }
}
