<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\Setting;

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
    /**
     * Prefix key Setting per-tenant untuk override kustomisasi tanpa deploy
     * config (tahap 4 #343). Urutan resolve: Setting tenant → config
     * per-tenant → config default. Nilai Setting boleh mentah atau dibungkus
     * ['value' => ...] (pola Setting bercast array).
     */
    public const SETTING_PREFIX = 'customization.';

    /**
     * Memo per-request hasil lookup Setting: "{tenantKey}|{key}" =>
     * ['hit' => bool, 'value' => mixed]. Aman karena runtime PHP-FPM/
     * FrankenPHP non-worker mereset statik tiap request; test wajib
     * memanggil flushSettingMemo() setelah menulis Setting.
     *
     * @var array<string, array{hit: bool, value: mixed}>
     */
    protected static array $settingMemo = [];

    /** Reset memo Setting (dipakai test / setelah menulis Setting in-request). */
    public static function flushSettingMemo(): void
    {
        static::$settingMemo = [];
    }

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

    /**
     * Kelas body halaman publik tenant (guard tema, mis. 'tenant-albakids'
     * mempertahankan gaya asli pra-Pixio). '' = tanpa kelas tambahan.
     * Keputusan dari registry, BUKAN cek slug tenant di blade (tahap 4 #343);
     * nilai kelasnya sendiri tetap 'tenant-albakids' untuk kompat CSS.
     */
    public static function bodyClass(): string
    {
        return static::stringValue('body_class') ?? '';
    }

    /**
     * Peta default tautan konten Aplikasi (Beranda & Ibadah) — satu sumber
     * untuk AppShellController + PengaturanBranding (dulu duplikat konstanta
     * berisi URL asy-syifaa). Admin tetap menimpa per-key via Setting.
     *
     * @return array<string, string>
     */
    public static function appContentLinks(): array
    {
        return (array) static::value('app_content_links');
    }

    /**
     * Ambil nilai kustomisasi $key untuk tenant saat ini.
     * Urutan: Setting tenant ('customization.{key}') → config per-tenant →
     * config default.
     */
    public static function value(string $key): mixed
    {
        $override = static::settingOverride($key);

        if ($override['hit']) {
            return $override['value'];
        }

        $entry = static::currentEntry();

        if ($entry !== null && array_key_exists($key, $entry)) {
            return $entry[$key];
        }

        return config("tenant-customization.default.{$key}");
    }

    /**
     * Override via Setting tenant (memo per-request; 1 query per key).
     * Defensif: tenancy belum siap / tabel settings belum ada → tidak ada
     * override, tanpa melempar (pola PanelTheme::resolve()).
     *
     * @return array{hit: bool, value: mixed}
     */
    protected static function settingOverride(string $key): array
    {
        $tenantKey = static::currentTenantKey();

        if ($tenantKey === null) {
            return ['hit' => false, 'value' => null];
        }

        $memoKey = $tenantKey.'|'.$key;

        if (array_key_exists($memoKey, static::$settingMemo)) {
            return static::$settingMemo[$memoKey];
        }

        $result = ['hit' => false, 'value' => null];

        try {
            if (tenancy()->initialized && tenancy()->tenant !== null) {
                $value = Setting::getTenantValue(static::SETTING_PREFIX.$key);

                if ($value !== null) {
                    // Bungkus ['value' => ...] (pola Setting cast array) → unwrap.
                    if (is_array($value) && array_keys($value) === ['value']) {
                        $value = $value['value'];
                    }

                    $result = ['hit' => true, 'value' => $value];
                }
            }
        } catch (\Throwable) {
            // Biarkan tanpa override; fallback config.
        }

        return static::$settingMemo[$memoKey] = $result;
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
