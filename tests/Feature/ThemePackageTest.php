<?php

namespace Tests\Feature;

use App\Models\Setting;
use App\Support\PanelTheme;
use App\Support\ThemePackage;
use App\Support\ThemeThumbnail;
use Tests\Concerns\InteractsWithPpdbTestDatabase;
use Tests\TestCase;

/**
 * PAKET TEMA (5 paket = Standar + 4 tema EKSTERNAL asli: Brisk/Nord/
 * Moonlight/Neo-Brutalis): tiap paket merujuk theme/color yang VALID di
 * PanelTheme; applyToSetting menulis dua Setting per-tenant dengan benar;
 * recommendedForRoles menghitung irisan peran; thumbnail SVG deterministik.
 */
class ThemePackageTest extends TestCase
{
    use InteractsWithPpdbTestDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->ensurePpdbTestingSchemaReady(['tenants', 'domains', 'settings']);
        $this->truncatePpdbTables(['domains', 'tenants']);
    }

    private function boot(string $prefix): void
    {
        $tenant = $this->createRuntimeTenant($prefix, 'Pesantren '.$prefix);
        tenancy()->initialize($tenant);
        $this->seedTenantRow($tenant);
    }

    public function test_lima_paket_merujuk_theme_dan_color_yang_valid(): void
    {
        $this->assertCount(5, ThemePackage::PACKAGES);
        $expected = ['standar-putih', 'brisk', 'nord', 'moonlight', 'neobrutalism'];
        $this->assertSame($expected, array_keys(ThemePackage::PACKAGES));

        foreach (ThemePackage::PACKAGES as $key => $pkg) {
            $this->assertArrayHasKey('name', $pkg, $key);
            $this->assertArrayHasKey('description', $pkg, $key);
            $this->assertArrayHasKey('theme', $pkg, $key);
            $this->assertArrayHasKey('color', $pkg, $key);
            $this->assertArrayHasKey('typography', $pkg, $key);
            $this->assertArrayHasKey('category', $pkg, $key);
            $this->assertArrayHasKey('recommended_for', $pkg, $key);
            $this->assertArrayHasKey('features', $pkg, $key);

            // theme WAJIB key valid di THEMES
            $this->assertArrayHasKey($pkg['theme'], PanelTheme::THEMES, "Paket {$key} merujuk tema tak dikenal");

            // color null diperbolehkan; jika di-set harus valid di COLORS
            if ($pkg['color'] !== null) {
                $this->assertArrayHasKey($pkg['color'], PanelTheme::COLORS, "Paket {$key} merujuk warna tak dikenal");
            }

            // category selaras kategori tema
            $this->assertContains($pkg['category'], ['sidebar', 'topnav', 'eksternal'], $key);
            $this->assertSame(PanelTheme::THEMES[$pkg['theme']]['category'], $pkg['category'], $key);
        }
    }

    public function test_paket_standar_adalah_tema_bawaan_default(): void
    {
        $std = ThemePackage::get('standar-putih');
        $this->assertNotNull($std);
        $this->assertSame('bawaan', $std['theme']);
        $this->assertNull($std['color']);
    }

    public function test_all_dan_get_mengembalikan_key_ter_inline(): void
    {
        $all = ThemePackage::all();
        $this->assertSameSize(ThemePackage::PACKAGES, $all);
        $this->assertArrayHasKey('key', $all[0]);

        $pkg = ThemePackage::get('brisk');
        $this->assertNotNull($pkg);
        $this->assertSame('brisk', $pkg['key']);
        $this->assertSame('brisk', $pkg['theme']);

        $this->assertNull(ThemePackage::get('paket-tidak-ada'));
        // Paket skin lama sudah dihapus bersih.
        $this->assertNull(ThemePackage::get('slate-pro'));
        $this->assertNull(ThemePackage::get('neon'));
    }

    public function test_apply_to_setting_menulis_theme_dan_color_per_tenant(): void
    {
        $this->boot('paket');

        // Paket standar (bawaan, color null) → color efektif 'bawaan'.
        $this->assertTrue(ThemePackage::applyToSetting('standar-putih'));
        $this->assertSame('bawaan', Setting::getTenantStringValue(PanelTheme::SETTING_THEME));
        $this->assertSame('bawaan', Setting::getTenantStringValue(PanelTheme::SETTING_COLOR));
        $this->assertSame('bawaan', PanelTheme::theme());
        $this->assertSame(PanelTheme::DEFAULT_CSS_ENTRY, PanelTheme::cssEntry());

        // Paket eksternal Brisk → tema + build CSS-nya aktif.
        $this->assertTrue(ThemePackage::applyToSetting('brisk'));
        $this->assertSame('brisk', Setting::getTenantStringValue(PanelTheme::SETTING_THEME));
        $this->assertSame('bawaan', Setting::getTenantStringValue(PanelTheme::SETTING_COLOR));
        $this->assertSame('resources/css/filament/pesantren/theme-brisk.css', PanelTheme::cssEntry());

        // Paket eksternal berpalet (nord) → primer mengikat.
        $this->assertTrue(ThemePackage::applyToSetting('nord'));
        $this->assertSame('nord', Setting::getTenantStringValue(PanelTheme::SETTING_THEME));
        $this->assertSame('#88c0d0', PanelTheme::primaryHex());

        // Paket tidak dikenal / paket lama → false, tidak menulis.
        $this->assertFalse(ThemePackage::applyToSetting('paket-hantu'));
        $this->assertFalse(ThemePackage::applyToSetting('corporate'));
        $this->assertSame('nord', Setting::getTenantStringValue(PanelTheme::SETTING_THEME));

        tenancy()->end();
    }

    public function test_recommended_for_roles_menghitung_irisan(): void
    {
        // Peran TU → brisk (ramah utk admin/tu) muncul.
        $forTu = collect(ThemePackage::recommendedForRoles(['tu']))->pluck('key')->all();
        $this->assertContains('brisk', $forTu);
        $this->assertNotContains('moonlight', $forTu);

        // Peran operator → nord & moonlight.
        $forOperator = collect(ThemePackage::recommendedForRoles(['operator_divisi']))->pluck('key')->all();
        $this->assertContains('nord', $forOperator);
        $this->assertContains('moonlight', $forOperator);

        // Kosong / tak match → tak ada.
        $this->assertSame([], ThemePackage::recommendedForRoles([]));
        $this->assertSame([], ThemePackage::recommendedForRoles(['peran_asing']));

        // Helper boolean.
        $this->assertTrue(ThemePackage::isRecommendedForRoles('brisk', ['tu']));
        $this->assertFalse(ThemePackage::isRecommendedForRoles('moonlight', ['tu']));
        $this->assertFalse(ThemePackage::isRecommendedForRoles('paket-hantu', ['tu']));
    }

    public function test_active_key_mendeteksi_kombinasi_tersimpan(): void
    {
        // standar-putih = bawaan + bawaan.
        $this->assertSame('standar-putih', ThemePackage::activeKey('bawaan', 'bawaan'));
        // brisk + bawaan → paket brisk.
        $this->assertSame('brisk', ThemePackage::activeKey('brisk', 'bawaan'));
        // nord + bawaan → paket nord.
        $this->assertSame('nord', ThemePackage::activeKey('nord', 'bawaan'));
        // kombinasi acak / tema lama → null.
        $this->assertNull(ThemePackage::activeKey('slate-pro', 'bawaan'));
        $this->assertNull(ThemePackage::activeKey('tema-hantu', 'bawaan'));
    }

    public function test_thumbnail_svg_valid_dan_memakai_warna_tema(): void
    {
        // Tema nord → header polar night #2e3440 harus muncul di SVG (key 'thumb').
        $svg = ThemeThumbnail::inlineForTheme('nord');
        $this->assertStringStartsWith('<svg', $svg);
        $this->assertStringContainsString('#2e3440', $svg);
        $this->assertStringContainsString('#88c0d0', $svg);

        // Paket moonlight → header biru malam #1e2030.
        $pkgSvg = ThemeThumbnail::inlineForPackage('moonlight');
        $this->assertStringContainsString('#1e2030', $pkgSvg);

        // Fallback aman untuk key tak dikenal (pakai palet bawaan, tetap SVG valid).
        $fallback = ThemeThumbnail::inlineForPackage('paket-hantu');
        $this->assertStringStartsWith('<svg', $fallback);

        // Semua tema menghasilkan SVG well-formed (root <svg> + close).
        foreach (array_keys(PanelTheme::THEMES) as $themeKey) {
            $out = ThemeThumbnail::inlineForTheme($themeKey);
            $this->assertStringStartsWith('<svg', $out, $themeKey);
            $this->assertStringContainsString('</svg>', $out, $themeKey);
        }
    }
}
