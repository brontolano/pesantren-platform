<?php

namespace Tests\Feature;

use App\Models\Setting;
use App\Models\Tenant;
use App\Support\PanelTheme;
use App\Support\TenantCustomizationRegistry;
use Illuminate\Support\Facades\Config;
use Tests\Concerns\InteractsWithPpdbTestDatabase;
use Tests\TestCase;

/**
 * Guard refactor #343 tahap 4:
 * 1. Lapisan Setting per-tenant di TenantCustomizationRegistry — urutan
 *    resolve Setting tenant ('customization.{key}') > config per-tenant >
 *    config default, dengan memo per-request (flushSettingMemo utk test).
 * 2. Guard Pixio blade: kelas body dari registry ('body_class'), BUKAN cek
 *    slug tenant — render identik utk tenant albakids vs tenant lain.
 * 3. Default konten Aplikasi (asy-syifaa) satu sumber di registry
 *    (appContentLinks) — identik dengan konstanta duplikat lama.
 */
class TenantCustomizationSettingOverrideTest extends TestCase
{
    use InteractsWithPpdbTestDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->ensurePpdbTestingSchemaReady(['tenants', 'domains', 'settings']);
        $this->truncatePpdbTables(['domains', 'tenants']);
        TenantCustomizationRegistry::flushSettingMemo();
    }

    protected function tearDown(): void
    {
        TenantCustomizationRegistry::flushSettingMemo();
        parent::tearDown();
    }

    /** Salin entri albakids dari config ke tenant-key runtime (simulasi tenant white-label). */
    protected function applyAlbaKidsEntryFor(string $tenantKey): void
    {
        $entry = (array) config('tenant-customization.tenants.albakids');
        unset($entry['hosts']);
        Config::set('tenant-customization.tenants.'.$tenantKey, $entry);
    }

    private function boot(string $prefix): Tenant
    {
        $tenant = $this->createRuntimeTenant($prefix, 'Pesantren '.$prefix);
        tenancy()->initialize($tenant);
        $this->seedTenantRow($tenant);

        return $tenant;
    }

    // ---------------------------------------------------------------
    // 1. Resolve berlapis: Setting > config tenant > config default
    // ---------------------------------------------------------------

    public function test_setting_tenant_menang_atas_config_tenant_dan_default(): void
    {
        $tenant = $this->boot('ovr');
        $this->applyAlbaKidsEntryFor((string) $tenant->getTenantKey());

        // Tanpa Setting: config per-tenant (entri albakids) berlaku.
        $this->assertSame('bawaan', TenantCustomizationRegistry::stringValue('panel_theme_default'));
        $this->assertSame('login', TenantCustomizationRegistry::stringValue('guest_home'));

        // Setting per-tenant menimpa config per-tenant — white-label TANPA deploy.
        Setting::updateOrCreate(['key' => 'customization.panel_theme_default'], ['value' => ['value' => 'pixio']]);
        Setting::updateOrCreate(['key' => 'customization.guest_home'], ['value' => ['value' => 'landing']]);
        TenantCustomizationRegistry::flushSettingMemo();

        $this->assertSame('pixio', TenantCustomizationRegistry::stringValue('panel_theme_default'));
        $this->assertSame('landing', TenantCustomizationRegistry::stringValue('guest_home'));

        // PanelTheme ikut: default tema kini dari Setting override.
        $this->assertSame('pixio', PanelTheme::theme());

        tenancy()->end();
    }

    public function test_tanpa_setting_fallback_config_tenant_lalu_default(): void
    {
        $tenant = $this->boot('fbk');

        // Tenant tanpa entri config & tanpa Setting → default global.
        $this->assertSame('default', TenantCustomizationRegistry::panelProfile());
        $this->assertSame('', TenantCustomizationRegistry::bodyClass());
        $this->assertSame('pixio', TenantCustomizationRegistry::stringValue('panel_theme_default'));

        // Dengan entri config per-tenant → entri menang atas default.
        $this->applyAlbaKidsEntryFor((string) $tenant->getTenantKey());
        $this->assertSame('albakids_ringkas', TenantCustomizationRegistry::panelProfile());
        $this->assertSame('tenant-albakids', TenantCustomizationRegistry::bodyClass());

        tenancy()->end();
    }

    public function test_setting_mentah_tanpa_bungkus_value_juga_terbaca(): void
    {
        $this->boot('raw');

        Setting::updateOrCreate(['key' => 'customization.body_class'], ['value' => 'tenant-albakids']);
        TenantCustomizationRegistry::flushSettingMemo();

        $this->assertSame('tenant-albakids', TenantCustomizationRegistry::bodyClass());

        tenancy()->end();
    }

    public function test_memo_per_request_tidak_query_ulang_dan_flush_membaca_ulang(): void
    {
        $this->boot('memo');

        // Baca pertama (miss) → dimemo.
        $this->assertSame('', TenantCustomizationRegistry::bodyClass());

        Setting::updateOrCreate(['key' => 'customization.body_class'], ['value' => ['value' => 'tenant-albakids']]);

        // Masih nilai memo lama dalam "request" yang sama (tidak query ulang).
        $this->assertSame('', TenantCustomizationRegistry::bodyClass());

        // Flush (akhir request / test) → nilai baru terbaca.
        TenantCustomizationRegistry::flushSettingMemo();
        $this->assertSame('tenant-albakids', TenantCustomizationRegistry::bodyClass());

        tenancy()->end();
    }

    public function test_tanpa_tenancy_registry_tetap_default_tanpa_melempar(): void
    {
        $this->assertSame('', TenantCustomizationRegistry::bodyClass());
        $this->assertSame('default', TenantCustomizationRegistry::panelProfile());
    }

    // ---------------------------------------------------------------
    // 2. Guard Pixio blade — kelas body dari registry, render identik
    // ---------------------------------------------------------------

    public function test_blade_guard_pixio_identik_albakids_vs_tenant_lain(): void
    {
        // Tenant biasa: tanpa kelas tenant-albakids di <body>.
        $this->boot('bla');
        $html = view('tenant.kebijakan-privasi', ['nama' => 'Pesantren Uji', 'isi' => 'Isi kebijakan.'])->render();
        $this->assertStringContainsString('<body class="">', $html);
        tenancy()->end();
        TenantCustomizationRegistry::flushSettingMemo();

        // Tenant dengan entri albakids: kelas kompat CSS tetap 'tenant-albakids'
        // (perilaku identik guard lama `AlbaKidsTenant::isActive()`).
        $tenant = $this->boot('blb');
        $this->applyAlbaKidsEntryFor((string) $tenant->getTenantKey());
        $html = view('tenant.kebijakan-privasi', ['nama' => 'Alba Kids', 'isi' => 'Isi kebijakan.'])->render();
        $this->assertStringContainsString('<body class="tenant-albakids">', $html);
        tenancy()->end();
    }

    // ---------------------------------------------------------------
    // 3. Default konten Aplikasi (asy-syifaa) — satu sumber registry
    // ---------------------------------------------------------------

    public function test_app_content_links_identik_dengan_konstanta_lama(): void
    {
        $links = TenantCustomizationRegistry::appContentLinks();

        // Peta identik dengan AppShellController::DEFAULTS / PengaturanBranding::APP_LINKS lama.
        $this->assertSame([
            'app_profil_pondok_url' => 'https://asy-syifaa.com/profil-pondok-pesantren',
            'app_profil_abuya_url' => 'https://asy-syifaa.com/profil-abuya',
            'app_profil_guru_url' => 'https://asy-syifaa.com/profil-guru',
            'app_struktur_url' => 'https://asy-syifaa.com/struktur-organisasi',
            'app_instagram_url' => 'https://www.instagram.com/ponpes.asysyifaa',
            'app_instagram2_url' => 'https://www.instagram.com/asysyifaapusat/',
            'app_instagram3_url' => 'https://www.instagram.com/syifaalawiyyahofficial/',
            'app_youtube_url' => 'https://www.youtube.com/@AsySyifaaTVOfficial/videos',
            'app_facebook_url' => 'https://www.facebook.com/AsySyifaaTV/live_videos',
            'app_jadwal_kajian_url' => '',
            'app_donasi_url' => '/donasi',
            'app_zakat_wakaf_url' => '',
        ], $links);
    }
}
