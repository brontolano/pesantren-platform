<?php

namespace App\Support;

use App\Models\Setting;

/**
 * Tema panel admin per PESANTREN (Setting per tenant, tanpa migrasi DB).
 *
 * Dua konsep TERPISAH:
 * - TEMA  = SKIN LENGKAP: layout + font + radius + bayangan + gaya tombol/
 *           kartu/input + palet. Tiap tema mengganti "kulit" panel menyeluruh
 *           (bukan cuma warna) lewat kontrak var --tema-* di theme.css.
 * - WARNA = preset palet (header sidebar, item aktif, warna primer Filament)
 *           untuk tema tanpa palet bawaan.
 *
 * KONTRAK SKIN (var yang di-drive theme.css, di-scope body.tema-{key}):
 *   Warna : --tema-sidebar-header, --tema-sidebar-header-border,
 *           --tema-active-bg, --tema-active-fg, --tema-active-dark.
 *   Skin  : --tema-font-sans (+ opsional --tema-font-heading), --tema-radius,
 *           --tema-btn-radius, --tema-input-radius, --tema-card-shadow,
 *           --tema-card-border, --tema-btn-shadow, --tema-btn-weight,
 *           --tema-heading-weight.
 *   Tema 'bawaan' TIDAK punya bodyClass → CSS skin generik tak berlaku,
 *   tampilan Filament standar tetap (aman untuk AlbaKids/default).
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
            'label' => 'Standar (Sidebar Putih)',
            'desc' => 'Menu samping putih seperti WhatsApp, header hijau — tampilan standar & default.',
            'topNav' => false,
            'bodyClass' => null,
            'category' => 'sidebar',
            'icon' => 'heroicon-o-rectangle-group',
            'thumbnail' => '/images/theme-thumbnails/bawaan.svg',
            'recommended_roles' => ['admin_pesantren', 'tu', 'operator_divisi'],
            'features' => ['Familiar seperti WhatsApp', 'Cocok untuk entry-level', 'Tidak perlu adaptasi', 'Netral untuk semua peran'],
            'efficiency' => 'normal',
            'typography' => 'Plus Jakarta Sans',
        ],
        'slate-pro' => [
            'label' => 'Slate Pro (Emerald)',
            'desc' => 'Konsol bersih gaya Aura — kanvas slate dingin, sidebar putih, aksen emerald profesional.',
            'topNav' => false,
            'bodyClass' => 'tema-slate-pro',
            'primary' => '#059669',
            'vars' => [
                '--tema-sidebar-header' => '#ffffff',
                '--tema-sidebar-header-border' => '#e7ecf3',
                '--tema-active-bg' => '#ecfdf5',
                '--tema-active-fg' => '#047857',
                '--tema-active-dark' => 'linear-gradient(90deg, #10b981 0%, #047857 100%)',
                // --- KONTRAK SKIN (SaaS bersih) ---
                '--tema-font-sans' => "'Jost', system-ui, sans-serif",
                '--tema-radius' => '0.5rem',
                '--tema-btn-radius' => '0.5rem',
                '--tema-input-radius' => '0.5rem',
                '--tema-card-shadow' => '0 1px 2px rgba(15, 23, 42, 0.04), 0 6px 18px -12px rgba(15, 23, 42, 0.14)',
                '--tema-card-border' => '1px solid #e7ecf3',
                '--tema-btn-shadow' => '0 1px 2px rgba(15, 23, 42, 0.06)',
                '--tema-btn-weight' => '600',
                '--tema-heading-weight' => '700',
            ],
            'category' => 'sidebar',
            'icon' => 'heroicon-o-sparkles',
            'thumbnail' => '/images/theme-thumbnails/slate-pro.svg',
            'recommended_roles' => ['admin_pesantren', 'bendahara_pusat', 'akuntan'],
            'features' => ['Tampilan SaaS premium', 'Aksen emerald profesional', 'Sidebar putih bersih', 'Kanvas slate dingin'],
            'efficiency' => 'normal',
            'typography' => 'Plus Jakarta Sans',
        ],
        'glass' => [
            'label' => 'Kaca (Glass)',
            'desc' => 'Glassmorphism lembut — kartu semi-transparan berblur, kanvas gradasi slate/indigo, aksen indigo.',
            'topNav' => false,
            'bodyClass' => 'tema-glass',
            'primary' => '#6366f1',
            'vars' => [
                '--tema-sidebar-header' => '#ffffff',
                '--tema-sidebar-header-border' => 'rgba(99, 102, 241, 0.14)',
                '--tema-active-bg' => '#eef2ff',
                '--tema-active-fg' => '#4338ca',
                '--tema-active-dark' => 'linear-gradient(90deg, #6366f1 0%, #4338ca 100%)',
                // --- KONTRAK SKIN (glassmorphism: radius besar, blur, shadow lembut) ---
                '--tema-font-sans' => "'Inter', system-ui, sans-serif",
                '--tema-radius' => '1rem',
                '--tema-btn-radius' => '0.75rem',
                '--tema-input-radius' => '0.75rem',
                '--tema-card-shadow' => '0 8px 24px -12px rgba(79, 70, 229, 0.18)',
                '--tema-card-border' => '1px solid rgba(255, 255, 255, 0.6)',
                '--tema-btn-shadow' => '0 2px 8px -2px rgba(99, 102, 241, 0.24)',
                '--tema-btn-weight' => '600',
                '--tema-heading-weight' => '600',
            ],
            'category' => 'sidebar',
            'icon' => 'heroicon-o-square-3-stack-3d',
            'thumbnail' => '/images/theme-thumbnails/glass.svg',
            'recommended_roles' => ['admin_pesantren', 'tu'],
            'features' => ['Glassmorphism modern', 'Kartu berblur halus', 'Aksen indigo', 'Kanvas gradasi lembut'],
            'efficiency' => 'normal',
            'typography' => 'Plus Jakarta Sans',
        ],
        'corporate' => [
            'label' => 'Korporat (Navigasi Atas)',
            'desc' => 'Netral slate profesional dengan menu di bilah atas — band header navy, aksen biru eksekutif.',
            'topNav' => true,
            'bodyClass' => 'tema-corporate',
            'primary' => '#2563eb',
            'vars' => [
                '--tema-sidebar-header' => '#1e293b',
                '--tema-sidebar-header-border' => '#0f172a',
                '--tema-active-bg' => '#dbeafe',
                '--tema-active-fg' => '#1d4ed8',
                '--tema-active-dark' => 'linear-gradient(90deg, #3b82f6 0%, #2563eb 100%)',
                // --- KONTRAK SKIN (korporat: radius kecil, border tegas, shadow minimal) ---
                '--tema-font-sans' => "'IBM Plex Sans', system-ui, sans-serif",
                '--tema-radius' => '0.375rem',
                '--tema-btn-radius' => '0.375rem',
                '--tema-input-radius' => '0.375rem',
                '--tema-card-shadow' => '0 1px 2px rgba(15, 23, 42, 0.06)',
                '--tema-card-border' => '1px solid #cbd5e1',
                '--tema-btn-shadow' => 'none',
                '--tema-btn-weight' => '600',
                '--tema-heading-weight' => '700',
            ],
            'category' => 'topnav',
            'icon' => 'heroicon-o-building-office-2',
            'thumbnail' => '/images/theme-thumbnails/corporate.svg',
            'recommended_roles' => ['kepala', 'dewan_pembina', 'pimpinan'],
            'features' => ['Netral & eksekutif', 'Menu di atas (top-nav)', 'Band header navy', 'Area kerja lega'],
            'efficiency' => 'spacious',
            'typography' => 'Plus Jakarta Sans',
        ],
        'midnight' => [
            'label' => 'Tengah Malam (Indigo)',
            'desc' => 'Dark-first nyaris hitam, sidebar gelap pekat, aksen indigo/violet — teduh untuk mata.',
            'topNav' => false,
            'bodyClass' => 'tema-midnight',
            'primary' => '#818cf8',
            'vars' => [
                '--tema-sidebar-header' => '#12121c',
                '--tema-sidebar-header-border' => '#232338',
                '--tema-active-bg' => '#eef2ff',
                '--tema-active-fg' => '#4f46e5',
                '--tema-active-dark' => 'linear-gradient(90deg, #818cf8 0%, #6366f1 100%)',
                // --- KONTRAK SKIN (dark-first: radius sedang, glow tipis indigo) ---
                '--tema-font-sans' => "'Space Grotesk', system-ui, sans-serif",
                '--tema-radius' => '0.75rem',
                '--tema-btn-radius' => '0.625rem',
                '--tema-input-radius' => '0.625rem',
                '--tema-card-shadow' => '0 6px 20px -12px rgba(99, 102, 241, 0.35)',
                '--tema-card-border' => '1px solid #232338',
                '--tema-btn-shadow' => '0 2px 10px -3px rgba(129, 140, 248, 0.4)',
                '--tema-btn-weight' => '600',
                '--tema-heading-weight' => '600',
            ],
            'category' => 'sidebar',
            'icon' => 'heroicon-o-moon',
            'thumbnail' => '/images/theme-thumbnails/midnight.svg',
            'recommended_roles' => ['operator_divisi', 'admin_pesantren'],
            'features' => ['Dark-first pekat', 'Aksen indigo/violet', 'Teduh untuk mata', 'Fokus malam hari'],
            'efficiency' => 'normal',
            'typography' => 'Plus Jakarta Sans',
        ],
        'warm' => [
            'label' => 'Hangat (Amber)',
            'desc' => 'Kanvas krem hangat, sidebar warm-white, aksen amber — nuansa ramah & bersahaja.',
            'topNav' => false,
            'bodyClass' => 'tema-warm',
            'primary' => '#d97706',
            'vars' => [
                '--tema-sidebar-header' => '#fffaf3',
                '--tema-sidebar-header-border' => '#efe4d3',
                '--tema-active-bg' => '#fffbeb',
                '--tema-active-fg' => '#b45309',
                '--tema-active-dark' => 'linear-gradient(90deg, #f59e0b 0%, #d97706 100%)',
                // --- KONTRAK SKIN (hangat: radius membulat ramah, shadow hangat lembut) ---
                '--tema-font-sans' => "'Nunito', system-ui, sans-serif",
                '--tema-radius' => '0.75rem',
                '--tema-btn-radius' => '0.875rem',
                '--tema-input-radius' => '0.75rem',
                '--tema-card-shadow' => '0 4px 14px rgba(120, 53, 15, 0.08)',
                '--tema-card-border' => '1px solid #efe4d3',
                '--tema-btn-shadow' => '0 2px 8px -2px rgba(217, 119, 6, 0.28)',
                '--tema-btn-weight' => '700',
                '--tema-heading-weight' => '700',
            ],
            'category' => 'sidebar',
            'icon' => 'heroicon-o-sun',
            'thumbnail' => '/images/theme-thumbnails/warm.svg',
            'recommended_roles' => ['admin_pesantren', 'tu'],
            'features' => ['Kanvas krem hangat', 'Aksen amber ramah', 'Sidebar warm-white', 'Nuansa bersahaja'],
            'efficiency' => 'normal',
            'typography' => 'Plus Jakarta Sans',
        ],
        'aurora' => [
            'label' => 'Aurora (Violet)',
            'desc' => 'Kanvas terang lapang beraksen gradasi ungu→merah muda→biru, primer violet — segar & ekspresif.',
            'topNav' => false,
            'bodyClass' => 'tema-aurora',
            'primary' => '#8b5cf6',
            'vars' => [
                '--tema-sidebar-header' => '#ffffff',
                '--tema-sidebar-header-border' => 'rgba(139, 92, 246, 0.16)',
                '--tema-active-bg' => '#f5f3ff',
                '--tema-active-fg' => '#7c3aed',
                '--tema-active-dark' => 'linear-gradient(90deg, #a78bfa 0%, #ec4899 100%)',
                // --- KONTRAK SKIN (aurora: membulat, tombol gradasi lembut) ---
                '--tema-font-sans' => "'Outfit', system-ui, sans-serif",
                '--tema-radius' => '1rem',
                '--tema-btn-radius' => '0.75rem',
                '--tema-input-radius' => '0.75rem',
                '--tema-card-shadow' => '0 8px 24px -14px rgba(124, 58, 237, 0.22)',
                '--tema-card-border' => '1px solid rgba(139, 92, 246, 0.14)',
                '--tema-btn-shadow' => '0 3px 12px -3px rgba(139, 92, 246, 0.4)',
                '--tema-btn-weight' => '600',
                '--tema-heading-weight' => '600',
            ],
            'category' => 'sidebar',
            'icon' => 'heroicon-o-swatch',
            'thumbnail' => '/images/theme-thumbnails/aurora.svg',
            'recommended_roles' => ['admin_pesantren', 'tu'],
            'features' => ['Gradasi aurora ekspresif', 'Primer violet segar', 'Kanvas terang lapang', 'Aksen ungu–merah muda'],
            'efficiency' => 'normal',
            'typography' => 'Plus Jakarta Sans',
        ],
        'nature' => [
            'label' => 'Alam (Forest)',
            'desc' => 'Earthy — kanvas sage lembut, sidebar putih kehijauan, primer forest, aksen olive/cokelat.',
            'topNav' => false,
            'bodyClass' => 'tema-nature',
            'primary' => '#15803d',
            'vars' => [
                '--tema-sidebar-header' => '#fbfdf7',
                '--tema-sidebar-header-border' => '#e2ead9',
                '--tema-active-bg' => '#ecfdf3',
                '--tema-active-fg' => '#166534',
                '--tema-active-dark' => 'linear-gradient(90deg, #22c55e 0%, #15803d 100%)',
                // --- KONTRAK SKIN (alam: heading serif Lora, radius organik, earthy) ---
                '--tema-font-sans' => "'Plus Jakarta Sans', system-ui, sans-serif",
                '--tema-font-heading' => "'Lora', Georgia, serif",
                '--tema-radius' => '0.875rem',
                '--tema-btn-radius' => '0.75rem',
                '--tema-input-radius' => '0.625rem',
                '--tema-card-shadow' => '0 4px 14px rgba(20, 83, 45, 0.07)',
                '--tema-card-border' => '1px solid #e2ead9',
                '--tema-btn-shadow' => '0 2px 8px -2px rgba(21, 128, 61, 0.24)',
                '--tema-btn-weight' => '600',
                '--tema-heading-weight' => '600',
            ],
            'category' => 'sidebar',
            'icon' => 'heroicon-o-sparkles',
            'thumbnail' => '/images/theme-thumbnails/nature.svg',
            'recommended_roles' => ['admin_pesantren', 'kepala', 'tu'],
            'features' => ['Nuansa earthy alami', 'Primer forest green', 'Aksen olive/cokelat', 'Kanvas sage lembut'],
            'efficiency' => 'normal',
            'typography' => 'Lora + Plus Jakarta Sans',
        ],
        'neon' => [
            'label' => 'Neon (Cyber)',
            'desc' => 'Kanvas gelap #0a0a12, aksen neon cyan/lime dengan glow, heading Orbitron — futuristik & berenergi.',
            'topNav' => false,
            'bodyClass' => 'tema-neon',
            'primary' => '#22d3ee',
            'vars' => [
                '--tema-sidebar-header' => '#0a0a12',
                '--tema-sidebar-header-border' => '#1b2735',
                '--tema-active-bg' => 'rgba(34, 211, 238, 0.14)',
                '--tema-active-fg' => '#22d3ee',
                '--tema-active-dark' => 'linear-gradient(90deg, #22d3ee 0%, #a3e635 100%)',
                // --- KONTRAK SKIN (neon: radius kecil-sedang + GLOW neon, heading Orbitron) ---
                '--tema-font-sans' => "'Inter', system-ui, sans-serif",
                '--tema-font-heading' => "'Orbitron', 'Space Grotesk', sans-serif",
                '--tema-radius' => '0.5rem',
                '--tema-btn-radius' => '0.375rem',
                '--tema-input-radius' => '0.375rem',
                '--tema-card-shadow' => '0 0 0 1px rgba(34, 211, 238, 0.12), 0 8px 24px -14px rgba(34, 211, 238, 0.3)',
                '--tema-card-border' => '1px solid rgba(34, 211, 238, 0.25)',
                '--tema-btn-shadow' => '0 0 12px -2px rgba(34, 211, 238, 0.55)',
                '--tema-btn-weight' => '700',
                '--tema-heading-weight' => '700',
            ],
            'category' => 'sidebar',
            'icon' => 'heroicon-o-bolt',
            'thumbnail' => '/images/theme-thumbnails/neon.svg',
            'recommended_roles' => ['operator_divisi', 'admin_pesantren'],
            'features' => ['Kanvas cyber gelap', 'Aksen neon cyan/lime', 'Glow pada tombol & item aktif', 'Heading Orbitron futuristik'],
            'efficiency' => 'normal',
            'typography' => 'Orbitron + Inter',
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
     * - SEMUA tenant → 'bawaan' (Standar Sidebar Putih) bila belum memilih;
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
