<?php

declare(strict_types=1);

namespace App\Services\Jemaah;

use App\Models\Setting;
use Illuminate\Support\Str;

/**
 * Branding ringkas untuk halaman auth peran publik "jemaah" (1-aplikasi, Fase 2b).
 *
 * Sengaja TERPISAH dari PortalWaliBrandingService: tagline/deskripsi & (kelak)
 * URL PWA berbeda — kita tak ingin halaman jemaah menampilkan "Portal Wali"
 * atau menunjuk manifest /wali. Cukup identitas visual tenant untuk form login,
 * daftar, aktivasi, dan lupa-password jemaah.
 */
class JemaahBrandingService
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
     *     logoUrl: ?string
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
            'tagline' => Setting::getTenantStringValue('branding_tagline', 'Komunitas Jemaah'),
            'primaryColor' => Setting::getTenantStringValue('branding_primary_color', '#0f172a'),
            'secondaryColor' => Setting::getTenantStringValue('branding_secondary_color', '#d97706'),
            'backgroundColor' => '#fffdf9',
            'logoUrl' => $this->resolveAssetUrl(Setting::getTenantStringValue('branding_logo', null)),
        ];
    }

    private function resolveAssetUrl(?string $value): ?string
    {
        if ($value === null || trim($value) === '') {
            return null;
        }

        // URL/path absolut → apa adanya.
        if (str_starts_with($value, 'http://') || str_starts_with($value, 'https://') || str_starts_with($value, '/')) {
            return $value;
        }

        // File disk 'public' tenant → serve via /_tenant-storage (Storage::url 404 di Docker).
        return url('/_tenant-storage/'.ltrim($value, '/'));
    }
}
