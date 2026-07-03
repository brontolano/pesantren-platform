<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\Setting;

/**
 * PAKET TEMA siap-pakai — bundel {tema-layout + preset-warna + tipografi}
 * dalam satu klik. Owner-inti: pesantren memilih "paket" utuh, bukan
 * mengutak-atik tema & warna terpisah.
 *
 * Tiap paket MERUJUK key yang sudah ada di PanelTheme::THEMES (wajib) dan
 * PanelTheme::COLORS (opsional / null). ThemePackage TIDAK memperkenalkan
 * palet/vars baru — ia hanya mengkurasi kombinasi yang sudah teruji agar
 * konsisten & identitas visual utuh. Terapkan lewat applyToSetting() yang
 * menulis dua Setting per-tenant (panel_theme + panel_color_preset) sekaligus.
 *
 * Menambah paket = tambah 1 entry di PACKAGES yang merujuk theme/color valid.
 */
class ThemePackage
{
    /**
     * Registry PAKET bundel.
     * - key        : identitas unik paket (dipakai thumbnail + UI).
     * - name       : nama tampil.
     * - description: kalimat singkat manfaat/nuansa.
     * - theme      : key PanelTheme::THEMES (WAJIB ada).
     * - color      : key PanelTheme::COLORS atau null (ikut palet tema/bawaan).
     * - typography : label font untuk info (mengacu font tema di THEMES).
     * - category   : 'sidebar' | 'topnav' (mengikuti tema layout).
     * - recommended_for : peran/tipe yang disarankan (irisan dgn role login → badge).
     * - features   : daftar keunggulan singkat.
     */
    public const PACKAGES = [
        'standar-putih' => [
            'name' => 'Standar (Sidebar Putih)',
            'description' => 'Tampilan bawaan yang familiar seperti WhatsApp — sidebar putih, aman untuk semua pengguna.',
            'theme' => 'bawaan',
            'color' => null,
            'typography' => 'Plus Jakarta Sans',
            'category' => 'sidebar',
            'recommended_for' => ['admin_pesantren', 'tu', 'operator_divisi'],
            'features' => ['Familiar & mudah', 'Tanpa adaptasi', 'Cocok untuk pemula', 'Netral untuk semua peran'],
        ],
        'emerald-saas' => [
            'name' => 'Emerald SaaS',
            'description' => 'Konsol bersih ala aplikasi modern — kanvas dingin, aksen emerald premium. Sesuai Design System.',
            'theme' => 'pixio',
            'color' => null,
            'typography' => 'Plus Jakarta Sans',
            'category' => 'sidebar',
            'recommended_for' => ['admin_pesantren', 'bendahara_pusat', 'akuntan'],
            'features' => ['Tampilan SaaS premium', 'Aksen emerald profesional', 'Sidebar putih bersih', 'Identitas brand modern'],
        ],
        'baitul-maal-digital' => [
            'name' => 'Baitul Maal Digital',
            'description' => 'Hijau hutan beraksen emas islami dengan sentuhan serif — fokus keuangan & Baitul Maal.',
            'theme' => 'baitul-maal',
            'color' => null,
            'typography' => 'Marcellus (serif) + Plus Jakarta Sans',
            'category' => 'sidebar',
            'recommended_for' => ['bendahara_pusat', 'akuntan', 'kepala_keuangan'],
            'features' => ['Hijau–emas islami', 'Nuansa keuangan', 'Latar hangat krim', 'Aksen serif elegan'],
        ],
        'pimpinan-navy' => [
            'name' => 'Pimpinan Navy',
            'description' => 'Navy eksekutif dengan menu di bilah atas — dashboard lega untuk pimpinan & yayasan.',
            'theme' => 'pimpinan-biru',
            'color' => null,
            'typography' => 'Jost + Plus Jakarta Sans',
            'category' => 'topnav',
            'recommended_for' => ['kepala', 'dewan_pembina', 'pimpinan'],
            'features' => ['Navy eksekutif', 'Menu di atas (top-nav)', 'Area kerja lega', 'Kesan formal & berwibawa'],
        ],
        'merah-putih-nasional' => [
            'name' => 'Merah Putih Nasional',
            'description' => 'Sidebar merah marun elegan berlatar putih bersih — nuansa kebangsaan NKRI.',
            'theme' => 'merah-putih',
            'color' => null,
            'typography' => 'Plus Jakarta Sans',
            'category' => 'sidebar',
            'recommended_for' => ['admin_pesantren', 'kepala', 'tu'],
            'features' => ['Merah marun elegan', 'Nuansa kebangsaan', 'Kontras putih bersih', 'Identitas NKRI'],
        ],
        'hijau-malam' => [
            'name' => 'Hijau Malam',
            'description' => 'Hijau gelap mewah beraksen emas dengan menu di bilah atas — elegan & formal.',
            'theme' => 'hijau-malam',
            'color' => null,
            'typography' => 'Jost + Plus Jakarta Sans',
            'category' => 'topnav',
            'recommended_for' => ['kepala', 'dewan_pembina'],
            'features' => ['Hijau mewah gelap', 'Emas islami', 'Menu top-nav', 'Elegan & formal'],
        ],
        'kompak-padat' => [
            'name' => 'Kompak (Data Padat)',
            'description' => 'Sidebar rapat dengan palet navy — muat lebih banyak data per layar untuk entry cepat.',
            'theme' => 'kompak',
            'color' => 'pimpinan-biru',
            'typography' => 'Plus Jakarta Sans',
            'category' => 'sidebar',
            'recommended_for' => ['operator_divisi', 'kasir', 'petugas_payroll'],
            'features' => ['Data-dense', 'Spasi minimal', 'Banyak baris/layar', 'Efisien untuk entry'],
        ],
        'navigasi-atas-lega' => [
            'name' => 'Navigasi Atas (Lega)',
            'description' => 'Menu pindah ke bilah atas, area kerja maksimal — cocok layar lebar & multitasking.',
            'theme' => 'navigasi-atas',
            'color' => 'baitul-maal',
            'typography' => 'Plus Jakarta Sans',
            'category' => 'topnav',
            'recommended_for' => ['admin_pesantren', 'tu', 'bendahara_pusat'],
            'features' => ['Menu di atas', 'Area kerja maksimal', 'Cocok layar lebar', 'Palet hijau–emas hangat'],
        ],
    ];

    /** Semua paket dengan key ter-inline (untuk render card grid). */
    public static function all(): array
    {
        return collect(self::PACKAGES)
            ->map(fn (array $pkg, string $key) => array_merge(['key' => $key], $pkg))
            ->values()
            ->all();
    }

    /** Ambil satu paket beserta key-nya, atau null bila tak ada. */
    public static function get(string $key): ?array
    {
        if (! array_key_exists($key, self::PACKAGES)) {
            return null;
        }

        return array_merge(['key' => $key], self::PACKAGES[$key]);
    }

    /**
     * Paket yang disarankan untuk himpunan peran (mis. role user login).
     * Irisan non-kosong recommended_for ∩ $roles → disarankan.
     *
     * @param  array<int, string>  $roles
     */
    public static function recommendedForRoles(array $roles): array
    {
        return collect(self::PACKAGES)
            ->filter(fn (array $pkg) => count(array_intersect($pkg['recommended_for'] ?? [], $roles)) > 0)
            ->map(fn (array $pkg, string $key) => array_merge(['key' => $key], $pkg))
            ->values()
            ->all();
    }

    /** Apakah paket $key disarankan untuk salah satu peran di $roles? */
    public static function isRecommendedForRoles(string $key, array $roles): bool
    {
        $pkg = self::PACKAGES[$key] ?? null;

        if ($pkg === null) {
            return false;
        }

        return count(array_intersect($pkg['recommended_for'] ?? [], $roles)) > 0;
    }

    /**
     * Key preset warna efektif untuk paket:
     * - color eksplisit bila di-set,
     * - selain itu 'bawaan' (tema berpalet penuh mengikat paletnya sendiri
     *   di PanelTheme, jadi 'bawaan' aman & benar untuk mereka).
     */
    public static function effectiveColor(string $key): string
    {
        $pkg = self::PACKAGES[$key] ?? [];

        return $pkg['color'] ?? 'bawaan';
    }

    /**
     * Terapkan paket ke tenant AKTIF: tulis panel_theme + panel_color_preset
     * sekaligus (pola sama dgn PengaturanTampilan::saveThemeSettings — Setting
     * di-scope tenant, tidak bocor antar tenant). Return true bila berhasil.
     *
     * Paket tidak valid (theme tak dikenal) → false, tanpa menulis apa pun.
     */
    public static function applyToSetting(string $key): bool
    {
        $pkg = self::PACKAGES[$key] ?? null;

        if ($pkg === null || ! array_key_exists($pkg['theme'], PanelTheme::THEMES)) {
            return false;
        }

        $color = self::effectiveColor($key);

        // Guard: color harus key valid di COLORS (fallback bawaan).
        if (! array_key_exists($color, PanelTheme::COLORS)) {
            $color = 'bawaan';
        }

        Setting::updateOrCreate(
            ['key' => PanelTheme::SETTING_THEME],
            ['value' => ['text' => $pkg['theme']]],
        );
        Setting::updateOrCreate(
            ['key' => PanelTheme::SETTING_COLOR],
            ['value' => ['text' => $color]],
        );

        return true;
    }

    /**
     * Key paket yang cocok dengan tema+warna tersimpan saat ini (untuk
     * highlight kartu AKTIF). Null bila kombinasi tidak persis cocok paket.
     */
    public static function activeKey(string $currentTheme, string $currentColor): ?string
    {
        foreach (self::PACKAGES as $key => $pkg) {
            if ($pkg['theme'] !== $currentTheme) {
                continue;
            }

            if (self::effectiveColor($key) === $currentColor) {
                return $key;
            }
        }

        return null;
    }
}
