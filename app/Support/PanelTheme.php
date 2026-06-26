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
     * Registry TEMA (layout/gaya). 'topNav' memindah menu utama ke bilah atas;
     * 'bodyClass' mengaktifkan blok CSS `.tema-…` di theme.css; 'primary' +
     * 'vars' (opsional) = palet bawaan tema yang MENGIKAT — tema berpalet
     * penuh selalu memakai paletnya sendiri, preset warna diabaikan.
     */
    public const THEMES = [
        'bawaan' => [
            'label' => 'Klasik Sidebar (bawaan)',
            'desc' => 'Menu samping seperti WhatsApp — tampilan standar saat ini.',
            'topNav' => false,
            'bodyClass' => null,
        ],
        'pixio' => [
            'label' => 'Pixio (Emerald–Krim)',
            'desc' => 'Bahasa visual utama pesantren.online — latar krim hangat, satu aksen emerald, ornamen emas. Sesuai Design System.',
            'topNav' => false,
            'bodyClass' => 'tema-pixio',
            'primary' => '#059669',
            'vars' => [
                '--tema-sidebar-header' => '#047857',
                '--tema-sidebar-header-border' => 'rgba(201, 162, 74, 0.30)',
                '--tema-active-bg' => '#ecfdf5',
                '--tema-active-fg' => '#047857',
                '--tema-active-dark' => 'linear-gradient(90deg, #10b981 0%, #047857 100%)',
            ],
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
        ],
        'navigasi-atas' => [
            'label' => 'Navigasi Atas',
            'desc' => 'Menu utama pindah ke bilah atas, area kerja lebih lega — cocok layar lebar.',
            'topNav' => true,
            'bodyClass' => 'tema-navigasi-atas',
        ],
        'kompak' => [
            'label' => 'Kompak',
            'desc' => 'Sidebar tetap, jarak antar elemen lebih rapat — muat lebih banyak data per layar.',
            'topNav' => false,
            'bodyClass' => 'tema-kompak',
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
}
