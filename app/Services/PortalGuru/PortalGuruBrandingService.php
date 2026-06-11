<?php

declare(strict_types=1);

namespace App\Services\PortalGuru;

use App\Models\Setting;
use Illuminate\Support\Str;

/**
 * F1-PWA — branding tenant untuk PWA Guru (/guru).
 * Salinan pola PortalWaliBrandingService dengan scope /guru/.
 */
class PortalGuruBrandingService
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
     *     icon512Url: string
     * }
     */
    public function resolve(): array
    {
        $tenant = tenant();
        $name = Setting::getTenantStringValue('branding_name', $tenant?->getAttribute('name') ?? 'Pesantren');
        $tagline = Setting::getTenantStringValue('branding_tagline', 'PWA Guru');

        return [
            'tenantId' => (string) ($tenant?->getTenantKey() ?? 'tenant'),
            'name' => $name,
            'shortName' => (string) Str::of($name)->limit(12, ''),
            'tagline' => $tagline,
            'description' => sprintf(
                'Catat presensi kelas, setoran hafalan, dan jadwal mengajar %s langsung dari HP.',
                $name
            ),
            'primaryColor' => Setting::getTenantStringValue('branding_primary_color', '#0f172a'),
            'secondaryColor' => Setting::getTenantStringValue('branding_secondary_color', '#d97706'),
            'backgroundColor' => '#fffdf9',
            'logoUrl' => $this->resolveAssetUrl(Setting::getTenantStringValue('branding_logo', null)),
            'faviconUrl' => $this->resolveAssetUrl(Setting::getTenantStringValue('branding_favicon', null)),
            'manifestUrl' => '/guru/manifest.json',
            'serviceWorkerUrl' => '/guru/sw.js',
            'icon192Url' => url('/guru/icon.svg?size=192'),
            'icon512Url' => url('/guru/icon.svg?size=512'),
        ];
    }

    private function resolveAssetUrl(?string $value): ?string
    {
        if ($value === null || trim($value) === '') {
            return null;
        }

        if (str_starts_with($value, 'http://') || str_starts_with($value, 'https://') || str_starts_with($value, '/')) {
            return $value;
        }

        // Serve via /_tenant-storage — Storage::url 404 di Docker (pola baku).
        return url('/_tenant-storage/'.ltrim($value, '/'));
    }
}
