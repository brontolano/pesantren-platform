<?php

namespace Tests\Feature;

use App\Models\Setting;
use App\Support\PanelTheme;
use App\Support\ThemePackage;
use App\Support\ThemeThumbnail;
use Tests\Concerns\InteractsWithPpdbTestDatabase;
use Tests\TestCase;

/**
 * PAKET TEMA siap-pakai (8 paket = Standar + 7 gaya Aura): tiap paket
 * merujuk theme/color yang VALID di PanelTheme; applyToSetting menulis dua
 * Setting per-tenant dengan benar; recommendedForRoles menghitung irisan
 * peran; thumbnail SVG deterministik.
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

    public function test_delapan_paket_merujuk_theme_dan_color_yang_valid(): void
    {
        $this->assertCount(8, ThemePackage::PACKAGES);
        $expected = ['standar-putih', 'slate-pro', 'glass', 'corporate', 'midnight', 'warm', 'aurora', 'nature'];
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
            $this->assertContains($pkg['category'], ['sidebar', 'topnav'], $key);
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

        $pkg = ThemePackage::get('slate-pro');
        $this->assertNotNull($pkg);
        $this->assertSame('slate-pro', $pkg['key']);
        $this->assertSame('slate-pro', $pkg['theme']);

        $this->assertNull(ThemePackage::get('paket-tidak-ada'));
    }

    public function test_apply_to_setting_menulis_theme_dan_color_per_tenant(): void
    {
        $this->boot('paket');

        // Paket standar (bawaan, color null) → color efektif 'bawaan'.
        $this->assertTrue(ThemePackage::applyToSetting('standar-putih'));
        $this->assertSame('bawaan', Setting::getTenantStringValue(PanelTheme::SETTING_THEME));
        $this->assertSame('bawaan', Setting::getTenantStringValue(PanelTheme::SETTING_COLOR));
        $this->assertSame('bawaan', PanelTheme::theme());

        // Paket berpalet penuh (slate-pro) → tema mengikat primernya sendiri.
        $this->assertTrue(ThemePackage::applyToSetting('slate-pro'));
        $this->assertSame('slate-pro', Setting::getTenantStringValue(PanelTheme::SETTING_THEME));
        $this->assertSame('bawaan', Setting::getTenantStringValue(PanelTheme::SETTING_COLOR));
        $this->assertSame('#059669', PanelTheme::primaryHex());

        // Paket top-nav (corporate).
        $this->assertTrue(ThemePackage::applyToSetting('corporate'));
        $this->assertSame('corporate', Setting::getTenantStringValue(PanelTheme::SETTING_THEME));
        $this->assertSame('#2563eb', PanelTheme::primaryHex());
        $this->assertTrue(PanelTheme::topNavigation());

        // Paket tidak dikenal → false, tidak menulis (nilai lama tetap).
        $this->assertFalse(ThemePackage::applyToSetting('paket-hantu'));
        $this->assertSame('corporate', Setting::getTenantStringValue(PanelTheme::SETTING_THEME));

        tenancy()->end();
    }

    public function test_recommended_for_roles_menghitung_irisan(): void
    {
        // Peran bendahara → slate-pro (keuangan) muncul.
        $forBendahara = collect(ThemePackage::recommendedForRoles(['bendahara_pusat']))->pluck('key')->all();
        $this->assertContains('slate-pro', $forBendahara);
        $this->assertNotContains('corporate', $forBendahara);

        // Peran pimpinan → paket korporat.
        $forPimpinan = collect(ThemePackage::recommendedForRoles(['kepala']))->pluck('key')->all();
        $this->assertContains('corporate', $forPimpinan);

        // Kosong / tak match → tak ada.
        $this->assertSame([], ThemePackage::recommendedForRoles([]));
        $this->assertSame([], ThemePackage::recommendedForRoles(['peran_asing']));

        // Helper boolean.
        $this->assertTrue(ThemePackage::isRecommendedForRoles('corporate', ['kepala']));
        $this->assertFalse(ThemePackage::isRecommendedForRoles('corporate', ['kasir']));
        $this->assertFalse(ThemePackage::isRecommendedForRoles('paket-hantu', ['kepala']));
    }

    public function test_active_key_mendeteksi_kombinasi_tersimpan(): void
    {
        // standar-putih = bawaan + bawaan.
        $this->assertSame('standar-putih', ThemePackage::activeKey('bawaan', 'bawaan'));
        // slate-pro + bawaan → paket slate-pro.
        $this->assertSame('slate-pro', ThemePackage::activeKey('slate-pro', 'bawaan'));
        // corporate + bawaan → paket corporate.
        $this->assertSame('corporate', ThemePackage::activeKey('corporate', 'bawaan'));
        // kombinasi acak → null.
        $this->assertNull(ThemePackage::activeKey('tema-hantu', 'bawaan'));
    }

    public function test_thumbnail_svg_valid_dan_memakai_warna_tema(): void
    {
        // Tema corporate → header navy #1e293b harus muncul di SVG.
        $svg = ThemeThumbnail::inlineForTheme('corporate');
        $this->assertStringStartsWith('<svg', $svg);
        $this->assertStringContainsString('#1e293b', $svg);

        // Paket slate-pro → primer emerald #059669.
        $pkgSvg = ThemeThumbnail::inlineForPackage('slate-pro');
        $this->assertStringContainsString('#059669', $pkgSvg);

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
