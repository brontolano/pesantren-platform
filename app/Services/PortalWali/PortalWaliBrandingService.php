<?php

declare(strict_types=1);

namespace App\Services\PortalWali;

use App\Models\Setting;
use Illuminate\Support\Str;

class PortalWaliBrandingService
{
    /**
     * @return array{
     *     tenantId: string,
     *     name: string,
     *     shortName: string,
     *     tagline: string,
     *     description: string,
     *     primaryColor: string,
     *     secondaryColor: string,
     *     backgroundColor: string,
     *     logoUrl: ?string,
     *     faviconUrl: ?string,
     *     manifestUrl: string,
     *     serviceWorkerUrl: string,
     *     icon192Url: string,
     *     icon512Url: string,
     *     screenshotWideUrl: string,
     *     screenshotNarrowUrl: string
     * }
     */
    public function resolve(): array
    {
        $tenant = tenant();
        $name = Setting::getTenantStringValue('branding_name', $tenant?->getAttribute('name') ?? 'Pesantren');
        $tagline = Setting::getTenantStringValue('branding_tagline', 'Portal Wali Santri');

        return [
            'tenantId' => (string) ($tenant?->getTenantKey() ?? 'tenant'),
            'name' => $name,
            'shortName' => (string) Str::of($name)->limit(12, ''),
            'tagline' => $tagline,
            'description' => sprintf(
                'Pantau tagihan, tabungan, dan status pembayaran wali %s langsung dari HP.',
                $name
            ),
            'primaryColor' => Setting::getTenantStringValue('branding_primary_color', '#0f172a'),
            'secondaryColor' => Setting::getTenantStringValue('branding_secondary_color', '#d97706'),
            'backgroundColor' => '#fffdf9',
            'logoUrl' => $this->resolveAssetUrl(Setting::getTenantStringValue('branding_logo', null)),
            'faviconUrl' => $this->resolveAssetUrl(Setting::getTenantStringValue('branding_favicon', null)),
            'manifestUrl' => '/wali/manifest.json',
            'serviceWorkerUrl' => '/wali/sw.js',
            'icon192Url' => url('/wali/icon.svg?size=192'),
            'icon512Url' => url('/wali/icon.svg?size=512'),
            'screenshotWideUrl' => url('/wali/screenshot.svg?viewport=wide'),
            'screenshotNarrowUrl' => url('/wali/screenshot.svg?viewport=narrow'),
        ];
    }

    public function resolveIconFallbackUrl(): string
    {
        $branding = $this->resolve();

        return $branding['faviconUrl']
            ?? $branding['logoUrl']
            ?? $branding['icon192Url'];
    }

    private function resolveAssetUrl(?string $value): ?string
    {
        if ($value === null || trim($value) === '') {
            return null;
        }

        // URL absolut / path absolut — kembalikan apa adanya
        if (str_starts_with($value, 'http://') || str_starts_with($value, 'https://') || str_starts_with($value, '/')) {
            return $value;
        }

        // File di disk 'public' tenant: serve via route /_tenant-storage/{path}.
        // Storage::disk('public')->url() TIDAK dipakai karena menghasilkan
        // APP_URL/storage/{path} yang 404 di Docker (symlink hanya non-tenant).
        return url('/_tenant-storage/' . ltrim($value, '/'));
    }
}
