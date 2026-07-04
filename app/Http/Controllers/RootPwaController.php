<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Setting;
use App\Support\Pwa\SelfDestructSw;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Illuminate\View\View;

/**
 * Manifest + ikon PWA scope ROOT "/" untuk aplikasi 1-pintu per pesantren
 * (Beranda publik / Jemaah). Melengkapi manifest per-portal (/wali, /guru,
 * /spmb, /keuangan) yang scope-nya terbatas: ini menjadikan SELURUH aplikasi
 * (mulai dari "/") installable + fondasi TWA root (Fase 4, startUrl=/).
 *
 * Manifest & ikon bersifat PASIF (JSON/SVG) — additive, tak meng-intercept
 * fetch, tak bisa merusak app live. Service worker root (Fase 3) disusun
 * TERPISAH dan butuh uji-perangkat sebelum deploy.
 */
class RootPwaController extends Controller
{
    public function manifest(): JsonResponse
    {
        $b = $this->branding();

        return response()->json([
            'id' => '/',
            'name' => $b['name'],
            'short_name' => $b['shortName'],
            'description' => $b['description'],
            'lang' => 'id',
            'dir' => 'ltr',
            'start_url' => '/',
            'scope' => '/',
            'display' => 'standalone',
            'display_override' => ['standalone', 'minimal-ui'],
            'orientation' => 'portrait',
            'background_color' => $b['backgroundColor'],
            'theme_color' => $b['primaryColor'],
            'categories' => ['education'],
            'icons' => [
                [
                    'src' => $b['icon192Url'],
                    'sizes' => '192x192',
                    'type' => 'image/svg+xml',
                    'purpose' => 'any maskable',
                ],
                [
                    'src' => $b['icon512Url'],
                    'sizes' => '512x512',
                    'type' => 'image/svg+xml',
                    'purpose' => 'any maskable',
                ],
            ],
        ], 200, [
            'Content-Type' => 'application/manifest+json',
            'Cache-Control' => 'no-cache, must-revalidate',
        ]);
    }

    public function icon(Request $request): Response
    {
        $b = $this->branding();
        $size = (int) $request->query('size', 192);
        $size = in_array($size, [192, 512], true) ? $size : 192;
        $initials = $this->resolveInitials($b['name']);
        $fontSize = $size === 512 ? 184 : 70;
        $escapedName = e($b['name']);
        $escapedInitials = e($initials);
        // Warna sudah divalidasi hex di branding() — aman di-injeksi ke SVG.
        $primary = $b['primaryColor'];
        $secondary = $b['secondaryColor'];
        $corner = $size === 512 ? 132 : 48;
        $circleOffset = $size === 512 ? 108 : 42;
        $circleRadius = $size === 512 ? 104 : 38;

        $svg = <<<SVG
        <svg xmlns="http://www.w3.org/2000/svg" width="{$size}" height="{$size}" viewBox="0 0 {$size} {$size}" role="img" aria-label="{$escapedName}">
          <defs>
            <linearGradient id="rootGradient" x1="0%" y1="0%" x2="100%" y2="100%">
              <stop offset="0%" stop-color="{$primary}" />
              <stop offset="100%" stop-color="{$secondary}" />
            </linearGradient>
          </defs>
          <rect width="{$size}" height="{$size}" rx="{$corner}" fill="url(#rootGradient)" />
          <circle cx="{$circleOffset}" cy="{$circleOffset}" r="{$circleRadius}" fill="rgba(255,255,255,0.12)" />
          <text
            x="50%"
            y="54%"
            text-anchor="middle"
            dominant-baseline="middle"
            font-family="Arial, sans-serif"
            font-size="{$fontSize}"
            font-weight="700"
            fill="#ffffff"
            letter-spacing="0.04em"
          >{$escapedInitials}</text>
        </svg>
        SVG;

        return response($svg, 200, [
            'Content-Type' => 'image/svg+xml',
            'Cache-Control' => 'public, max-age=3600',
        ]);
    }

    /**
     * Halaman offline (F1) — precache-able, blade statis (bukan Inertia/Vue
     * berat), branding tenant. Ditampilkan SW saat navigasi gagal karena
     * jaringan mati.
     */
    public function offline(): View
    {
        return view('tenant.pwa-offline', $this->branding());
    }

    /**
     * Service worker scope ROOT "/" (Fase 3). Pola ALLOW-LIST aman a la PWA Guru:
     * HANYA shell statis publik (manifest + ikon) yang di-intercept & di-cache.
     * SEMUA permintaan lain (Beranda dinamis, /admin, /platform, /livewire,
     * halaman ber-auth, non-GET, lintas-origin) DILEWATKAN ke jaringan apa adanya
     * — TIDAK PERNAH masuk Cache Storage. Ini mencegah data ber-auth/keuangan
     * basi atau halaman admin ter-cache.
     *
     * KEAMANAN deploy: registrasi SW di app.blade.php DIGERBANG flag
     * 'pwa.root_sw_enabled' (default OFF) → deploy AMAN (SW tak aktif sampai
     * owner mengaktifkan SETELAH uji-perangkat). SW yang salah bisa membuat app
     * blank/uninstallable, maka WAJIB uji-perangkat sebelum flag dinyalakan.
     */
    public function serviceWorker(): Response
    {
        // KILL-SWITCH (F0): flag OFF → sajikan skrip self-destruct. Tanpa ini,
        // SW yang terlanjur terpasang saat flag pernah ON akan hidup selamanya
        // di perangkat walau owner mematikan flag.
        if (! (bool) Setting::getTenantValue('pwa.root_sw_enabled', false)) {
            return response(
                SelfDestructSw::script('root-shell-'),
                200,
                SelfDestructSw::headers('/'),
            );
        }

        $b = $this->branding();
        // F1: hash manifest build Vite masuk versionSeed → cache auto-bust tiap
        // deploy (aset /build/* ber-hash berubah nama, tapi precache list &
        // strategi SW sendiri bisa berubah tanpa itu — manifest.json adalah
        // proxy murah utk "build baru telah di-deploy").
        $buildManifestPath = public_path('build/manifest.json');
        $buildHash = File::exists($buildManifestPath)
            ? substr(sha1((string) File::get($buildManifestPath)), 0, 10)
            : 'nobuild';
        $versionSeed = implode('|', [$b['tenantId'], $b['name'], $b['primaryColor'], $b['secondaryColor'], $buildHash]);
        $cacheName = 'root-shell-'.$b['tenantId'].'-'.substr(sha1($versionSeed), 0, 12);
        $shellUrls = [
            '/manifest.webmanifest',
            '/icon.svg?size=192',
            '/icon.svg?size=512',
            '/offline',
        ];
        // F1: deny-list eksplisit — scope portal ber-SW-sendiri + jalur backend
        // TIDAK PERNAH ditangani SW root (defense-in-depth; longest-scope-wins
        // browser sudah menjamin ini, tapi kegagalan itu mahal — cegah dobel).
        $denyPrefixes = [
            '/admin',
            '/platform',
            '/livewire',
            '/api',
            '/filament',
            '/guru',
            '/wali',
            '/spmb',
            '/keuangan',
        ];

        $script = <<<'JS'
        const CACHE_NAME = %s;
        const SHELL_URLS = %s;
        const OFFLINE_URL = '/offline';
        const DENY_PREFIXES = %s;
        const BUILD_ASSET_RE = /^\/build\//;

        function isDenied(pathname) {
            return DENY_PREFIXES.some((prefix) => pathname === prefix || pathname.startsWith(prefix + '/'));
        }

        self.addEventListener('install', (event) => {
            event.waitUntil((async () => {
                const cache = await caches.open(CACHE_NAME);
                await cache.addAll(SHELL_URLS.map((url) => new Request(url, { cache: 'reload' })));
                await self.skipWaiting();
            })());
        });

        self.addEventListener('activate', (event) => {
            event.waitUntil((async () => {
                const keys = await caches.keys();
                await Promise.all(
                    keys
                        .filter((key) => key.startsWith('root-shell-') && key !== CACHE_NAME)
                        .map((key) => caches.delete(key)),
                );
                await self.clients.claim();
            })());
        });

        self.addEventListener('fetch', (event) => {
            if (event.request.method !== 'GET') {
                return;
            }

            const url = new URL(event.request.url);

            if (url.origin !== self.location.origin) {
                return;
            }

            // Deny-list eksplisit: jangan sentuh sama sekali — biar network murni.
            if (isDenied(url.pathname)) {
                return;
            }

            const isShellUrl = SHELL_URLS.includes(url.pathname + url.search);

            // Lapis 1 — shell (manifest/ikon/offline): network-then-cache, fallback cache.
            if (isShellUrl) {
                event.respondWith((async () => {
                    const cache = await caches.open(CACHE_NAME);
                    const cached = await cache.match(event.request);

                    try {
                        const response = await fetch(event.request);

                        if (response.ok) {
                            await cache.put(event.request, response.clone());
                        }

                        return response;
                    } catch (error) {
                        if (cached) {
                            return cached;
                        }

                        return new Response('', { status: 503, statusText: 'Offline' });
                    }
                })());

                return;
            }

            // Lapis 2 — aset build ber-hash (immutable): cache-first.
            if (BUILD_ASSET_RE.test(url.pathname)) {
                event.respondWith((async () => {
                    const cache = await caches.open(CACHE_NAME);
                    const cached = await cache.match(event.request);

                    if (cached) {
                        return cached;
                    }

                    try {
                        const response = await fetch(event.request);

                        if (response.ok) {
                            await cache.put(event.request, response.clone());
                        }

                        return response;
                    } catch (error) {
                        return new Response('', { status: 503, statusText: 'Offline' });
                    }
                })());

                return;
            }

            // Lapis 3 — navigasi HTML (halaman dinamis, mis. beranda publik):
            // network-first, fallback ke halaman /offline (TIDAK cache HTML
            // ber-auth apa pun — pelajaran insiden SW /wali di F0).
            const acceptsHtml = (event.request.headers.get('accept') || '').includes('text/html');

            if (event.request.mode === 'navigate' || acceptsHtml) {
                event.respondWith((async () => {
                    try {
                        return await fetch(event.request);
                    } catch (error) {
                        const cache = await caches.open(CACHE_NAME);
                        const offline = await cache.match(OFFLINE_URL);

                        return offline || new Response('', { status: 503, statusText: 'Offline' });
                    }
                })());
            }
        });

        // F6 — Web Push (VAPID). Listener INDEPENDEN dari logika fetch/install/
        // activate di atas — tak menyentuh cache shell. Payload JSON opsional
        // {title, body, url}; gagal parse → fallback aman.
        self.addEventListener('push', (event) => {
            let data = {};
            try {
                data = event.data ? event.data.json() : {};
            } catch (error) {
                data = { title: 'Notifikasi', body: event.data ? event.data.text() : '' };
            }

            const title = data.title || 'Notifikasi';
            const options = {
                body: data.body || '',
                data: { url: data.url || '/' },
            };

            event.waitUntil(self.registration.showNotification(title, options));
        });

        self.addEventListener('notificationclick', (event) => {
            event.notification.close();
            const targetUrl = (event.notification.data && event.notification.data.url) || '/';

            event.waitUntil((async () => {
                const clientsList = await self.clients.matchAll({ type: 'window', includeUncontrolled: true });
                for (const client of clientsList) {
                    if (client.url.includes(targetUrl) && 'focus' in client) {
                        return client.focus();
                    }
                }
                if (self.clients.openWindow) {
                    return self.clients.openWindow(targetUrl);
                }
            })());
        });
        JS;

        return response(sprintf(
            $script,
            json_encode($cacheName, JSON_THROW_ON_ERROR),
            json_encode($shellUrls, JSON_THROW_ON_ERROR),
            json_encode($denyPrefixes, JSON_THROW_ON_ERROR),
        ), 200, [
            'Content-Type' => 'application/javascript; charset=UTF-8',
            'Cache-Control' => 'no-cache, must-revalidate',
            'Service-Worker-Allowed' => '/',
        ]);
    }

    /**
     * @return array{tenantId:string,name:string,shortName:string,description:string,primaryColor:string,secondaryColor:string,backgroundColor:string,icon192Url:string,icon512Url:string}
     */
    private function branding(): array
    {
        $tenant = tenant();
        $name = Setting::getTenantStringValue('branding_name', $tenant?->getAttribute('name') ?? 'Pesantren');

        return [
            'tenantId' => (string) ($tenant?->getTenantKey() ?? 'tenant'),
            'name' => $name,
            'shortName' => (string) Str::of($name)->limit(12, ''),
            'description' => Setting::getTenantStringValue(
                'branding_tagline',
                'Aplikasi resmi '.$name.' — informasi, kegiatan, dan layanan pesantren.',
            ),
            'primaryColor' => $this->safeHex(Setting::getTenantStringValue('branding_primary_color', null), '#0f5132'),
            'secondaryColor' => $this->safeHex(Setting::getTenantStringValue('branding_secondary_color', null), '#d97706'),
            'backgroundColor' => '#fffdf9',
            'icon192Url' => url('/icon.svg?size=192'),
            'icon512Url' => url('/icon.svg?size=512'),
        ];
    }

    /** Validasi warna hex (#rgb / #rrggbb) sebelum injeksi SVG — cegah gradient rusak / injeksi. */
    private function safeHex(?string $value, string $fallback): string
    {
        $v = trim((string) $value);

        return preg_match('/^#(?:[0-9a-fA-F]{3}|[0-9a-fA-F]{6})$/', $v) === 1 ? $v : $fallback;
    }

    private function resolveInitials(string $name): string
    {
        $parts = collect(preg_split('/\s+/', trim($name)) ?: [])
            ->filter()
            ->take(2)
            ->map(fn (string $part): string => Str::upper(Str::substr($part, 0, 1)));

        return $parts->isNotEmpty() ? $parts->implode('') : 'PO';
    }
}
