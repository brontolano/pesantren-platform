<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\Setting;

/**
 * Helper terpusat untuk membaca branding tenant aktif (logo, nama, warna,
 * koordinat jadwal sholat). Menghindari duplikasi logika resolve URL logo
 * yang sebelumnya tersebar di beberapa service/blade.
 *
 * Semua getter aman dipanggil tanpa tenant aktif (mengembalikan default).
 */
final class TenantBranding
{
    /** Koordinat default: Pondok Pesantren Asy-Syifaa, Padalarang, Bandung Barat. */
    private const DEFAULT_LAT = -6.85;

    private const DEFAULT_LNG = 107.49;

    private const DEFAULT_TZ = 7.0; // WIB

    /**
     * URL logo brand tenant yang sudah diunggah, atau null bila belum ada.
     * File di disk 'public' tenant di-serve via route /_tenant-storage/{path}
     * (Storage::url() menghasilkan path 404 di Docker + stancl/tenancy).
     */
    public static function logoUrl(): ?string
    {
        try {
            $path = Setting::getTenantStringValue('branding_logo', null);
        } catch (\Throwable) {
            return null;
        }

        if ($path === null || trim($path) === '') {
            return null;
        }

        if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://') || str_starts_with($path, '/')) {
            return $path;
        }

        return url('/_tenant-storage/'.ltrim($path, '/'));
    }

    /**
     * URL favicon tenant (kunci 'branding_favicon', file 'favicon_path' —
     * Setting::getTenantStringValue sudah paham kandidat itu), atau null bila
     * belum diunggah. Pola serve sama dengan logo: /_tenant-storage
     * (Storage::url() 404 di Docker + stancl/tenancy).
     */
    public static function faviconUrl(): ?string
    {
        try {
            $path = Setting::getTenantStringValue('branding_favicon', null);
        } catch (\Throwable) {
            return null;
        }

        if ($path === null || trim($path) === '') {
            return null;
        }

        if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://') || str_starts_with($path, '/')) {
            return $path;
        }

        return url('/_tenant-storage/'.ltrim($path, '/'));
    }

    /**
     * URL foto/figur hero untuk halaman login (mis. foto pengasuh), atau null.
     * Sumber: Setting `branding_hero_image` — boleh URL absolut, path absolut
     * (aset statis di public/), atau path disk tenant (di-serve /_tenant-storage).
     */
    public static function heroImageUrl(): ?string
    {
        try {
            $path = Setting::getTenantStringValue('branding_hero_image', null);
        } catch (\Throwable) {
            return null;
        }

        if ($path === null || trim($path) === '') {
            return null;
        }

        if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://') || str_starts_with($path, '/')) {
            return $path;
        }

        return url('/_tenant-storage/'.ltrim($path, '/'));
    }

    public static function name(string $default = 'Pesantren'): string
    {
        try {
            $name = Setting::getTenantStringValue('branding_name');
            if (is_string($name) && trim($name) !== '') {
                return trim($name);
            }
        } catch (\Throwable) {
            // fallthrough
        }

        return (string) (tenant()?->getAttribute('name') ?? $default) ?: $default;
    }

    public static function primaryColor(string $default = '#14532d'): string
    {
        try {
            return Setting::getTenantStringValue('branding_primary_color', $default) ?: $default;
        } catch (\Throwable) {
            return $default;
        }
    }

    public static function latitude(): float
    {
        return self::floatSetting('branding_latitude', self::DEFAULT_LAT);
    }

    public static function longitude(): float
    {
        return self::floatSetting('branding_longitude', self::DEFAULT_LNG);
    }

    public static function timezoneOffset(): float
    {
        return self::DEFAULT_TZ;
    }

    private static function floatSetting(string $key, float $default): float
    {
        try {
            $raw = Setting::getTenantStringValue($key, null);
        } catch (\Throwable) {
            return $default;
        }

        if ($raw === null || trim($raw) === '') {
            return $default;
        }

        $val = (float) str_replace(',', '.', trim($raw));

        return $val !== 0.0 ? $val : $default;
    }
}
