<?php

namespace App\Filament\Concerns;

/**
 * Gerbang fitur "website bawaan" (Website Builder, Profil Website, Pengaturan
 * Website). ATURAN: bila tenant memakai website CUSTOM/STATIS (mis. asy-syifaa.com),
 * menu-menu ini DISEMBUNYIKAN — karena memberi makan website templat bawaan yang
 * tidak dipakai. Dikontrol oleh atribut tenant `website_mode` ('builder' | 'custom').
 *
 * Default 'builder' (tenant tanpa flag tetap melihat menu seperti biasa).
 */
trait BuiltInWebsiteGate
{
    protected static function builtInWebsiteEnabled(): bool
    {
        $tenant = tenancy()->initialized ? tenant() : null;

        return ($tenant?->website_mode ?? 'builder') !== 'custom';
    }
}
