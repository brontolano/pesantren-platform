<?php

namespace Tests\Feature;

use App\Models\Setting;
use App\Support\PanelTheme;
use Tests\Concerns\InteractsWithPpdbTestDatabase;
use Tests\TestCase;

/**
 * Tema panel per tenant — arsitektur "multi-build CSS + pilih runtime".
 *
 * 5 tema: bawaan (default, build theme.css standar) + 4 TEMA EKSTERNAL asli
 * (brisk/nord/moonlight/neobrutalism) — tiap tema eksternal punya build CSS
 * Vite sendiri ('cssEntry') yang meng-import CSS paket vendor Composer-nya,
 * dipilih per-request oleh ApplyPanelTheme lewat Panel::viteTheme().
 *
 * Skin custom lama (slate-pro/glass/corporate/midnight/warm/aurora/nature/
 * neon) DIHAPUS BERSIH: Setting lama yang masih menyimpannya jatuh mulus ke
 * 'bawaan'. Preset WARNA tetap ada untuk tema tanpa palet.
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
        // Tanpa tenancy: tidak melempar. Default tema SEMUA tenant = 'bawaan'.
        $this->assertSame('bawaan', PanelTheme::theme());
        $this->assertSame('bawaan', PanelTheme::color());

        // 'bawaan' → build CSS standar, tanpa font khusus, tanpa palet.
        $this->assertSame(PanelTheme::DEFAULT_CSS_ENTRY, PanelTheme::cssEntry());
        $this->assertNull(PanelTheme::font());
        $this->assertNull(PanelTheme::primaryHex());
        $this->assertFalse(PanelTheme::topNavigation());
        $this->assertNull(PanelTheme::bodyClass());
        $this->assertSame('', PanelTheme::cssVars());

        // Dengan tenancy tapi Setting kosong: tetap default 'bawaan'.
        $this->boot('tema');
        $this->assertSame('bawaan', PanelTheme::theme());
        $this->assertSame(PanelTheme::DEFAULT_CSS_ENTRY, PanelTheme::cssEntry());
        tenancy()->end();
    }

    public function test_setting_tenant_mengubah_tema_ke_brisk(): void
    {
        $this->boot('tema');

        Setting::updateOrCreate(['key' => PanelTheme::SETTING_THEME], ['value' => ['text' => 'brisk']]);

        $this->assertSame('brisk', PanelTheme::theme());
        // Brisk = build CSS eksternal sendiri + font Kumbh Sans.
        $this->assertSame('resources/css/filament/pesantren/theme-brisk.css', PanelTheme::cssEntry());
        $this->assertSame('Kumbh Sans', PanelTheme::font());
        // Brisk tanpa palet mengikat → primer ikut preset warna (default null).
        $this->assertNull(PanelTheme::primaryHex());
        $this->assertNull(PanelTheme::bodyClass());
        $this->assertFalse(PanelTheme::topNavigation());

        tenancy()->end();
    }

    public function test_tema_nord_berpalet_dan_build_sendiri(): void
    {
        $this->boot('tema');

        Setting::updateOrCreate(['key' => PanelTheme::SETTING_THEME], ['value' => ['text' => 'nord']]);

        $this->assertSame('nord', PanelTheme::theme());
        $this->assertSame('resources/css/filament/pesantren/theme-nord.css', PanelTheme::cssEntry());
        // Nord berpalet penuh → primer biru es mengikat, preset warna tak menimpa.
        $this->assertSame('#88c0d0', PanelTheme::primaryHex());
        Setting::updateOrCreate(['key' => PanelTheme::SETTING_COLOR], ['value' => ['text' => 'navy']]);
        $this->assertSame('#88c0d0', PanelTheme::primaryHex());

        tenancy()->end();
    }

    public function test_tema_moonlight_dan_neobrutalism(): void
    {
        $this->boot('tema');

        Setting::updateOrCreate(['key' => PanelTheme::SETTING_THEME], ['value' => ['text' => 'moonlight']]);
        $this->assertSame('resources/css/filament/pesantren/theme-moonlight.css', PanelTheme::cssEntry());
        $this->assertSame('#82aaff', PanelTheme::primaryHex());

        Setting::updateOrCreate(['key' => PanelTheme::SETTING_THEME], ['value' => ['text' => 'neobrutalism']]);
        $this->assertSame('resources/css/filament/pesantren/theme-neobrutalism.css', PanelTheme::cssEntry());
        $this->assertNull(PanelTheme::primaryHex());

        tenancy()->end();
    }

    public function test_tema_tanpa_palet_masih_bisa_pakai_preset_warna(): void
    {
        $this->boot('tema');

        // Tema 'bawaan': preset warna tetap berlaku (var --tema-* di theme.css).
        Setting::updateOrCreate(['key' => PanelTheme::SETTING_COLOR], ['value' => ['text' => 'navy']]);
        $this->assertSame('#2563eb', PanelTheme::primaryHex());
        $this->assertStringContainsString('--tema-active-bg:#dbeafe;', PanelTheme::cssVars());

        tenancy()->end();
    }

    public function test_nilai_lama_yang_dihapus_jatuh_ke_default_bawaan(): void
    {
        $this->boot('tema');

        // Skin custom LAMA yang sudah dihapus bersih → fallback mulus.
        foreach (['slate-pro', 'glass', 'corporate', 'midnight', 'warm', 'aurora', 'nature', 'neon', 'baitul-maal'] as $lama) {
            Setting::updateOrCreate(['key' => PanelTheme::SETTING_THEME], ['value' => ['text' => $lama]]);
            $this->assertSame('bawaan', PanelTheme::theme(), "Tema lama '{$lama}' harus jatuh ke bawaan");
            $this->assertSame(PanelTheme::DEFAULT_CSS_ENTRY, PanelTheme::cssEntry(), $lama);
        }

        Setting::updateOrCreate(['key' => PanelTheme::SETTING_COLOR], ['value' => ['text' => 'pimpinan-biru']]);
        $this->assertSame('bawaan', PanelTheme::color());
        $this->assertSame('', PanelTheme::cssVars());
        $this->assertNull(PanelTheme::bodyClass());

        tenancy()->end();
    }

    public function test_registry_lima_tema_lengkap_dan_konsisten(): void
    {
        // Tepat 5 tema: bawaan + 4 tema eksternal asli.
        $this->assertCount(5, PanelTheme::THEMES);
        $expected = ['bawaan', 'brisk', 'nord', 'moonlight', 'neobrutalism'];
        $this->assertSame($expected, array_keys(PanelTheme::THEMES));

        foreach (PanelTheme::THEMES as $key => $t) {
            $this->assertArrayHasKey('label', $t, $key);
            $this->assertArrayHasKey('desc', $t, $key);
            $this->assertArrayHasKey('topNav', $t, $key);
            $this->assertArrayHasKey('bodyClass', $t, $key);
            $this->assertArrayHasKey('category', $t, $key);
            $this->assertArrayHasKey('icon', $t, $key);
            $this->assertArrayHasKey('thumbnail', $t, $key);
            $this->assertArrayHasKey('features', $t, $key);
            $this->assertArrayHasKey('typography', $t, $key);

            if ($key === 'bawaan') {
                $this->assertArrayNotHasKey('cssEntry', $t, $key);

                continue;
            }

            // Tema eksternal: WAJIB build CSS Vite sendiri yang filenya ADA
            // (jebakan: entry hilang dari vite.config.js/disk = layar polos).
            $this->assertSame('eksternal', $t['category'], $key);
            $this->assertArrayHasKey('cssEntry', $t, $key);
            $this->assertFileExists(base_path($t['cssEntry']), $key);
            $this->assertStringContainsString(
                $t['cssEntry'],
                file_get_contents(base_path('vite.config.js')),
                "{$key}: cssEntry belum terdaftar di vite.config.js",
            );

            // Palet thumbnail eksplisit (CSS-nya milik vendor, tanpa var --tema-*).
            $this->assertArrayHasKey('thumb', $t, $key);
            foreach (['header', 'active', 'activeFg', 'primary'] as $thumbKey) {
                $this->assertArrayHasKey($thumbKey, $t['thumb'], "{$key} thumb kehilangan {$thumbKey}");
            }

            if (isset($t['primary'])) {
                $this->assertMatchesRegularExpression('/^#[0-9a-f]{6}$/i', (string) $t['primary'], $key);
            }
        }

        // COLORS: setiap preset punya label/primary/vars; non-bawaan wajib var inti.
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

    public function test_css_vendor_tema_eksternal_terpasang(): void
    {
        // Paket Composer tema eksternal harus ada di vendor (composer require).
        foreach ([
            'vendor/filafly/brisk/resources/css/theme.css',
            'vendor/andreia/filament-nord-theme/resources/css/theme.css',
            'vendor/notwonderful/filament-moonlight-theme/resources/css/theme.css',
            'vendor/caresome/filament-neobrutalism-theme/resources/css/theme.css',
        ] as $vendorCss) {
            $this->assertFileExists(base_path($vendorCss));
        }
    }
}
