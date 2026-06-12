<?php

declare(strict_types=1);

namespace App\Services\KepalaKeuangan;

use App\Models\Setting;
use Illuminate\Support\Str;

/**
 * Branding tenant untuk PWA Kepala Keuangan (/keuangan).
 * Pola PortalGuruBrandingService dengan scope /keuangan/ dan design tokens keuangan.
 */
class KepalaKeuanganBrandingService
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
        $tagline = Setting::getTenantStringValue('branding_tagline', 'Keuangan');

        return [
            'tenantId' => (string) ($tenant?->getTenantKey() ?? 'tenant'),
            'name' => $name,
            'shortName' => (string) Str::of($name)->limit(12, ''),
            'tagline' => $tagline,
            'description' => sprintf(
                'Kelola keuangan %s: tagihan, kas, tabungan, pengeluaran, ledger, dan laporan.',
                $name
            ),
            'primaryColor' => '#0A5C36',
            'secondaryColor' => '#C9A24A',
            'backgroundColor' => '#FDFBF7',
            'logoUrl' => $this->resolveAssetUrl(Setting::getTenantStringValue('branding_logo', null)),
            'faviconUrl' => $this->resolveAssetUrl(Setting::getTenantStringValue('branding_favicon', null)),
            'manifestUrl' => '/keuangan/manifest.json',
            'serviceWorkerUrl' => '/keuangan/sw.js',
            'icon192Url' => url('/keuangan/icon.svg?size=192'),
            'icon512Url' => url('/keuangan/icon.svg?size=512'),
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

        return url('/_tenant-storage/'.ltrim($value, '/'));
    }
}
