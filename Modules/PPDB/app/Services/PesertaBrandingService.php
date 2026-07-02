<?php

namespace Modules\PPDB\Services;

use App\Models\Setting;
use Illuminate\Support\Str;

class PesertaBrandingService
{
    /**
     * @return array{
     *     tenantId: string,
     *     name: string,
     *     shortName: string,
     *     tagline: string,
     *     primaryColor: string,
     *     secondaryColor: string,
     *     backgroundColor: string,
     *     logoUrl: ?string,
     *     faviconUrl: ?string,
     *     icon192Url: string,
     *     icon512Url: string
     * }
     */
    public function resolve(): array
    {
        $tenant = tenant();
        $name = Setting::getTenantStringValue('branding_name', $tenant?->getAttribute('name') ?? 'Pesantren');

        return [
            'tenantId' => (string) ($tenant?->getTenantKey() ?? 'tenant'),
            'name' => $name,
            'shortName' => (string) Str::of($name)->limit(12, ''),
            'tagline' => Setting::getTenantStringValue('branding_tagline', 'Portal Penerimaan Santri Baru'),
            'primaryColor' => Setting::getTenantStringValue('branding_primary_color', '#14532d'),
            'secondaryColor' => Setting::getTenantStringValue('branding_secondary_color', '#c2410c'),
            'backgroundColor' => '#f5f5f4',
            'logoUrl' => $this->resolveAssetUrl(Setting::getTenantStringValue('branding_logo', null)),
            'faviconUrl' => $this->resolveAssetUrl(Setting::getTenantStringValue('branding_favicon', null)),
            'icon192Url' => url('/spmb/icon.svg?size=192'),
            'icon512Url' => url('/spmb/icon.svg?size=512'),
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
        return url('/_tenant-storage/'.ltrim($value, '/'));
    }
}
