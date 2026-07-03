<?php

namespace Tests\Feature;

use App\Models\Setting;
use App\Support\PanelTheme;
use App\Support\ThemePackage;
use App\Support\ThemeThumbnail;
use Tests\Concerns\InteractsWithPpdbTestDatabase;
use Tests\TestCase;

/**
 * PAKET TEMA siap-pakai: tiap paket merujuk theme/color yang VALID di
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

    public function test_semua_paket_merujuk_theme_dan_color_yang_valid(): void
    {
        $this->assertNotEmpty(ThemePackage::PACKAGES);

        foreach (ThemePackage::PACKAGES as $key => $pkg) {
            $this->assertArrayHasKey('name', $pkg, $key);
            $this->assertArrayHasKey('description', $pkg, $key);
            $this->assertArrayHasKey('theme', $pkg, $key);
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
        }
    }

    public function test_all_dan_get_mengembalikan_key_ter_inline(): void
    {
        $all = ThemePackage::all();
        $this->assertSameSize(ThemePackage::PACKAGES, $all);
        $this->assertArrayHasKey('key', $all[0]);

        $pkg = ThemePackage::get('emerald-saas');
        $this->assertNotNull($pkg);
        $this->assertSame('emerald-saas', $pkg['key']);
        $this->assertSame('pixio', $pkg['theme']);

        $this->assertNull(ThemePackage::get('paket-tidak-ada'));
    }

    public function test_apply_to_setting_menulis_theme_dan_color_per_tenant(): void
    {
        $this->boot('paket');

        // Paket berpalet penuh (baitul-maal, color null) → color efektif 'bawaan'
        $this->assertTrue(ThemePackage::applyToSetting('baitul-maal-digital'));
        $this->assertSame('baitul-maal', Setting::getTenantStringValue(PanelTheme::SETTING_THEME));
        $this->assertSame('bawaan', Setting::getTenantStringValue(PanelTheme::SETTING_COLOR));
        $this->assertSame('baitul-maal', PanelTheme::theme());
        // Tema berpalet penuh mengikat primernya sendiri.
        $this->assertSame('#0A5C36', PanelTheme::primaryHex());

        // Paket dgn color eksplisit (kompak + pimpinan-biru)
        $this->assertTrue(ThemePackage::applyToSetting('kompak-padat'));
        $this->assertSame('kompak', Setting::getTenantStringValue(PanelTheme::SETTING_THEME));
        $this->assertSame('pimpinan-biru', Setting::getTenantStringValue(PanelTheme::SETTING_COLOR));
        $this->assertSame('#1d4ed8', PanelTheme::primaryHex());

        // Paket tidak dikenal → false, tidak menulis (nilai lama tetap)
        $this->assertFalse(ThemePackage::applyToSetting('paket-hantu'));
        $this->assertSame('kompak', Setting::getTenantStringValue(PanelTheme::SETTING_THEME));

        tenancy()->end();
    }

    public function test_recommended_for_roles_menghitung_irisan(): void
    {
        // Peran bendahara → paket keuangan/emerald muncul
        $forBendahara = collect(ThemePackage::recommendedForRoles(['bendahara_pusat']))->pluck('key')->all();
        $this->assertContains('baitul-maal-digital', $forBendahara);
        $this->assertContains('emerald-saas', $forBendahara);
        $this->assertNotContains('pimpinan-navy', $forBendahara);

        // Peran pimpinan → paket pimpinan/navy
        $forPimpinan = collect(ThemePackage::recommendedForRoles(['kepala']))->pluck('key')->all();
        $this->assertContains('pimpinan-navy', $forPimpinan);

        // Kosong / tak match → tak ada
        $this->assertSame([], ThemePackage::recommendedForRoles([]));
        $this->assertSame([], ThemePackage::recommendedForRoles(['peran_asing']));

        // Helper boolean
        $this->assertTrue(ThemePackage::isRecommendedForRoles('pimpinan-navy', ['kepala']));
        $this->assertFalse(ThemePackage::isRecommendedForRoles('pimpinan-navy', ['kasir']));
        $this->assertFalse(ThemePackage::isRecommendedForRoles('paket-hantu', ['kepala']));
    }

    public function test_active_key_mendeteksi_kombinasi_tersimpan(): void
    {
        // baitul-maal + bawaan → paket baitul-maal-digital
        $this->assertSame('baitul-maal-digital', ThemePackage::activeKey('baitul-maal', 'bawaan'));
        // kompak + pimpinan-biru → paket kompak-padat
        $this->assertSame('kompak-padat', ThemePackage::activeKey('kompak', 'pimpinan-biru'));
        // kombinasi acak → null
        $this->assertNull(ThemePackage::activeKey('kompak', 'merah-putih'));
        $this->assertNull(ThemePackage::activeKey('tema-hantu', 'bawaan'));
    }

    public function test_thumbnail_svg_valid_dan_memakai_warna_tema(): void
    {
        // Tema baitul-maal → header hijau gelap #06351F harus muncul di SVG
        $svg = ThemeThumbnail::inlineForTheme('baitul-maal');
        $this->assertStringStartsWith('<svg', $svg);
        $this->assertStringContainsString('#06351F', $svg);

        // Paket pimpinan-navy → tema top-nav (bilah atas), primer navy #1d4ed8
        $pkgSvg = ThemeThumbnail::inlineForPackage('pimpinan-navy');
        $this->assertStringContainsString('#1d4ed8', $pkgSvg);

        // Fallback aman untuk key tak dikenal (pakai palet bawaan, tetap SVG valid)
        $fallback = ThemeThumbnail::inlineForPackage('paket-hantu');
        $this->assertStringStartsWith('<svg', $fallback);

        // Semua tema menghasilkan SVG well-formed (root <svg> + close)
        foreach (array_keys(PanelTheme::THEMES) as $themeKey) {
            $out = ThemeThumbnail::inlineForTheme($themeKey);
            $this->assertStringStartsWith('<svg', $out, $themeKey);
            $this->assertStringContainsString('</svg>', $out, $themeKey);
        }
    }
}
