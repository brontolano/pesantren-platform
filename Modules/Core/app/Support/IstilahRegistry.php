<?php

namespace Modules\Core\Support;

use App\Models\Setting;

/**
 * Registry istilah pesantren — daftar kunci bawaan + resolusi label per tenant.
 *
 * Tenant dapat menimpa label via Setting `label.istilah` (map kunci => label).
 * Parsing TOLERAN (pelajaran WP-02): value boleh array, string JSON, atau
 * rusak sama sekali — selalu fallback aman ke label bawaan.
 */
class IstilahRegistry
{
    public const SETTING_KEY = 'label.istilah';

    /** Daftar kunci bawaan. Tambah istilah = tambah satu baris di sini. */
    public const DEFAULTS = [
        'musyrif' => 'Musyrif',
        'mustahiq' => 'Wali Kelas Diniyah',
        'takzir' => "Ta'zir",
        'halaqah' => 'Halaqah',
        'bisyaroh' => 'Bisyaroh',
        'santri' => 'Santri',
        'ustadz' => 'Ustadz',
        'lurah_pondok' => 'Lurah Pondok',
        'qism' => 'Qism',
        'sorogan' => 'Sorogan',
        'bandongan' => 'Bandongan',
    ];

    /** Cache per-request: map tenant_id => override map (sudah dibersihkan). */
    private static array $cache = [];

    public static function label(string $kunci, ?string $default = null): string
    {
        $bawaan = self::DEFAULTS[$kunci] ?? $default ?? $kunci;

        $tenant = function_exists('tenant') ? tenant() : null;
        if (! $tenant) {
            return $bawaan;
        }

        $tenantKey = (string) $tenant->getTenantKey();

        if (! array_key_exists($tenantKey, self::$cache)) {
            self::$cache[$tenantKey] = self::resolveOverrides();
        }

        return self::$cache[$tenantKey][$kunci] ?? $bawaan;
    }

    /** Baca Setting dan normalisasi jadi map kunci-dikenal => label string non-kosong. */
    private static function resolveOverrides(): array
    {
        try {
            $value = Setting::getTenantValue(self::SETTING_KEY);
        } catch (\Throwable) {
            return [];
        }

        // Toleran: string JSON juga diterima.
        if (is_string($value)) {
            $decoded = json_decode($value, true);
            $value = is_array($decoded) ? $decoded : null;
        }

        if (! is_array($value)) {
            return [];
        }

        $overrides = [];
        foreach ($value as $kunci => $label) {
            if (! is_string($kunci) || ! array_key_exists($kunci, self::DEFAULTS)) {
                continue; // kunci asing/typo diabaikan
            }
            if (! is_string($label) || trim($label) === '') {
                continue;
            }
            $overrides[$kunci] = trim($label);
        }

        return $overrides;
    }

    /** Reset cache per-request (dipanggil setelah simpan pengaturan & di test). */
    public static function flushCache(): void
    {
        self::$cache = [];
    }
}
