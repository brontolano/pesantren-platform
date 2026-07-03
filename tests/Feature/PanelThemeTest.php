<?php

namespace Tests\Feature;

use App\Models\Setting;
use App\Support\PanelTheme;
use Tests\Concerns\InteractsWithPpdbTestDatabase;
use Tests\TestCase;

/**
 * Tema panel per tenant. Default tema tenant umum = 'pixio' (dari registry
 * kustomisasi 'panel_theme_default'; tenant custom albakids → 'bawaan'),
 * pilihan tersimpan di Setting, nilai tak dikenal/error → jatuh ke default
 * tema tersebut. Preset WARNA punya resolver terpisah yang tetap fallback
 * ke 'bawaan'.
 */
class PanelThemeTest extends TestCase
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

    public function test_default_pixio_tanpa_setting_dan_tanpa_tenancy(): void
    {
        // Tanpa tenancy: tidak melempar. Default tema tenant umum = 'pixio'
        // (registry 'panel_theme_default'); preset WARNA resolver terpisah
        // tetap fallback 'bawaan'.
        $this->assertSame('pixio', PanelTheme::theme());
        $this->assertSame('bawaan', PanelTheme::color());

        // Pixio berpalet penuh → primer emerald + bodyClass + vars terisi.
        $this->assertSame('#059669', PanelTheme::primaryHex());
        $this->assertFalse(PanelTheme::topNavigation());
        $this->assertSame('tema-pixio', PanelTheme::bodyClass());
        $this->assertStringContainsString('--tema-sidebar-header:#ffffff;', PanelTheme::cssVars());

        // Dengan tenancy tapi Setting kosong: tetap default 'pixio'.
        $this->boot('tema');
        $this->assertSame('pixio', PanelTheme::theme());
        $this->assertSame('bawaan', PanelTheme::color());
        tenancy()->end();
    }

    public function test_setting_tenant_mengubah_tema_dan_warna(): void
    {
        $this->boot('tema');

        Setting::updateOrCreate(['key' => PanelTheme::SETTING_THEME], ['value' => ['text' => 'navigasi-atas']]);
        Setting::updateOrCreate(['key' => PanelTheme::SETTING_COLOR], ['value' => ['text' => 'pimpinan-biru']]);

        $this->assertSame('navigasi-atas', PanelTheme::theme());
        $this->assertTrue(PanelTheme::topNavigation());
        $this->assertSame('tema-navigasi-atas', PanelTheme::bodyClass());

        $this->assertSame('pimpinan-biru', PanelTheme::color());
        $this->assertSame('#1d4ed8', PanelTheme::primaryHex());
        $this->assertStringContainsString('--tema-sidebar-header:#0f2a4a;', PanelTheme::cssVars());
        $this->assertStringContainsString('--tema-active-bg:#dbeafe;', PanelTheme::cssVars());

        tenancy()->end();
    }

    public function test_tema_baitul_maal_membawa_vars_dan_primer_sendiri(): void
    {
        $this->boot('tema');

        Setting::updateOrCreate(['key' => PanelTheme::SETTING_THEME], ['value' => ['text' => 'baitul-maal']]);

        $this->assertSame('baitul-maal', PanelTheme::theme());
        $this->assertSame('tema-baitul-maal', PanelTheme::bodyClass());
        $this->assertFalse(PanelTheme::topNavigation());

        // Preset warna masih bawaan → primer dibawa tema (forest green).
        $this->assertSame('#0A5C36', PanelTheme::primaryHex());
        $this->assertStringContainsString('--tema-sidebar-header:#06351F;', PanelTheme::cssVars());
        $this->assertStringContainsString('--tema-active-fg:#ffffff;', PanelTheme::cssVars());

        // Palet tema MENGIKAT: preset warna apa pun TIDAK menimpa palet
        // tema berpalet penuh — identitas Baitul Maal tetap utuh.
        Setting::updateOrCreate(['key' => PanelTheme::SETTING_COLOR], ['value' => ['text' => 'merah-putih']]);
        $this->assertSame('#0A5C36', PanelTheme::primaryHex());
        $this->assertStringContainsString('--tema-active-bg:rgba(238, 220, 154, 0.15);', PanelTheme::cssVars());
        $this->assertStringContainsString('--tema-sidebar-header:#06351F;', PanelTheme::cssVars());

        // Tema tanpa palet sendiri: preset warna tetap berlaku normal.
        Setting::updateOrCreate(['key' => PanelTheme::SETTING_THEME], ['value' => ['text' => 'kompak']]);
        $this->assertSame('#dc2626', PanelTheme::primaryHex());
        $this->assertStringContainsString('--tema-active-bg:#fee2e2;', PanelTheme::cssVars());

        tenancy()->end();
    }

    public function test_nilai_tak_dikenal_jatuh_ke_default(): void
    {
        $this->boot('tema');

        Setting::updateOrCreate(['key' => PanelTheme::SETTING_THEME], ['value' => ['text' => 'tema-tidak-ada']]);
        Setting::updateOrCreate(['key' => PanelTheme::SETTING_COLOR], ['value' => ['text' => 'warna-aneh']]);

        // Tema tak dikenal → default tema tenant umum 'pixio'; preset warna
        // tak dikenal → resolver warna fallback 'bawaan'.
        $this->assertSame('pixio', PanelTheme::theme());
        $this->assertSame('bawaan', PanelTheme::color());

        // cssVars berasal dari palet pixio (tema berpalet penuh mengikat).
        $this->assertStringContainsString('--tema-active-bg:#ecfdf5;', PanelTheme::cssVars());

        tenancy()->end();
    }

    public function test_registry_lengkap_dan_konsisten(): void
    {
        // Setiap tema punya label/desc/topNav/bodyClass; setiap warna punya
        // label/primary/vars; preset non-bawaan wajib var inti agar theme.css
        // konsisten (header + item aktif light & dark).
        foreach (PanelTheme::THEMES as $key => $t) {
            $this->assertArrayHasKey('label', $t, $key);
            $this->assertArrayHasKey('desc', $t, $key);
            $this->assertArrayHasKey('topNav', $t, $key);
            $this->assertArrayHasKey('bodyClass', $t, $key);
        }

        foreach (PanelTheme::COLORS as $key => $w) {
            $this->assertArrayHasKey('label', $w, $key);
            $this->assertArrayHasKey('primary', $w, $key);
            $this->assertArrayHasKey('vars', $w, $key);

            if ($key === 'bawaan') {
                continue;
            }

            $this->assertMatchesRegularExpression('/^#[0-9a-f]{6}$/i', (string) $w['primary'], $key);
            foreach (['--tema-sidebar-header', '--tema-active-bg', '--tema-active-fg', '--tema-active-dark'] as $var) {
                $this->assertArrayHasKey($var, $w['vars'], "{$key} kehilangan {$var}");
            }
        }
    }
}
