<?php

namespace Tests\Feature;

use App\Models\Setting;
use App\Support\PanelTheme;
use Tests\Concerns\InteractsWithPpdbTestDatabase;
use Tests\TestCase;

/**
 * Tema panel per tenant. Default SEMUA tenant = 'bawaan' (Standar Sidebar
 * Putih; dari registry kustomisasi 'panel_theme_default'), pilihan tersimpan
 * di Setting, nilai tak dikenal/error → jatuh ke 'bawaan'. Preset WARNA
 * punya resolver terpisah yang juga fallback ke 'bawaan'.
 *
 * 9 tema: bawaan (default) + 8 skin lengkap (slate-pro, glass, corporate,
 * midnight, warm, aurora, nature, neon). Tiap tema non-bawaan mengganti
 * KULIT menyeluruh (font/radius/bayangan/tombol/kartu/input) lewat kontrak
 * var --tema-* — bukan cuma warna.
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
        // Tanpa tenancy: tidak melempar. Default tema SEMUA tenant = 'bawaan';
        // preset WARNA resolver terpisah juga fallback 'bawaan'.
        $this->assertSame('bawaan', PanelTheme::theme());
        $this->assertSame('bawaan', PanelTheme::color());

        // 'bawaan' TANPA palet penuh → primer null, tanpa bodyClass & vars.
        $this->assertNull(PanelTheme::primaryHex());
        $this->assertFalse(PanelTheme::topNavigation());
        $this->assertNull(PanelTheme::bodyClass());
        $this->assertSame('', PanelTheme::cssVars());

        // Dengan tenancy tapi Setting kosong: tetap default 'bawaan'.
        $this->boot('tema');
        $this->assertSame('bawaan', PanelTheme::theme());
        $this->assertSame('bawaan', PanelTheme::color());
        tenancy()->end();
    }

    public function test_setting_tenant_mengubah_tema_ke_slate_pro(): void
    {
        $this->boot('tema');

        Setting::updateOrCreate(['key' => PanelTheme::SETTING_THEME], ['value' => ['text' => 'slate-pro']]);

        $this->assertSame('slate-pro', PanelTheme::theme());
        $this->assertFalse(PanelTheme::topNavigation());
        $this->assertSame('tema-slate-pro', PanelTheme::bodyClass());

        // Slate Pro berpalet penuh → primer emerald + vars terisi.
        $this->assertSame('#059669', PanelTheme::primaryHex());
        $this->assertStringContainsString('--tema-sidebar-header:#ffffff;', PanelTheme::cssVars());
        $this->assertStringContainsString('--tema-active-bg:#ecfdf5;', PanelTheme::cssVars());

        tenancy()->end();
    }

    public function test_tema_corporate_memakai_navigasi_atas(): void
    {
        $this->boot('tema');

        Setting::updateOrCreate(['key' => PanelTheme::SETTING_THEME], ['value' => ['text' => 'corporate']]);

        $this->assertSame('corporate', PanelTheme::theme());
        $this->assertSame('tema-corporate', PanelTheme::bodyClass());
        $this->assertTrue(PanelTheme::topNavigation());
        $this->assertSame('#2563eb', PanelTheme::primaryHex());
        $this->assertStringContainsString('--tema-sidebar-header:#1e293b;', PanelTheme::cssVars());

        tenancy()->end();
    }

    public function test_tema_neon_skin_lengkap_dan_glow(): void
    {
        $this->boot('tema');

        Setting::updateOrCreate(['key' => PanelTheme::SETTING_THEME], ['value' => ['text' => 'neon']]);

        $this->assertSame('neon', PanelTheme::theme());
        $this->assertSame('tema-neon', PanelTheme::bodyClass());
        $this->assertFalse(PanelTheme::topNavigation());
        $this->assertSame('#22d3ee', PanelTheme::primaryHex());

        $vars = PanelTheme::cssVars();
        // Kanvas gelap + aksen neon.
        $this->assertStringContainsString('--tema-sidebar-header:#0a0a12;', $vars);
        // Skin lengkap: font heading Orbitron + bayang tombol glow.
        $this->assertStringContainsString('Orbitron', $vars);
        $this->assertStringContainsString('--tema-btn-shadow:', $vars);

        tenancy()->end();
    }

    public function test_cssvars_memancarkan_kontrak_skin(): void
    {
        $this->boot('tema');

        // Tema berpalet penuh memancarkan var skin (font/radius) ke :root.
        Setting::updateOrCreate(['key' => PanelTheme::SETTING_THEME], ['value' => ['text' => 'glass']]);
        $vars = PanelTheme::cssVars();
        $this->assertStringContainsString('--tema-font-sans:', $vars);
        $this->assertStringContainsString('--tema-radius:1rem;', $vars);
        $this->assertStringContainsString('--tema-btn-radius:', $vars);

        tenancy()->end();
    }

    public function test_tema_berpalet_penuh_mengikat_paletnya(): void
    {
        $this->boot('tema');

        Setting::updateOrCreate(['key' => PanelTheme::SETTING_THEME], ['value' => ['text' => 'warm']]);

        $this->assertSame('warm', PanelTheme::theme());
        $this->assertSame('tema-warm', PanelTheme::bodyClass());
        $this->assertSame('#d97706', PanelTheme::primaryHex());
        $this->assertStringContainsString('--tema-active-bg:#fffbeb;', PanelTheme::cssVars());

        // Palet tema MENGIKAT: preset warna apa pun TIDAK menimpa palet
        // tema berpalet penuh — identitas tema tetap utuh.
        Setting::updateOrCreate(['key' => PanelTheme::SETTING_COLOR], ['value' => ['text' => 'navy']]);
        $this->assertSame('#d97706', PanelTheme::primaryHex());
        $this->assertStringContainsString('--tema-sidebar-header:#fffaf3;', PanelTheme::cssVars());

        // Tema tanpa palet sendiri ('bawaan'): preset warna tetap berlaku.
        Setting::updateOrCreate(['key' => PanelTheme::SETTING_THEME], ['value' => ['text' => 'bawaan']]);
        $this->assertSame('#2563eb', PanelTheme::primaryHex());
        $this->assertStringContainsString('--tema-active-bg:#dbeafe;', PanelTheme::cssVars());

        tenancy()->end();
    }

    public function test_nilai_tak_dikenal_jatuh_ke_default_bawaan(): void
    {
        $this->boot('tema');

        // Termasuk tema LAMA yang sudah dihapus (fallback mulus).
        Setting::updateOrCreate(['key' => PanelTheme::SETTING_THEME], ['value' => ['text' => 'baitul-maal']]);
        Setting::updateOrCreate(['key' => PanelTheme::SETTING_COLOR], ['value' => ['text' => 'pimpinan-biru']]);

        // Tema tak dikenal → default 'bawaan'; preset warna tak dikenal →
        // resolver warna fallback 'bawaan'.
        $this->assertSame('bawaan', PanelTheme::theme());
        $this->assertSame('bawaan', PanelTheme::color());

        // 'bawaan' tanpa palet → cssVars kosong.
        $this->assertSame('', PanelTheme::cssVars());
        $this->assertNull(PanelTheme::bodyClass());

        tenancy()->end();
    }

    public function test_registry_sembilan_tema_lengkap_dan_konsisten(): void
    {
        // Tepat 9 tema: bawaan + 8 skin lengkap.
        $this->assertCount(9, PanelTheme::THEMES);
        $expected = ['bawaan', 'slate-pro', 'glass', 'corporate', 'midnight', 'warm', 'aurora', 'nature', 'neon'];
        $this->assertSame($expected, array_keys(PanelTheme::THEMES));

        // Setiap tema punya metadata inti.
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

            // Tiap tema berpalet penuh (selain bawaan) wajib 4 var inti.
            if ($key === 'bawaan') {
                $this->assertNull($t['bodyClass'], $key);
                $this->assertArrayNotHasKey('primary', $t, $key);

                continue;
            }

            $this->assertArrayHasKey('primary', $t, $key);
            $this->assertMatchesRegularExpression('/^#[0-9a-f]{6}$/i', (string) $t['primary'], $key);
            $this->assertSame('tema-'.$key, $t['bodyClass'], $key);
            foreach (['--tema-sidebar-header', '--tema-sidebar-header-border', '--tema-active-bg', '--tema-active-fg', '--tema-active-dark'] as $var) {
                $this->assertArrayHasKey($var, $t['vars'], "{$key} kehilangan {$var}");
            }

            // KONTRAK SKIN: tiap tema non-bawaan set var kulit inti (SKIN LENGKAP,
            // bukan cuma warna) — font, radius kartu/tombol/input, bayangan,
            // border kartu, bayang tombol, berat tombol/heading.
            foreach ([
                '--tema-font-sans', '--tema-radius', '--tema-btn-radius',
                '--tema-input-radius', '--tema-card-shadow', '--tema-card-border',
                '--tema-btn-shadow', '--tema-btn-weight', '--tema-heading-weight',
            ] as $skinVar) {
                $this->assertArrayHasKey($skinVar, $t['vars'], "{$key} kehilangan skin var {$skinVar}");
                $this->assertNotSame('', trim((string) $t['vars'][$skinVar]), "{$key} skin var {$skinVar} kosong");
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
}
