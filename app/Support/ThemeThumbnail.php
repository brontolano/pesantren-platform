<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Thumbnail SVG generatif untuk kartu tema/paket — mock-up mini panel
 * (sidebar/topbar + konten) MEMAKAI hex warna ASLI tiap tema (dari
 * PanelTheme::THEMES/COLORS). Bukan screenshot headless (rapuh & lambat);
 * SVG deterministik, ringan, dan tetap akurat warnanya.
 *
 * Dua mode pakai:
 * - inlineForTheme()/inlineForPackage() → string SVG untuk di-embed langsung
 *   di blade (data-URI-free, tanpa file), dengan fallback aman.
 * - writeAll() (dipakai skrip/generator) → menulis file .svg statis ke
 *   public/images/theme-thumbnails/{key}.svg.
 */
class ThemeThumbnail
{
    /** Warna netral konten/kanvas mock-up. */
    private const CANVAS = '#f3f4f6';

    private const CARD = '#ffffff';

    private const LINE = '#e5e7eb';

    private const TEXT_MUTED = '#cbd5e1';

    /**
     * Palet efektif (hex) untuk satu key tema: header sidebar, latar item
     * aktif, teks aktif, dan warna primer. Selalu mengembalikan hex/warna CSS
     * valid (fallback bawaan bila tema tak punya var tertentu).
     *
     * @return array{header: string, active: string, activeFg: string, primary: string, topNav: bool}
     */
    public static function palette(string $themeKey): array
    {
        $theme = PanelTheme::THEMES[$themeKey] ?? [];
        $vars = $theme['vars'] ?? [];
        // Tema eksternal: palet thumbnail eksplisit di key 'thumb'
        // (CSS-nya milik vendor, tak lewat var --tema-*).
        $thumb = $theme['thumb'] ?? [];

        $header = $thumb['header'] ?? $vars['--tema-sidebar-header'] ?? '#ffffff';
        $active = $thumb['active'] ?? $vars['--tema-active-bg'] ?? '#ecfdf5';
        $activeFg = $thumb['activeFg'] ?? $vars['--tema-active-fg'] ?? '#047857';
        $primary = $thumb['primary'] ?? $theme['primary'] ?? '#059669';

        $headerSolid = self::solid($header, '#ffffff');

        return [
            'header' => $headerSolid,
            'active' => self::solid($active, '#ecfdf5'),
            'activeFg' => self::solid($activeFg, '#047857'),
            'primary' => self::solid($primary, '#059669'),
            'topNav' => (bool) ($theme['topNav'] ?? false),
            // Tema gelap (header pekat) → mock-up kanvas gelap agar akurat.
            'dark' => self::isDark($headerSolid),
        ];
    }

    /**
     * Gradient/rgba tidak dipakai sebagai fill datar SVG yang bersih; ambil
     * warna solid representatif. Bila nilai adalah linear-gradient(...),
     * ambil hex pertama; bila rgba(...), pakai apa adanya (SVG mendukung).
     */
    private static function solid(string $value, string $fallback): string
    {
        $value = trim($value);

        if ($value === '') {
            return $fallback;
        }

        if (str_starts_with($value, 'linear-gradient')) {
            if (preg_match('/#[0-9a-fA-F]{3,8}/', $value, $m) === 1) {
                return $m[0];
            }

            return $fallback;
        }

        return $value;
    }

    /**
     * Apakah warna (hex) tergolong gelap (luminance rendah) → thumbnail
     * memakai kanvas gelap. Hanya menerima #rgb/#rrggbb; selain itu → false.
     */
    private static function isDark(string $hex): bool
    {
        if (preg_match('/^#([0-9a-fA-F]{6})$/', $hex, $m) !== 1) {
            if (preg_match('/^#([0-9a-fA-F]{3})$/', $hex, $m3) === 1) {
                $m[1] = $m3[1][0].$m3[1][0].$m3[1][1].$m3[1][1].$m3[1][2].$m3[1][2];
            } else {
                return false;
            }
        }

        $r = hexdec(substr($m[1], 0, 2));
        $g = hexdec(substr($m[1], 2, 2));
        $b = hexdec(substr($m[1], 4, 2));

        // Luminance perseptual (Rec. 601). < 60 = gelap.
        return (0.299 * $r + 0.587 * $g + 0.114 * $b) < 60;
    }

    /** SVG mock-up untuk satu tema (by key). */
    public static function inlineForTheme(string $themeKey): string
    {
        return self::render(self::palette($themeKey));
    }

    /**
     * SVG mock-up untuk satu paket (by key) — memakai palet tema paket.
     * Fallback ke bawaan bila paket/tema tak dikenal.
     */
    public static function inlineForPackage(string $packageKey): string
    {
        $pkg = ThemePackage::PACKAGES[$packageKey] ?? null;
        $themeKey = $pkg['theme'] ?? 'bawaan';

        return self::render(self::palette($themeKey));
    }

    /**
     * Render SVG mock-up 320x200 dari palet. Layout mengikuti orientasi:
     * top-nav → bilah menu di atas; sidebar → menu di kiri.
     *
     * @param  array{header: string, active: string, activeFg: string, primary: string, topNav: bool}  $p
     */
    public static function render(array $p): string
    {
        $header = self::esc($p['header']);
        $active = self::esc($p['active']);
        $activeFg = self::esc($p['activeFg']);
        $primary = self::esc($p['primary']);
        $isDark = (bool) ($p['dark'] ?? false);
        // Tema gelap → kanvas/kartu/garis gelap agar mock-up representatif.
        $canvas = $isDark ? '#0a0a12' : self::CANVAS;
        $card = $isDark ? '#10131e' : self::CARD;
        $line = $isDark ? '#1b2735' : self::LINE;
        $muted = $isDark ? '#3f4b5b' : self::TEXT_MUTED;

        if ($p['topNav']) {
            // Top navigation: bilah menu horizontal di atas.
            $body = <<<SVG
  <rect x="0" y="0" width="320" height="200" fill="{$canvas}"/>
  <rect x="0" y="0" width="320" height="34" fill="{$header}"/>
  <rect x="14" y="13" width="46" height="8" rx="4" fill="{$primary}"/>
  <rect x="150" y="12" width="40" height="10" rx="5" fill="{$active}"/>
  <rect x="156" y="14" width="28" height="6" rx="3" fill="{$activeFg}"/>
  <rect x="200" y="14" width="34" height="6" rx="3" fill="{$muted}"/>
  <rect x="244" y="14" width="34" height="6" rx="3" fill="{$muted}"/>
  <rect x="20" y="54" width="280" height="52" rx="8" fill="{$card}" stroke="{$line}"/>
  <rect x="34" y="68" width="120" height="9" rx="4" fill="{$primary}"/>
  <rect x="34" y="86" width="200" height="6" rx="3" fill="{$muted}"/>
  <rect x="20" y="120" width="135" height="56" rx="8" fill="{$card}" stroke="{$line}"/>
  <rect x="165" y="120" width="135" height="56" rx="8" fill="{$card}" stroke="{$line}"/>
  <rect x="34" y="134" width="70" height="8" rx="4" fill="{$muted}"/>
  <rect x="179" y="134" width="70" height="8" rx="4" fill="{$muted}"/>
SVG;
        } else {
            // Sidebar klasik: menu vertikal di kiri.
            $body = <<<SVG
  <rect x="0" y="0" width="320" height="200" fill="{$canvas}"/>
  <rect x="0" y="0" width="86" height="200" fill="{$header}"/>
  <rect x="14" y="16" width="52" height="9" rx="4" fill="{$primary}"/>
  <rect x="10" y="44" width="66" height="20" rx="6" fill="{$active}"/>
  <rect x="18" y="51" width="42" height="6" rx="3" fill="{$activeFg}"/>
  <rect x="18" y="76" width="50" height="6" rx="3" fill="{$muted}"/>
  <rect x="18" y="96" width="50" height="6" rx="3" fill="{$muted}"/>
  <rect x="18" y="116" width="50" height="6" rx="3" fill="{$muted}"/>
  <rect x="106" y="18" width="120" height="10" rx="5" fill="{$primary}"/>
  <rect x="106" y="44" width="194" height="46" rx="8" fill="{$card}" stroke="{$line}"/>
  <rect x="120" y="58" width="120" height="8" rx="4" fill="{$muted}"/>
  <rect x="120" y="74" width="80" height="6" rx="3" fill="{$muted}"/>
  <rect x="106" y="104" width="92" height="72" rx="8" fill="{$card}" stroke="{$line}"/>
  <rect x="208" y="104" width="92" height="72" rx="8" fill="{$card}" stroke="{$line}"/>
  <rect x="120" y="150" width="60" height="8" rx="4" fill="{$primary}"/>
  <rect x="222" y="150" width="60" height="8" rx="4" fill="{$muted}"/>
SVG;
        }

        return <<<SVG
<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 320 200" width="320" height="200" role="img" aria-label="Pratinjau tema">
{$body}
</svg>
SVG;
    }

    private static function esc(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
    }

    /**
     * Tulis semua thumbnail SVG statis ke $dir (default public/images/
     * theme-thumbnails). Menghasilkan {themeKey}.svg per tema. Return daftar
     * path yang ditulis. Dipakai skrip generator (bukan runtime request).
     *
     * @return array<int, string>
     */
    public static function writeAll(?string $dir = null): array
    {
        $dir ??= public_path('images/theme-thumbnails');

        if (! is_dir($dir)) {
            mkdir($dir, 0o755, true);
        }

        $written = [];

        foreach (array_keys(PanelTheme::THEMES) as $themeKey) {
            $path = rtrim($dir, '/\\').DIRECTORY_SEPARATOR.$themeKey.'.svg';
            file_put_contents($path, self::inlineForTheme($themeKey));
            $written[] = $path;
        }

        return $written;
    }
}
