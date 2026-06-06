<?php

declare(strict_types=1);

namespace App\Http\Controllers\PortalWali;

use App\Http\Controllers\Controller;
use App\Services\PortalWali\PortalWaliBrandingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Str;

class PortalWaliPwaController extends Controller
{
    public function __construct(
        private readonly PortalWaliBrandingService $brandingService,
    ) {
    }

    public function manifest(): JsonResponse
    {
        $branding = $this->brandingService->resolve();

        return response()->json([
            'id' => '/wali',
            'name' => $branding['name'].' · Portal Wali',
            'short_name' => $branding['shortName'],
            'description' => $branding['description'],
            'start_url' => '/wali',
            'scope' => '/wali/',
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
            'screenshots' => [
                [
                    'src' => $branding['screenshotWideUrl'],
                    'sizes' => '1280x720',
                    'type' => 'image/svg+xml',
                    'form_factor' => 'wide',
                    'label' => 'Beranda keuangan Portal Wali',
                ],
                [
                    'src' => $branding['screenshotNarrowUrl'],
                    'sizes' => '540x1200',
                    'type' => 'image/svg+xml',
                    'form_factor' => 'narrow',
                    'label' => 'Ringkasan tagihan dan tabungan santri',
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

        $svg = <<<SVG
<svg xmlns="http://www.w3.org/2000/svg" width="{$size}" height="{$size}" viewBox="0 0 {$size} {$size}" role="img" aria-label="{$escapedName}">
  <defs>
    <linearGradient id="waliGradient" x1="0%" y1="0%" x2="100%" y2="100%">
      <stop offset="0%" stop-color="{$primary}" />
      <stop offset="100%" stop-color="{$secondary}" />
    </linearGradient>
  </defs>
  <rect width="{$size}" height="{$size}" rx="{$this->resolveCornerRadius($size)}" fill="url(#waliGradient)" />
  <circle cx="{$this->resolveCircleOffset($size)}" cy="{$this->resolveCircleOffset($size)}" r="{$this->resolveCircleRadius($size)}" fill="rgba(255,255,255,0.12)" />
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

    public function screenshot(Request $request): Response
    {
        $branding = $this->brandingService->resolve();
        $viewport = $request->query('viewport', 'wide') === 'narrow' ? 'narrow' : 'wide';
        $width = $viewport === 'narrow' ? 540 : 1280;
        $height = $viewport === 'narrow' ? 1200 : 720;
        $frameWidth = $width - 64;
        $frameHeight = $height - 64;
        $heroWidth = $width - 128;
        $heroHeight = $viewport === 'narrow' ? 238 : 188;
        $portalLabelY = $viewport === 'narrow' ? 134 : 128;
        $brandTitleY = 182;
        $subtitleY = $viewport === 'narrow' ? 220 : 222;
        $firstCardY = $viewport === 'narrow' ? 286 : 300;
        $cardWidth = $viewport === 'narrow' ? 348 : 320;
        $cardHeight = $viewport === 'narrow' ? 136 : 120;
        $secondCardX = $viewport === 'narrow' ? 96 : 448;
        $secondCardY = $viewport === 'narrow' ? 442 : 300;
        $thirdCardX = $viewport === 'narrow' ? 96 : 800;
        $thirdCardY = $viewport === 'narrow' ? 598 : 300;
        $firstCardTitleY = $viewport === 'narrow' ? 338 : 346;
        $firstCardValueY = $viewport === 'narrow' ? 382 : 388;
        $valueFontSize = $viewport === 'narrow' ? 28 : 34;
        $secondCardTitleX = $viewport === 'narrow' ? 124 : 476;
        $secondCardTitleY = $viewport === 'narrow' ? 494 : 346;
        $secondCardValueX = $viewport === 'narrow' ? 124 : 476;
        $secondCardValueY = $viewport === 'narrow' ? 538 : 388;
        $thirdCardTitleX = $viewport === 'narrow' ? 124 : 828;
        $thirdCardTitleY = $viewport === 'narrow' ? 650 : 346;
        $thirdCardValueX = $viewport === 'narrow' ? 124 : 828;
        $thirdCardValueY = $viewport === 'narrow' ? 694 : 388;
        $buttonY = $viewport === 'narrow' ? 786 : 480;
        $buttonWidth = $viewport === 'narrow' ? 348 : 270;
        $buttonTextX = $viewport === 'narrow' ? 270 : 231;
        $buttonTextY = $viewport === 'narrow' ? 819 : 514;
        $escapedName = e($branding['name']);
        $primary = e($branding['primaryColor']);
        $secondary = e($branding['secondaryColor']);
        $titleSize = $viewport === 'narrow' ? 30 : 42;
        $subtitleSize = $viewport === 'narrow' ? 18 : 24;

        $svg = <<<SVG
<svg xmlns="http://www.w3.org/2000/svg" width="{$width}" height="{$height}" viewBox="0 0 {$width} {$height}" role="img" aria-label="{$escapedName} Portal Wali">
  <defs>
    <linearGradient id="waliScreenGradient" x1="0%" y1="0%" x2="100%" y2="100%">
      <stop offset="0%" stop-color="{$primary}" />
      <stop offset="100%" stop-color="{$secondary}" />
    </linearGradient>
  </defs>
  <rect width="{$width}" height="{$height}" fill="#f8f5ed" />
  <rect x="32" y="32" width="{$frameWidth}" height="{$frameHeight}" rx="32" fill="#fffdf9" stroke="#e2e8f0" />
  <rect x="64" y="64" width="{$heroWidth}" height="{$heroHeight}" rx="28" fill="url(#waliScreenGradient)" />
  <text x="96" y="{$portalLabelY}" font-family="Arial, sans-serif" font-size="18" font-weight="700" fill="#fde68a">Portal Wali</text>
  <text x="96" y="{$brandTitleY}" font-family="Georgia, serif" font-size="{$titleSize}" font-weight="700" fill="#ffffff">{$escapedName}</text>
  <text x="96" y="{$subtitleY}" font-family="Arial, sans-serif" font-size="{$subtitleSize}" fill="rgba(255,255,255,0.78)">Pantau tagihan, tabungan, dan status pembayaran santri.</text>
  <rect x="96" y="{$firstCardY}" width="{$cardWidth}" height="{$cardHeight}" rx="24" fill="#ffffff" stroke="#e2e8f0" />
  <rect x="{$secondCardX}" y="{$secondCardY}" width="{$cardWidth}" height="{$cardHeight}" rx="24" fill="#ffffff" stroke="#e2e8f0" />
  <rect x="{$thirdCardX}" y="{$thirdCardY}" width="{$cardWidth}" height="{$cardHeight}" rx="24" fill="#ffffff" stroke="#e2e8f0" />
  <text x="124" y="{$firstCardTitleY}" font-family="Arial, sans-serif" font-size="16" font-weight="700" fill="#0f172a">Total tunggakan</text>
  <text x="124" y="{$firstCardValueY}" font-family="Arial, sans-serif" font-size="{$valueFontSize}" font-weight="700" fill="#0f172a">Rp 1.250.000</text>
  <text x="{$secondCardTitleX}" y="{$secondCardTitleY}" font-family="Arial, sans-serif" font-size="16" font-weight="700" fill="#0f172a">Saldo tabungan</text>
  <text x="{$secondCardValueX}" y="{$secondCardValueY}" font-family="Arial, sans-serif" font-size="{$valueFontSize}" font-weight="700" fill="#0f172a">Rp 325.000</text>
  <text x="{$thirdCardTitleX}" y="{$thirdCardTitleY}" font-family="Arial, sans-serif" font-size="16" font-weight="700" fill="#0f172a">Status bayar</text>
  <text x="{$thirdCardValueX}" y="{$thirdCardValueY}" font-family="Arial, sans-serif" font-size="{$valueFontSize}" font-weight="700" fill="#0f172a">Menunggu verifikasi</text>
  <rect x="96" y="{$buttonY}" width="{$buttonWidth}" height="52" rx="26" fill="{$primary}" />
  <text x="{$buttonTextX}" y="{$buttonTextY}" text-anchor="middle" font-family="Arial, sans-serif" font-size="18" font-weight="700" fill="#ffffff">Bayar Sekarang</text>
</svg>
SVG;

        return response($svg, 200, [
            'Content-Type' => 'image/svg+xml',
            'Cache-Control' => 'public, max-age=3600',
        ]);
    }

    public function serviceWorker(): Response
    {
        $branding = $this->brandingService->resolve();
        $versionSeed = implode('|', [
            $branding['tenantId'],
            $branding['name'],
            $branding['primaryColor'],
            $branding['secondaryColor'],
            $branding['description'],
        ]);

        $cacheName = 'wali-shell-'.$branding['tenantId'].'-'.substr(sha1($versionSeed), 0, 12);
        $shellUrls = [
            '/wali',
            '/wali/manifest.json',
            '/wali/icon.svg?size=192',
            '/wali/icon.svg?size=512',
            '/wali/screenshot.svg?viewport=wide',
            '/wali/screenshot.svg?viewport=narrow',
        ];

        $script = <<<JS
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
                .filter((key) => key.startsWith('wali-shell-') && key !== CACHE_NAME)
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

    const isWaliPage = url.pathname === '/wali' || url.pathname.startsWith('/wali/');
    const isWaliAsset =
        url.pathname === '/wali/manifest.json' ||
        url.pathname === '/wali/icon.svg' ||
        url.pathname === '/wali/screenshot.svg';

    if (!isWaliPage && !isWaliAsset) {
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
            'Service-Worker-Allowed' => '/wali/',
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
            : 'PW';
    }

    private function resolveCornerRadius(int $size): int
    {
        return $size === 512 ? 132 : 48;
    }

    private function resolveCircleOffset(int $size): int
    {
        return $size === 512 ? 108 : 42;
    }

    private function resolveCircleRadius(int $size): int
    {
        return $size === 512 ? 104 : 38;
    }
}
