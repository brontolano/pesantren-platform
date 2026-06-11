<?php

namespace App\Support;

use App\Models\Setting;

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
     * 'bodyClass' mengaktifkan blok CSS `.tema-…` di theme.css; 'primary'
     * (opsional) = palet Filament bila preset warna masih bawaan; 'vars'
     * (opsional) = variabel CSS bawaan tema (preset warna menimpanya).
     */
    public const THEMES = [
        'bawaan' => [
            'label' => 'Klasik Sidebar (bawaan)',
            'desc' => 'Menu samping seperti WhatsApp — tampilan standar saat ini.',
            'topNav' => false,
            'bodyClass' => null,
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
     * Registry PRESET WARNA. 'primary' = palet tombol/badge Filament;
     * 'vars' = variabel CSS yang dibaca theme.css (header sidebar, item aktif).
     * 'bawaan' sengaja kosong → fallback hex lama di theme.css yang berlaku.
     */
    public const COLORS = [
        'bawaan' => [
            'label' => 'Hijau Pesantren (bawaan)',
            'primary' => null,
            'vars' => [],
        ],
        'laut' => [
            'label' => 'Biru Laut',
            'primary' => '#0284c7',
            'vars' => [
                '--tema-sidebar-header' => '#075985',
                '--tema-sidebar-header-border' => '#0c4a6e',
                '--tema-active-bg' => '#e0f2fe',
                '--tema-active-fg' => '#0369a1',
                '--tema-active-dark' => 'linear-gradient(90deg, #0284c7 0%, #0369a1 100%)',
            ],
        ],
        'senja' => [
            'label' => 'Jingga Senja',
            'primary' => '#ea580c',
            'vars' => [
                '--tema-sidebar-header' => '#9a3412',
                '--tema-sidebar-header-border' => '#7c2d12',
                '--tema-active-bg' => '#ffedd5',
                '--tema-active-fg' => '#c2410c',
                '--tema-active-dark' => 'linear-gradient(90deg, #ea580c 0%, #c2410c 100%)',
            ],
        ],
        'royal' => [
            'label' => 'Ungu Royal',
            'primary' => '#7c3aed',
            'vars' => [
                '--tema-sidebar-header' => '#5b21b6',
                '--tema-sidebar-header-border' => '#4c1d95',
                '--tema-active-bg' => '#ede9fe',
                '--tema-active-fg' => '#6d28d9',
                '--tema-active-dark' => 'linear-gradient(90deg, #7c3aed 0%, #6d28d9 100%)',
            ],
        ],
        'klasik' => [
            'label' => 'Hijau Klasik Kitab',
            'primary' => '#15803d',
            'vars' => [
                '--tema-sidebar-header' => '#14532d',
                '--tema-sidebar-header-border' => '#0f3d21',
                '--tema-active-bg' => '#dcfce7',
                '--tema-active-fg' => '#166534',
                '--tema-active-dark' => 'linear-gradient(90deg, #16a34a 0%, #15803d 100%)',
            ],
        ],
    ];

    /** Key tema aktif tenant — fallback 'bawaan' (Setting kosong/tak dikenal/error). */
    public static function theme(): string
    {
        return self::resolve(self::SETTING_THEME, self::THEMES);
    }

    /** Key preset warna aktif tenant — fallback 'bawaan'. */
    public static function color(): string
    {
        return self::resolve(self::SETTING_COLOR, self::COLORS);
    }

    /**
     * Hex warna primer Filament: preset warna menang; bila preset masih
     * bawaan, tema boleh membawa primer sendiri; null = bawaan panel.
     */
    public static function primaryHex(): ?string
    {
        return self::COLORS[self::color()]['primary']
            ?? (self::THEMES[self::theme()]['primary'] ?? null);
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
     * Var bawaan tema dahulu, lalu DITIMPA preset warna pilihan pengguna.
     */
    public static function cssVars(): string
    {
        $vars = array_merge(
            self::THEMES[self::theme()]['vars'] ?? [],
            self::COLORS[self::color()]['vars'],
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
