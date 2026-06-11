<?php

namespace Tests\Feature;

use App\Models\Setting;
use App\Support\PanelTheme;
use Tests\Concerns\InteractsWithPpdbTestDatabase;
use Tests\TestCase;

/**
 * Tema panel per tenant: default 'bawaan' (tampilan saat ini), pilihan
 * tersimpan di Setting, nilai tak dikenal/error → selalu jatuh ke bawaan.
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

    public function test_default_bawaan_tanpa_setting_dan_tanpa_tenancy(): void
    {
        // Tanpa tenancy: tidak melempar, jatuh ke bawaan.
        $this->assertSame('bawaan', PanelTheme::theme());
        $this->assertSame('bawaan', PanelTheme::color());
        $this->assertNull(PanelTheme::primaryHex());
        $this->assertFalse(PanelTheme::topNavigation());
        $this->assertNull(PanelTheme::bodyClass());
        $this->assertSame('', PanelTheme::cssVars());

        // Dengan tenancy tapi Setting kosong: tetap bawaan.
        $this->boot('tema');
        $this->assertSame('bawaan', PanelTheme::theme());
        $this->assertSame('bawaan', PanelTheme::color());
        tenancy()->end();
    }

    public function test_setting_tenant_mengubah_tema_dan_warna(): void
    {
        $this->boot('tema');

        Setting::updateOrCreate(['key' => PanelTheme::SETTING_THEME], ['value' => ['text' => 'navigasi-atas']]);
        Setting::updateOrCreate(['key' => PanelTheme::SETTING_COLOR], ['value' => ['text' => 'laut']]);

        $this->assertSame('navigasi-atas', PanelTheme::theme());
        $this->assertTrue(PanelTheme::topNavigation());
        $this->assertSame('tema-navigasi-atas', PanelTheme::bodyClass());

        $this->assertSame('laut', PanelTheme::color());
        $this->assertSame('#0284c7', PanelTheme::primaryHex());
        $this->assertStringContainsString('--tema-sidebar-header:#075985;', PanelTheme::cssVars());
        $this->assertStringContainsString('--tema-active-bg:#e0f2fe;', PanelTheme::cssVars());

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

        // Pengguna pilih preset warna → preset MENIMPA var & primer tema.
        Setting::updateOrCreate(['key' => PanelTheme::SETTING_COLOR], ['value' => ['text' => 'senja']]);
        $this->assertSame('#ea580c', PanelTheme::primaryHex());
        $this->assertStringContainsString('--tema-active-bg:#ffedd5;', PanelTheme::cssVars());

        tenancy()->end();
    }

    public function test_nilai_tak_dikenal_jatuh_ke_bawaan(): void
    {
        $this->boot('tema');

        Setting::updateOrCreate(['key' => PanelTheme::SETTING_THEME], ['value' => ['text' => 'tema-tidak-ada']]);
        Setting::updateOrCreate(['key' => PanelTheme::SETTING_COLOR], ['value' => ['text' => 'warna-aneh']]);

        $this->assertSame('bawaan', PanelTheme::theme());
        $this->assertSame('bawaan', PanelTheme::color());
        $this->assertSame('', PanelTheme::cssVars());

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
