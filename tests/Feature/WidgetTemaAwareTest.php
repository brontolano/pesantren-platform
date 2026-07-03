<?php

namespace Tests\Feature;

use App\Support\PanelTheme;
use Tests\TestCase;

/**
 * Widget dasbor THEME-AWARE (perbaikan GAP "setengah bertema"):
 * - Blade widget custom TIDAK lagi memakai warna hardcode (hex WA-green /
 *   TikTok-dark) — mereka memakai token semantik (primary / gray) yang
 *   mengikuti palet tema aktif.
 * - Identitas tema BAWAAN (gradient hijau WA, kartu krem, permukaan gelap
 *   #1C1C24) TETAP UTUH: dipulihkan blok .tema-* di theme.css bawaan yang
 *   TIDAK ikut ter-bundle di build tema eksternal.
 */
class WidgetTemaAwareTest extends TestCase
{
    private const BLADES = [
        'resources/views/filament/widgets/greeting-banner.blade.php',
        'resources/views/filament/widgets/quick-access.blade.php',
        'resources/views/filament/widgets/agenda-pengumuman.blade.php',
    ];

    /** Hex identitas lama yang dulunya hardcode di blade. */
    private const HEX_LAMA = ['#0b8f63', '#047857', '#059669', '#10b981', '#1C1C24', '#2A2A35', '#fffaf3', '#efe7da', '#161823'];

    public function test_blade_widget_tanpa_hex_hardcode(): void
    {
        foreach (self::BLADES as $blade) {
            $isi = file_get_contents(base_path($blade));

            foreach (self::HEX_LAMA as $hex) {
                $this->assertStringNotContainsStringIgnoringCase(
                    $hex,
                    $isi,
                    "{$blade} masih hardcode {$hex} — widget tidak theme-aware.",
                );
            }
        }
    }

    public function test_banner_memakai_token_primary(): void
    {
        $isi = file_get_contents(base_path('resources/views/filament/widgets/greeting-banner.blade.php'));

        $this->assertStringContainsString('from-primary-700', $isi, 'Gradient banner harus dari palet primer panel.');
        $this->assertStringContainsString('text-primary-600', $isi, 'Chip sholat aktif harus memakai token primer.');
    }

    public function test_identitas_bawaan_dipertahankan_di_theme_css_bawaan(): void
    {
        $sumber = file_get_contents(base_path(PanelTheme::DEFAULT_CSS_ENTRY));

        // Gradient hijau WA + permukaan gelap TikTok pindah ke theme.css bawaan.
        $this->assertStringContainsString('.tema-greeting', $sumber);
        $this->assertStringContainsString('#0b8f63', $sumber, 'Gradient hijau WA banner (identitas bawaan) wajib tetap ada.');
        $this->assertStringContainsString('.tema-quick', $sumber);
        $this->assertStringContainsString('#fffaf3', $sumber, 'Kartu krem Akses Cepat (identitas bawaan) wajib tetap ada.');
        $this->assertStringContainsString('.tema-card', $sumber);
        $this->assertStringContainsString('#1C1C24', $sumber);
    }

    public function test_build_tema_eksternal_bersih_dari_identitas_bawaan(): void
    {
        $manifestPath = public_path('build/manifest.json');

        if (! file_exists($manifestPath)) {
            $this->markTestSkipped('Manifest Vite belum ada — jalankan `npm run build` dulu.');
        }

        $manifest = json_decode((string) file_get_contents($manifestPath), true);

        // Build bawaan memuat identitas; build eksternal TIDAK (tampil netral
        // mengikuti tema — bukan "setengah bertema").
        $bawaan = file_get_contents(public_path('build/'.$manifest[PanelTheme::DEFAULT_CSS_ENTRY]['file']));
        $this->assertStringContainsString('#0b8f63', $bawaan, 'Build bawaan wajib memuat gradient hijau WA.');

        foreach (PanelTheme::THEMES as $key => $meta) {
            if (! isset($meta['cssEntry'])) {
                continue;
            }

            $css = file_get_contents(public_path('build/'.$manifest[$meta['cssEntry']]['file']));
            $this->assertStringNotContainsString('#0b8f63', $css, "Build tema {$key} tidak boleh membawa identitas bawaan.");
            $this->assertStringNotContainsString('.tema-greeting{', $css, "Build tema {$key} tidak boleh memuat blok identitas .tema-greeting.");
        }
    }
}
