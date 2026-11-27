<?php

namespace App\Support;

use App\Models\Setting;

/**
 * Tema panel admin per PESANTREN (Setting per tenant, tanpa migrasi DB).
 *
 * Dua konsep TERPISAH:
 * - TEMA  = paket tema EKSTERNAL asli (paket Composer dari komunitas Filament)
 *           yang mengubah komponen NYATA (tombol/kartu/tabel/modal/font) lewat
 *           build CSS Vite terpisah per tema ("multi-build CSS + pilih runtime"
 *           — ApplyPanelTheme memanggil Panel::viteTheme(cssEntry) per-request
 *           setelah tenancy aktif).
 * - WARNA = preset palet (header sidebar, item aktif, warna primer Filament)
 *           untuk tema tanpa palet bawaan (berbasis var --tema-* di theme.css).
 *
 *   Tema 'bawaan' TANPA 'cssEntry' → build theme.css standar; tampilan
 *   Filament default tetap utuh (aman untuk AlbaKids/tenant tanpa pilihan).
 *   Tema LAMA buatan sendiri (slate-pro/glass/corporate/midnight/warm/aurora/
 *   nature/neon) DIHAPUS BERSIH — Setting lama yang masih menyimpannya jatuh
 *   mulus ke 'bawaan' (lihat theme()).
 *
 * Menambah tema eksternal baru = composer require paketnya + 1 entry CSS Vite
 * (import CSS vendor) + 1 entry di THEMES (cssEntry + metadata + thumb).
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
            'label' => 'Standar (Ruang Kerja Modern)',
            'desc' => 'Sidebar ringkas, permukaan terang yang bersih, dan status kerja yang mudah dibaca.',
            'topNav' => false,
            'bodyClass' => null,
            'category' => 'sidebar',
            'icon' => 'heroicon-o-rectangle-group',
            'thumbnail' => '/images/theme-thumbnails/bawaan.svg',
            'recommended_roles' => ['admin_pesantren', 'tu', 'operator_divisi'],
            'features' => ['Tampilan modern dan tenang', 'Navigasi mudah dipindai', 'Nyaman di layar kecil', 'Netral untuk semua peran'],
            'efficiency' => 'normal',
            'typography' => 'Plus Jakarta Sans',
        ],
        'brisk' => [
            'label' => 'Brisk (Filafly)',
            'desc' => 'Tema eksternal premium dari Filafly — bersih, ramah, font Kumbh Sans; mengubah komponen nyata (tombol, kartu, tabel, modal).',
            'topNav' => false,
            'bodyClass' => null,
            'cssEntry' => 'resources/css/filament/pesantren/theme-brisk.css',
            'font' => 'Kumbh Sans',
            // Identitas Brisk (demo Filafly): monokrom zinc, primer near-black.
            // Tanpa palet ini Brisk tampak "hijau standar" — nyaris tak beda.
            'primary' => '#18181b',
            'palette' => [
                'primary' => '#18181b',
                'gray' => 'zinc',
            ],
            'category' => 'eksternal',
            'icon' => 'heroicon-o-sparkles',
            'thumbnail' => '/images/theme-thumbnails/brisk.svg',
            'thumb' => ['header' => '#fafafa', 'active' => '#f4f4f5', 'activeFg' => '#18181b', 'primary' => '#18181b'],
            'recommended_roles' => ['admin_pesantren', 'tu'],
            'features' => ['Tema jadi dari Filafly', 'Gaya friendly & bersih', 'Font Kumbh Sans', 'Komponen di-restyle menyeluruh'],
            'efficiency' => 'normal',
            'typography' => 'Kumbh Sans',
        ],
        'nord' => [
            'label' => 'Nord (Arctic)',
            'desc' => 'Tema eksternal palet Nord — nuansa dingin nordic (polar night & frost), primer biru es; light + dark mengikuti toggle mode gelap.',
            'topNav' => false,
            'bodyClass' => null,
            'cssEntry' => 'resources/css/filament/pesantren/theme-nord.css',
            'primary' => '#88c0d0',
            // REPLIKA persis FilamentNordThemePlugin::register() ->colors():
            // identitas Nord terutama datang dari palet ini (gray = polar night)
            // — CSS vendornya sendiri kecil. Tanpa registrasi ini Nord nyaris
            // tak terlihat.
            'palette' => [
                'danger' => '#bf616a',
                'gray' => [
                    50 => '#eceff4', 100 => '#e5e9f0', 200 => '#d8dee9',
                    300 => '#a7b1c5', 400 => '#8c9ab3', 500 => '#71829b',
                    600 => '#4c566a', 700 => '#434c5e', 800 => '#3b4252',
                    900 => '#2e3440', 950 => '#232831',
                ],
                'info' => '#81a1c1',
                'primary' => [
                    50 => '#FAFCFD', 100 => '#F3F9FA', 200 => '#E3EFF2',
                    300 => '#CFE6EC', 400 => '#ACD4DD', 500 => '#88C0D0',
                    600 => '#7AADBB', 700 => '#66909B', 800 => '#52737D',
                    900 => '#445E66', 950 => '#293A3D',
                ],
                'secondary' => '#5e81ac',
                'success' => '#a3be8c',
                'warning' => '#ebcb8b',
            ],
            'category' => 'eksternal',
            'icon' => 'heroicon-o-cloud',
            'thumbnail' => '/images/theme-thumbnails/nord.svg',
            'thumb' => ['header' => '#2e3440', 'active' => '#434c5e', 'activeFg' => '#eceff4', 'primary' => '#88c0d0'],
            'recommended_roles' => ['admin_pesantren', 'operator_divisi'],
            'features' => ['Palet Nord asli (Arctic)', 'Nuansa dingin menenangkan', 'Primer biru es', 'Varian terang + gelap'],
            'efficiency' => 'normal',
            'typography' => 'Inter (bawaan Nord)',
        ],
        'moonlight' => [
            'label' => 'Moonlight (Gelap)',
            'desc' => 'Tema eksternal gelap ala JetBrains Moonlight — biru malam dengan aksen pastel; mode gelap otomatis aktif (bisa di-toggle).',
            'topNav' => false,
            'bodyClass' => null,
            'cssEntry' => 'resources/css/filament/pesantren/theme-moonlight.css',
            'primary' => '#82aaff',
            // SELURUH CSS Moonlight di-scope `.dark` (tema gelap murni) —
            // tanpa default dark halaman tetap terang & tema tak terlihat.
            'defaultDark' => true,
            'palette' => [
                'primary' => '#82aaff',
            ],
            'category' => 'eksternal',
            'icon' => 'heroicon-o-moon',
            'thumbnail' => '/images/theme-thumbnails/moonlight.svg',
            'thumb' => ['header' => '#1e2030', 'active' => '#2f334d', 'activeFg' => '#c8d3f5', 'primary' => '#82aaff'],
            'recommended_roles' => ['operator_divisi', 'admin_pesantren'],
            'features' => ['Gelap ala JetBrains Moonlight', 'Aksen pastel lembut', 'Teduh untuk kerja malam', 'Mode gelap otomatis aktif'],
            'efficiency' => 'normal',
            'typography' => 'Plus Jakarta Sans',
        ],
        'neobrutalism' => [
            'label' => 'Neo-Brutalis',
            'desc' => 'Tema eksternal neo-brutalism — border tebal, bayangan offset, kontras berani; komponen tombol/kartu/input berubah total.',
            'topNav' => false,
            'bodyClass' => null,
            'cssEntry' => 'resources/css/filament/pesantren/theme-neobrutalism.css',
            // Identitas neo-brutalism: primer near-black kontras tinggi
            // (aksen kuning datang dari CSS vendornya sendiri).
            'primary' => '#111827',
            'palette' => [
                'primary' => '#111827',
            ],
            'category' => 'eksternal',
            'icon' => 'heroicon-o-square-2-stack',
            'thumbnail' => '/images/theme-thumbnails/neobrutalism.svg',
            'thumb' => ['header' => '#ffffff', 'active' => '#fde047', 'activeFg' => '#111827', 'primary' => '#111827'],
            'recommended_roles' => ['admin_pesantren'],
            'features' => ['Gaya neo-brutalism berani', 'Border tebal + bayangan offset', 'Kontras tinggi mudah dibaca', 'Identitas visual kuat'],
            'efficiency' => 'normal',
            'typography' => 'Plus Jakarta Sans',
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
        'emerald' => [
            'label' => 'Palet Emerald',
            'primary' => '#059669',
            'vars' => [
                '--tema-sidebar-header' => '#065f46',
                '--tema-sidebar-header-border' => '#064e3b',
                '--tema-active-bg' => '#ecfdf5',
                '--tema-active-fg' => '#047857',
                '--tema-active-dark' => 'linear-gradient(90deg, #10b981 0%, #047857 100%)',
            ],
        ],
        'navy' => [
            'label' => 'Palet Navy',
            'primary' => '#2563eb',
            'vars' => [
                '--tema-sidebar-header' => '#1e293b',
                '--tema-sidebar-header-border' => '#0f172a',
                '--tema-active-bg' => '#dbeafe',
                '--tema-active-fg' => '#1d4ed8',
                '--tema-active-dark' => 'linear-gradient(90deg, #3b82f6 0%, #2563eb 100%)',
            ],
        ],
        'amber' => [
            'label' => 'Palet Amber',
            'primary' => '#d97706',
            'vars' => [
                '--tema-sidebar-header' => '#78350f',
                '--tema-sidebar-header-border' => '#5c290b',
                '--tema-active-bg' => '#fffbeb',
                '--tema-active-fg' => '#b45309',
                '--tema-active-dark' => 'linear-gradient(90deg, #f59e0b 0%, #d97706 100%)',
            ],
        ],
    ];

    /**
     * Key tema aktif tenant. Default per-tenant dari registry kustomisasi
     * ('panel_theme_default'):
     * - SEMUA tenant → 'bawaan' (Ruang Kerja Modern) bila belum memilih;
     *   pilihan eksplisit tenant via Setting tetap dihormati. Nilai tersimpan
     *   yang tak dikenal (mis. tema lama yang sudah dihapus) → jatuh ke 'bawaan'.
     */
    public static function theme(): string
    {
        $default = TenantCustomizationRegistry::stringValue('panel_theme_default') ?? 'bawaan';

        if (! array_key_exists($default, self::THEMES)) {
            $default = 'bawaan';
        }

        try {
            if (! tenancy()->initialized || tenancy()->tenant === null) {
                return $default;
            }

            $value = Setting::getTenantStringValue(self::SETTING_THEME);
        } catch (\Throwable) {
            return $default;
        }

        return is_string($value) && array_key_exists($value, self::THEMES) ? $value : $default;
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

    /**
     * Tema aktif kategori 'eksternal'? Dipakai widget dasbor theme-aware:
     * identitas bawaan (gradient hijau WA dsb.) hidup di theme.css bawaan;
     * saat tema eksternal aktif widget tampil netral mengikuti token tema
     * (--primary-*, gray) — bukan warna hardcode.
     */
    public static function isEksternal(): bool
    {
        return (self::THEMES[self::theme()]['category'] ?? null) === 'eksternal';
    }

    /** Kelas body utk blok CSS tema (null = tanpa kelas tambahan). */
    public static function bodyClass(): ?string
    {
        return self::THEMES[self::theme()]['bodyClass'];
    }

    /** Entry CSS Vite default (tema bawaan/standar). */
    public const DEFAULT_CSS_ENTRY = 'resources/css/filament/pesantren/theme.css';

    /**
     * Entry CSS Vite tema aktif — arsitektur "multi-build CSS + pilih
     * runtime": tiap tema EKSTERNAL punya build CSS sendiri (lihat
     * vite.config.js), dipilih per-request oleh ApplyPanelTheme lewat
     * Panel::viteTheme(). Tema tanpa 'cssEntry' → build bawaan.
     */
    public static function cssEntry(): string
    {
        return self::THEMES[self::theme()]['cssEntry'] ?? self::DEFAULT_CSS_ENTRY;
    }

    /**
     * Palet warna vendor tema aktif — REPLIKA efek `->plugin(XxxTheme::make())`
     * vendor (yang me-register colors ke panel) tanpa mendaftarkan plugin
     * global (tidak aman antar tenant di worker long-running). Nilai:
     * hex string, nama palet Filament ('zinc' dll), atau array shade penuh.
     * Kosong utk tema tanpa palet vendor. Diterapkan per-request oleh
     * ApplyPanelTheme lewat FilamentColor::register().
     *
     * @return array<string, string|array<int, string>>
     */
    public static function palette(): array
    {
        return self::THEMES[self::theme()]['palette'] ?? [];
    }

    /**
     * Tema aktif gelap-default? (mis. Moonlight: seluruh CSS-nya di-scope
     * `.dark`). ApplyPanelTheme men-set default theme mode panel ke Dark —
     * pengguna tetap bisa toggle terang/gelap.
     */
    public static function defaultDark(): bool
    {
        return (bool) (self::THEMES[self::theme()]['defaultDark'] ?? false);
    }

    /** Idem defaultDark() tetapi untuk key tema tertentu (dipakai UI apply). */
    public static function defaultDarkFor(string $themeKey): bool
    {
        return (bool) (self::THEMES[$themeKey]['defaultDark'] ?? false);
    }

    /**
     * Font panel tema aktif (null = font bawaan platform). Diterapkan
     * per-request oleh ApplyPanelTheme lewat Panel::font() (provider
     * bawaan Bunny Fonts) — mis. Brisk memakai Kumbh Sans.
     */
    public static function font(): ?string
    {
        return self::THEMES[self::theme()]['font'] ?? null;
    }

    /**
     * Deklarasi variabel CSS aktif ('' bila semuanya bawaan).
     * Preset warna dahulu, lalu DITIMPA var tema — tema berpalet penuh
     * mengikat paletnya sendiri (identitas desain tidak boleh tercampur).
     */
    public static function cssVars(): string
    {
        // Tema EKSTERNAL: JANGAN suntik var --tema-* preset warna — var itu
        // hanya dipakai theme.css bawaan; menyuntiknya berisiko menimpa
        // gaya vendor. Palet tema eksternal diikat lewat palette().
        if ((self::THEMES[self::theme()]['category'] ?? null) === 'eksternal') {
            return '';
        }

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
            'eksternal' => 'Tema Eksternal (Premium Look)',
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
