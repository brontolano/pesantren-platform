<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Registry kustomisasi per-tenant (white-label T3, Refs #343).
 *
 * Satu pintu untuk "tenant X memakai komponen/perilaku Y" — menggantikan cabang
 * `if AlbaKidsTenant::isActive()` yang hardcoded di controller/provider secara
 * bertahap. Sumber data: config/tenant-customization.php (bisa dievolusikan ke
 * Setting per-tenant nanti tanpa mengubah pemanggil).
 *
 * Resolusi tenant meniru AlbaKidsTenant::isActive() agar perilaku identik:
 * 1) tenant key dari tenancy aktif (tenant()->getTenantKey()),
 * 2) fallback host request penuh (alias 'hosts' di config) — untuk jalur di
 *    mana tenancy belum/tidak terinisialisasi tetapi host sudah spesifik.
 */
class TenantCustomizationRegistry
{
    /** Komponen halaman landing Inertia untuk tenant saat ini. */
    public static function landingComponent(): string
    {
        return (string) static::value('landing_component');
    }

    /**
     * Profil panel Filament tenant saat ini ('default' | 'albakids_ringkas').
     * Dibaca TenantPanelProvider untuk memilih set resources/pages/widgets —
     * menggantikan cabang AlbaKidsTenant::isActive() inline (tahap 3 #343).
     */
    public static function panelProfile(): string
    {
        return static::stringValue('panel_profile') ?? 'default';
    }

    /**
     * Flag fitur kustomisasi untuk tenant saat ini (mis. 'ppdb_custom_flow').
     * Resource bertanya "apakah alur X aktif?", BUKAN "apakah ini albakids?".
     */
    public static function feature(string $key, bool $default = false): bool
    {
        $value = static::value($key);

        return is_bool($value) ? $value : $default;
    }

    /**
     * Label override untuk tenant saat ini. $key = kunci di peta 'labels'
     * (mis. 'ppdb.calon_santri.navigation'); fallback $default bila tenant
     * tidak meng-override label tersebut.
     */
    public static function label(string $key, string $default): string
    {
        $labels = static::value('labels');

        if (is_array($labels) && isset($labels[$key]) && is_string($labels[$key])) {
            return $labels[$key];
        }

        return $default;
    }

    /** Nilai string opsional (scope query dsb.); null/'' → null. */
    public static function stringValue(string $key): ?string
    {
        $value = static::value($key);

        return is_string($value) && $value !== '' ? $value : null;
    }

    /** Ambil nilai kustomisasi $key untuk tenant saat ini, fallback ke default. */
    public static function value(string $key): mixed
    {
        $entry = static::currentEntry();

        if ($entry !== null && array_key_exists($key, $entry)) {
            return $entry[$key];
        }

        return config("tenant-customization.default.{$key}");
    }

    /** Entri config tenant saat ini (by tenant key, lalu by host), atau null. */
    protected static function currentEntry(): ?array
    {
        /** @var array<string, array> $tenants */
        $tenants = (array) config('tenant-customization.tenants', []);

        $tenantKey = static::currentTenantKey();

        if ($tenantKey !== null && isset($tenants[$tenantKey])) {
            return $tenants[$tenantKey];
        }

        $host = (string) request()?->getHost();

        if ($host !== '') {
            foreach ($tenants as $entry) {
                if (in_array($host, (array) ($entry['hosts'] ?? []), true)) {
                    return $entry;
                }
            }
        }

        return null;
    }

    protected static function currentTenantKey(): ?string
    {
        try {
            $key = tenant()?->getTenantKey();

            return $key !== null && $key !== '' ? (string) $key : null;
        } catch (\Throwable) {
            return null;
        }
    }
}
