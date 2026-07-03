<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\Setting;

/**
 * PAKET TEMA siap-pakai — bundel {tema-layout + preset-warna + tipografi}
 * dalam satu klik. Owner-inti: pesantren memilih "paket" utuh, bukan
 * mengutak-atik tema & warna terpisah.
 *
 * Paket kini = Standar (bawaan) + 4 TEMA EKSTERNAL asli (Brisk/Nord/
 * Moonlight/Neo-Brutalis). Tiap paket MERUJUK key PanelTheme::THEMES (wajib) dan
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
            'description' => 'Tampilan bawaan yang familiar seperti WhatsApp — sidebar putih, aman untuk semua pengguna. Default & disarankan umum.',
            'theme' => 'bawaan',
            'color' => null,
            'typography' => 'Plus Jakarta Sans',
            'category' => 'sidebar',
            'recommended_for' => ['admin_pesantren', 'tu', 'operator_divisi'],
            'features' => ['Familiar & mudah', 'Tanpa adaptasi', 'Cocok untuk pemula', 'Netral untuk semua peran'],
        ],
        'brisk' => [
            'name' => 'Brisk (Filafly)',
            'description' => 'Tema eksternal premium dari Filafly — bersih, ramah, font Kumbh Sans; komponen tombol/kartu/tabel/modal di-restyle menyeluruh.',
            'theme' => 'brisk',
            'color' => null,
            'typography' => 'Kumbh Sans',
            'category' => 'eksternal',
            'recommended_for' => ['admin_pesantren', 'tu'],
            'features' => ['Tema jadi dari Filafly', 'Gaya friendly & bersih', 'Font Kumbh Sans', 'Komponen di-restyle menyeluruh'],
        ],
        'nord' => [
            'name' => 'Nord (Arctic)',
            'description' => 'Palet Nord asli — nuansa dingin nordic (polar night & frost), primer biru es; varian terang + gelap mengikuti toggle mode gelap.',
            'theme' => 'nord',
            'color' => null,
            'typography' => 'Inter (bawaan Nord)',
            'category' => 'eksternal',
            'recommended_for' => ['admin_pesantren', 'operator_divisi'],
            'features' => ['Palet Nord asli (Arctic)', 'Nuansa dingin menenangkan', 'Primer biru es', 'Varian terang + gelap'],
        ],
        'moonlight' => [
            'name' => 'Moonlight (Gelap)',
            'description' => 'Tema gelap ala JetBrains Moonlight — biru malam dengan aksen pastel; mode gelap otomatis aktif (bisa di-toggle).',
            'theme' => 'moonlight',
            'color' => null,
            'typography' => 'Plus Jakarta Sans',
            'category' => 'eksternal',
            'recommended_for' => ['operator_divisi', 'admin_pesantren'],
            'features' => ['Gelap ala JetBrains Moonlight', 'Aksen pastel lembut', 'Teduh untuk kerja malam', 'Mode gelap otomatis aktif'],
        ],
        'neobrutalism' => [
            'name' => 'Neo-Brutalis',
            'description' => 'Neo-brutalism — border tebal, bayangan offset, kontras berani; tombol/kartu/input berubah total, identitas visual kuat.',
            'theme' => 'neobrutalism',
            'color' => null,
            'typography' => 'Plus Jakarta Sans',
            'category' => 'eksternal',
            'recommended_for' => ['admin_pesantren'],
            'features' => ['Gaya neo-brutalism berani', 'Border tebal + bayangan offset', 'Kontras tinggi mudah dibaca', 'Identitas visual kuat'],
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
