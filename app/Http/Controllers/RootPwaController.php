<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Setting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Str;

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
