<?php

namespace App\Support;

use App\Models\Setting;
use App\Support\AlbaKidsTenant;

/**
 * Tema panel admin per PESANTREN (Setting per tenant, tanpa migrasi DB).
 *
 * Dua konsep TERPISAH:
 * - TEMA  = layout/susunan panel (sidebar klasik, navigasi atas, kompak, ...).
 * - WARNA = preset palet (header sidebar, item aktif, warna primer Filament).
 *
 * Menambah tema/warna baru = tambah 1 entry di THEMES/COLORS (+ blok CSS
 * `.tema-{key}` di resources/css/filament/pesantren/theme.css bila tema butuh
 * gaya lebih dari variabel). Default 'bawaan' = tampilan saat ini, tanpa
 * perubahan visual untuk tenant yang belum memilih.
 */
class PanelTheme
{
    public const SETTING_THEME = 'panel_theme';

    public const SETTING_COLOR = 'panel_color_preset';

    /**
     * Registry TEMA (layout/gaya). Metadata diperkaya untuk UI card-based theme selector:
     * - topNav: apakah gunakan navigasi atas
     * - bodyClass: blok CSS tema di theme.css
     * - primary/vars: palet bawaan (mengikat bila ada)
     * - category: 'sidebar' | 'topnav' | 'utility'
     * - icon: heroicon untuk visual
     * - thumbnail: path image mockup
     * - recommended_roles: siapa sebaiknya pakai (admin, bendahara, pimpinan, dst)
     * - features: daftar keunggulan tema
     * - efficiency: 'compact' | 'normal' | 'spacious'
     */
    public const THEMES = [
        'bawaan' => [
            'label' => 'Klasik Sidebar (bawaan)',
            'desc' => 'Menu samping seperti WhatsApp — tampilan standar saat ini.',
            'topNav' => false,
            'bodyClass' => null,
            'category' => 'sidebar',
            'icon' => 'heroicon-o-rectangle-group',
            'thumbnail' => '/images/theme-thumbnails/bawaan.webp',
            'recommended_roles' => ['admin_pesantren', 'tu'],
            'features' => ['Familiar seperti WhatsApp', 'Cocok untuk entry-level', 'Tidak perlu adaptasi'],
            'efficiency' => 'normal',
        ],
        'pixio' => [
            'label' => 'Pixio (Emerald SaaS)',
            'desc' => 'Konsol bersih ala Filament — kanvas slate dingin, sidebar putih, aksen emerald. Sesuai template Design System.',
            'topNav' => false,
            'bodyClass' => 'tema-pixio',
            'primary' => '#059669',
            'vars' => [
                '--tema-sidebar-header' => '#ffffff',
                '--tema-sidebar-header-border' => '#e7ecf3',
                '--tema-active-bg' => '#ecfdf5',
                '--tema-active-fg' => '#047857',
                '--tema-active-dark' => 'linear-gradient(90deg, #10b981 0%, #047857 100%)',
            ],
            'category' => 'sidebar',
            'icon' => 'heroicon-o-sparkles',
            'thumbnail' => '/images/theme-thumbnails/pixio.webp',
            'recommended_roles' => ['admin_pesantren', 'bendahara_pusat', 'akuntan'],
            'features' => ['Premium SaaS look', 'Brand identity emerald', 'Cream hangat, sidebar putih', 'Aksen profesional'],
            'efficiency' => 'normal',
        ],
        'baitul-maal' => [
            'label' => 'Baitul Maal (Hijau–Emas)',
            'desc' => 'Sidebar hijau hutan gelap beraksen emas, latar krim — desain Baitul Maal Digital.',
            'topNav' => false,
            'bodyClass' => 'tema-baitul-maal',
            'primary' => '#0A5C36',
            'vars' => [
                '--tema-sidebar-header' => '#06351F',
                '--tema-sidebar-header-border' => 'rgba(255, 255, 255, 0.1)',
                '--tema-active-bg' => 'rgba(238, 220, 154, 0.15)',
                '--tema-active-fg' => '#ffffff',
                '--tema-active-dark' => 'linear-gradient(90deg, #157A4C 0%, #0A5C36 100%)',
            ],
            'category' => 'sidebar',
            'icon' => 'heroicon-o-currency-dollar',
            'thumbnail' => '/images/theme-thumbnails/baitul-maal.webp',
            'recommended_roles' => ['bendahara_pusat', 'akuntan', 'kepala_keuangan'],
            'features' => ['Hijau hutan profesional', 'Aksen emas islami', 'Latar hangat krim', 'Fokus keuangan'],
            'efficiency' => 'normal',
        ],
        'pimpinan-biru' => [
            'label' => 'Pimpinan Biru (Navigasi Atas)',
            'desc' => 'Navy profesional dengan menu di bilah atas — gaya dashboard pimpinan/yayasan.',
            'topNav' => true,
            'bodyClass' => 'tema-pimpinan-biru',
            'primary' => '#1d4ed8',
            'vars' => [
                '--tema-sidebar-header' => '#0f2a4a',
                '--tema-sidebar-header-border' => '#0b2038',
                '--tema-active-bg' => '#dbeafe',
                '--tema-active-fg' => '#1d4ed8',
                '--tema-active-dark' => 'linear-gradient(90deg, #2563eb 0%, #1d4ed8 100%)',
            ],
            'category' => 'topnav',
            'icon' => 'heroicon-o-chart-bar',
            'thumbnail' => '/images/theme-thumbnails/pimpinan-biru.webp',
            'recommended_roles' => ['kepala', 'dewan_pembina', 'pimpinan'],
            'features' => ['Navy profesional', 'Menu di atas (top-nav)', 'Dashboard pimpinan', 'Lebih luas kerja'],
            'efficiency' => 'spacious',
        ],
        'merah-putih' => [
            'label' => 'Merah Putih',
            'desc' => 'Sidebar merah marun gelap, konten putih bersih — nuansa kebangsaan.',
            'topNav' => false,
            'bodyClass' => 'tema-merah-putih',
            'primary' => '#dc2626',
            'vars' => [
                '--tema-sidebar-header' => '#7f1d1d',
                '--tema-sidebar-header-border' => '#601414',
                '--tema-active-bg' => 'rgba(255, 255, 255, 0.14)',
                '--tema-active-fg' => '#ffffff',
                '--tema-active-dark' => 'linear-gradient(90deg, #dc2626 0%, #b91c1c 100%)',
            ],
            'category' => 'sidebar',
            'icon' => 'heroicon-o-flag',
            'thumbnail' => '/images/theme-thumbnails/merah-putih.webp',
            'recommended_roles' => ['admin_pesantren', 'kepala', 'tu'],
            'features' => ['Merah marun elegan', 'Nuansa kebangsaan', 'Kontras putih bersih', 'Identitas NKRI'],
            'efficiency' => 'normal',
        ],
        'hijau-malam' => [
            'label' => 'Hijau Malam (Emas)',
            'desc' => 'Hijau gelap mewah beraksen emas dengan menu di bilah atas — elegan untuk pimpinan.',
            'topNav' => true,
            'bodyClass' => 'tema-hijau-malam',
            'primary' => '#10b981',
            'vars' => [
                '--tema-sidebar-header' => '#0f2419',
                '--tema-sidebar-header-border' => 'rgba(212, 175, 55, 0.25)',
                '--tema-active-bg' => 'rgba(212, 175, 55, 0.16)',
                '--tema-active-fg' => '#0f5132',
                '--tema-active-dark' => 'linear-gradient(90deg, #10b981 0%, #047857 100%)',
            ],
            'category' => 'topnav',
            'icon' => 'heroicon-o-moon',
            'thumbnail' => '/images/theme-thumbnails/hijau-malam.webp',
            'recommended_roles' => ['kepala', 'dewan_pembina'],
            'features' => ['Hijau mewah gelap', 'Emas islami', 'Menu top-nav', 'Elegan & formal'],
            'efficiency' => 'spacious',
        ],
        'navigasi-atas' => [
            'label' => 'Navigasi Atas',
            'desc' => 'Menu utama pindah ke bilah atas, area kerja lebih lega — cocok layar lebar.',
            'topNav' => true,
            'bodyClass' => 'tema-navigasi-atas',
            'category' => 'topnav',
            'icon' => 'heroicon-o-bars-3',
            'thumbnail' => '/images/theme-thumbnails/navigasi-atas.webp',
            'recommended_roles' => ['admin_pesantren', 'tu', 'bendahara_pusat'],
            'features' => ['Menu di atas', 'Area kerja maksimal', 'Cocok layar lebar', 'Tidak ada sidebar'],
            'efficiency' => 'spacious',
        ],
        'kompak' => [
            'label' => 'Kompak',
            'desc' => 'Sidebar tetap, jarak antar elemen lebih rapat — muat lebih banyak data per layar.',
            'topNav' => false,
            'bodyClass' => 'tema-kompak',
            'category' => 'utility',
            'icon' => 'heroicon-o-rectangle-stack',
            'thumbnail' => '/images/theme-thumbnails/kompak.webp',
            'recommended_roles' => ['operator_divisi', 'kasir', 'petugas_payroll'],
            'features' => ['Data-dense', 'Spasi minimal', 'Banyak baris/layar', 'Efisien untuk entry'],
            'efficiency' => 'compact',
        ],
    ];

    /**
     * Registry PRESET WARNA — DIKUNCI: diturunkan dari kombinasi palet
     * tema-tema terkunci (bukan warna bebas), agar identitas visual
     * platform konsisten. Berlaku untuk tema TANPA palet bawaan
     * (Klasik Sidebar / Navigasi Atas / Kompak); nilai disesuaikan
     * untuk konteks sidebar terang tema bawaan.
     */
    public const COLORS = [
        'bawaan' => [
            'label' => 'Hijau Pesantren (bawaan)',
            'primary' => null,
            'vars' => [],
        ],
        'baitul-maal' => [
            'label' => 'Palet Baitul Maal (Hijau–Emas)',
            'primary' => '#0A5C36',
            'vars' => [
                '--tema-sidebar-header' => '#06351F',
                '--tema-sidebar-header-border' => '#04281A',
                '--tema-active-bg' => '#F6ECC8',
                '--tema-active-fg' => '#0A5C36',
                '--tema-active-dark' => 'linear-gradient(90deg, #157A4C 0%, #0A5C36 100%)',
            ],
        ],
        'pimpinan-biru' => [
            'label' => 'Palet Pimpinan Biru (Navy)',
            'primary' => '#1d4ed8',
            'vars' => [
                '--tema-sidebar-header' => '#0f2a4a',
                '--tema-sidebar-header-border' => '#0b2038',
                '--tema-active-bg' => '#dbeafe',
                '--tema-active-fg' => '#1d4ed8',
                '--tema-active-dark' => 'linear-gradient(90deg, #2563eb 0%, #1d4ed8 100%)',
            ],
        ],
        'merah-putih' => [
            'label' => 'Palet Merah Putih',
            'primary' => '#dc2626',
            'vars' => [
                '--tema-sidebar-header' => '#7f1d1d',
                '--tema-sidebar-header-border' => '#601414',
                '--tema-active-bg' => '#fee2e2',
                '--tema-active-fg' => '#b91c1c',
                '--tema-active-dark' => 'linear-gradient(90deg, #dc2626 0%, #b91c1c 100%)',
            ],
        ],
        'hijau-malam' => [
            'label' => 'Palet Hijau Malam (Emas)',
            'primary' => '#10b981',
            'vars' => [
                '--tema-sidebar-header' => '#0f2419',
                '--tema-sidebar-header-border' => '#0a1a12',
                '--tema-active-bg' => '#F6ECC8',
                '--tema-active-fg' => '#0f5132',
                '--tema-active-dark' => 'linear-gradient(90deg, #10b981 0%, #047857 100%)',
            ],
        ],
    ];

    /**
     * Key tema aktif tenant.
     * - AlbaKids (tenant custom) → perilaku lama, default 'bawaan' (WhatsApp).
     * - Tenant lain → DEFAULT 'pixio' bila belum memilih; pilihan eksplisit
     *   (termasuk 'bawaan' WA bila sengaja dipilih) tetap dihormati.
     */
    public static function theme(): string
    {
        if (AlbaKidsTenant::isActive()) {
            return self::resolve(self::SETTING_THEME, self::THEMES);
        }

        try {
            if (! tenancy()->initialized || tenancy()->tenant === null) {
                return 'pixio';
            }

            $value = Setting::getTenantStringValue(self::SETTING_THEME);
        } catch (\Throwable) {
            return 'pixio';
        }

        return is_string($value) && array_key_exists($value, self::THEMES) ? $value : 'pixio';
    }

    /** Key preset warna aktif tenant — fallback 'bawaan'. */
    public static function color(): string
    {
        return self::resolve(self::SETTING_COLOR, self::COLORS);
    }

    /**
     * Hex warna primer Filament. Tema berpalet penuh (mis. Baitul Maal —
     * punya 'primary' sendiri) MENGIKAT: paletnya selalu ikut tema agar
     * identitas desain utuh. Preset warna hanya berlaku utk tema tanpa
     * palet bawaan; null = bawaan panel.
     */
    public static function primaryHex(): ?string
    {
        return self::THEMES[self::theme()]['primary']
            ?? self::COLORS[self::color()]['primary'];
    }

    /** Tema aktif memakai navigasi atas? */
    public static function topNavigation(): bool
    {
        return (bool) self::THEMES[self::theme()]['topNav'];
    }

    /** Kelas body utk blok CSS tema (null = tanpa kelas tambahan). */
    public static function bodyClass(): ?string
    {
        return self::THEMES[self::theme()]['bodyClass'];
    }

    /**
     * Deklarasi variabel CSS aktif ('' bila semuanya bawaan).
     * Preset warna dahulu, lalu DITIMPA var tema — tema berpalet penuh
     * mengikat paletnya sendiri (identitas desain tidak boleh tercampur).
     */
    public static function cssVars(): string
    {
        $vars = array_merge(
            self::COLORS[self::color()]['vars'],
            self::THEMES[self::theme()]['vars'] ?? [],
        );

        return implode('', array_map(
            fn (string $k, string $v): string => "{$k}:{$v};",
            array_keys($vars),
            $vars,
        ));
    }

    /** @param array<string, array<string, mixed>> $registry */
    private static function resolve(string $settingKey, array $registry): string
    {
        try {
            if (! tenancy()->initialized || tenancy()->tenant === null) {
                return 'bawaan';
            }

            $value = Setting::getTenantStringValue($settingKey);
        } catch (\Throwable) {
            // Tabel settings belum termigrasi / tenancy belum siap → bawaan.
            return 'bawaan';
        }

        return is_string($value) && array_key_exists($value, $registry) ? $value : 'bawaan';
    }

    /** ========================================================================
       HELPER METHODS UNTUK UI THEME SELECTOR (CARD-BASED)
       ======================================================================== */

    /** Ambil metadata satu tema beserta key-nya. */
    public static function themeWithMetadata(string $themeKey): ?array
    {
        if (! array_key_exists($themeKey, self::THEMES)) {
            return null;
        }

        return array_merge(['key' => $themeKey], self::THEMES[$themeKey]);
    }

    /** Daftar semua tema dengan metadata (untuk render card grid). */
    public static function allThemesWithMetadata(): array
    {
        return collect(self::THEMES)->map(
            fn (array $theme, string $key) => array_merge(['key' => $key], $theme)
        )->values()->all();
    }

    /** Filter tema per kategori (sidebar / topnav / utility). */
    public static function themesByCategory(string $category): array
    {
        return collect(self::THEMES)
            ->filter(fn (array $t) => ($t['category'] ?? null) === $category)
            ->map(fn (array $theme, string $key) => array_merge(['key' => $key], $theme))
            ->values()
            ->all();
    }

    /** Tema yang recommended untuk role tertentu. */
    public static function recommendedThemeForRole(string $role): ?string
    {
        foreach (self::THEMES as $key => $theme) {
            if (in_array($role, $theme['recommended_roles'] ?? [])) {
                return $key;
            }
        }

        return null;
    }

    /** Daftar kategori tema yang tersedia. */
    public static function categories(): array
    {
        return collect(self::THEMES)
            ->pluck('category')
            ->unique()
            ->sort()
            ->values()
            ->all();
    }

    /** Label kategori untuk UI. */
    public static function categoryLabel(string $category): string
    {
        return match ($category) {
            'sidebar' => 'Sidebar Klasik',
            'topnav' => 'Navigasi Atas (Top-Nav)',
            'utility' => 'Utilitas',
            default => ucfirst($category),
        };
    }

    /** Ambil metadata satu preset warna beserta key-nya. */
    public static function colorWithMetadata(string $colorKey): ?array
    {
        if (! array_key_exists($colorKey, self::COLORS)) {
            return null;
        }

        return array_merge(['key' => $colorKey], self::COLORS[$colorKey]);
    }

    /** Daftar warna yang applicable untuk tema aktif. */
    public static function applicableColors(): array
    {
        $currentTheme = self::theme();
        $themeMeta = self::THEMES[$currentTheme];

        // Tema berpalet penuh (primary exists) → hanya gunakan palet tema itu.
        if (isset($themeMeta['primary'])) {
            return [
                'locked' => true,
                'reason' => "Tema '{$themeMeta['label']}' memiliki palet sendiri.",
                'colors' => [$currentTheme => $themeMeta],
            ];
        }

        // Tema tanpa palet → semua preset warna applicable.
        return [
            'locked' => false,
            'colors' => collect(self::COLORS)
                ->map(fn (array $c, string $k) => array_merge(['key' => $k], $c))
                ->all(),
        ];
    }
}
