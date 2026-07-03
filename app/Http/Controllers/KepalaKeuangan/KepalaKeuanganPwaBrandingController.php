<?php

declare(strict_types=1);

namespace App\Http\Controllers\KepalaKeuangan;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use App\Services\KepalaKeuangan\KepalaKeuanganBrandingService;
use App\Support\Pwa\SelfDestructSw;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Str;

/**
 * PWA assets (manifest, ikon, service worker) untuk Kepala Keuangan.
 * Pola PortalGuruPwaController dengan scope /keuangan/.
 */
class KepalaKeuanganPwaBrandingController extends Controller
{
    public function __construct(
        private readonly KepalaKeuanganBrandingService $brandingService,
    ) {}

    public function manifest(): JsonResponse
    {
        $branding = $this->brandingService->resolve();

        return response()->json([
            'id' => '/keuangan',
            'name' => $branding['name'].' · Keuangan',
            'short_name' => 'Keuangan',
            'description' => $branding['description'],
            'start_url' => '/keuangan',
            'scope' => '/keuangan/',
            'display' => 'standalone',
            'orientation' => 'portrait',
            'background_color' => $branding['backgroundColor'],
            'theme_color' => $branding['primaryColor'],
            'icons' => [
                [
                    'src' => $branding['icon192Url'],
                    'sizes' => '192x192',
                    'type' => 'image/svg+xml',
                    'purpose' => 'any maskable',
                ],
                [
                    'src' => $branding['icon512Url'],
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
        $branding = $this->brandingService->resolve();
        $size = (int) $request->query('size', 192);
        $size = in_array($size, [192, 512], true) ? $size : 192;
        $initials = $this->resolveInitials($branding['name']);
        $fontSize = $size === 512 ? 184 : 70;
        $escapedName = e($branding['name']);
        $escapedInitials = e($initials);
        $primary = e($branding['primaryColor']);
        $secondary = e($branding['secondaryColor']);
        $corner = $size === 512 ? 132 : 48;
        $circleOffset = $size === 512 ? 108 : 42;
        $circleRadius = $size === 512 ? 104 : 38;

        $svg = <<<SVG
<svg xmlns="http://www.w3.org/2000/svg" width="{$size}" height="{$size}" viewBox="0 0 {$size} {$size}" role="img" aria-label="{$escapedName}">
  <defs>
    <linearGradient id="keuGradient" x1="0%" y1="0%" x2="100%" y2="100%">
      <stop offset="0%" stop-color="{$primary}" />
      <stop offset="100%" stop-color="{$secondary}" />
    </linearGradient>
  </defs>
  <rect width="{$size}" height="{$size}" rx="{$corner}" fill="url(#keuGradient)" />
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

    public function serviceWorker(): Response
    {
        // F5 (sunset instalasi lama): flag ON → SW /keuangan serve self-destruct.
        // Flag default OFF di semua tenant — dormant sampai owner mengaktifkan
        // setelah F1 stabil di produksi (lihat plan F5).
        if ((bool) Setting::getTenantValue('pwa.legacy_sw_selfdestruct', false)) {
            return response(
                SelfDestructSw::script('keuangan-shell-'),
                200,
                SelfDestructSw::headers('/keuangan/'),
            );
        }

        $branding = $this->brandingService->resolve();
        $versionSeed = implode('|', [
            $branding['tenantId'],
            $branding['name'],
            $branding['primaryColor'],
            $branding['secondaryColor'],
            $branding['description'],
        ]);

        $cacheName = 'keuangan-shell-'.$branding['tenantId'].'-'.substr(sha1($versionSeed), 0, 12);
        $shellUrls = [
            '/keuangan/manifest.json',
            '/keuangan/icon.svg?size=192',
            '/keuangan/icon.svg?size=512',
        ];

        $script = <<<'JS'
const CACHE_NAME = %s;
const SHELL_URLS = %s;

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
                .filter((key) => key.startsWith('keuangan-shell-') && key !== CACHE_NAME)
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

    const isShellUrl = SHELL_URLS.includes(url.pathname + url.search);

    if (!isShellUrl) {
        return;
    }

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
});
JS;

        return response(sprintf(
            $script,
            json_encode($cacheName, JSON_THROW_ON_ERROR),
            json_encode($shellUrls, JSON_THROW_ON_ERROR),
        ), 200, [
            'Content-Type' => 'application/javascript; charset=UTF-8',
            'Cache-Control' => 'no-cache, must-revalidate',
            'Service-Worker-Allowed' => '/keuangan/',
        ]);
    }

    private function resolveInitials(string $name): string
    {
        $parts = collect(preg_split('/\s+/', trim($name)) ?: [])
            ->filter()
            ->take(2)
            ->map(fn (string $part): string => Str::upper(Str::substr($part, 0, 1)));

        return $parts->isNotEmpty()
            ? $parts->implode('')
            : 'KU';
    }
}
