<?php

namespace Tests\Feature;

use App\Filament\Pages\TenantDashboard;
use App\Filament\Resources\Santri\SantriResource;
use App\Filament\Resources\Users\UserResource;
use App\Filament\Widgets\GreetingBanner;
use App\Filament\Widgets\StatsPesantren;
use App\Providers\Filament\TenantPanelProvider;
use App\Support\PanelTheme;
use App\Support\TenantCustomizationRegistry;
use Filament\Pages\Dashboard;
use Filament\Panel;
use Filament\Widgets\AccountWidget;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;
use Modules\Notifikasi\Filament\Resources\BroadcastWa\BroadcastWaResource;
use Modules\Notifikasi\Filament\Resources\TemplateNotifikasi\TemplateNotifikasiResource;
use Modules\PPDB\Filament\Resources\CalonSantriResource;
use Modules\PPDB\Filament\Resources\TagihanPpdbResource;
use Tests\Concerns\InteractsWithPpdbTestDatabase;
use Tests\TestCase;

/**
 * Guard refactor #343 tahap 3: profil panel Filament dari registry
 * ('panel_profile') harus berperilaku IDENTIK dengan cabang
 * `AlbaKidsTenant::isActive()` lama di TenantPanelProvider —
 * profil 'albakids_ringkas' = panel ringkas (PPDB + WA saja; resource lain
 * TIDAK terdaftar → /admin/* 404 BY DESIGN), profil 'default' = panel penuh.
 *
 * Pakai createRuntimeTenant (pola TenantPanelAuthedSmokeTest); entri config
 * albakids disalin ke tenant-key runtime agar tidak tergantung DB tenant
 * fisik 'tenantalbakids'.
 */
class TenantPanelProfileTest extends TestCase
{
    use InteractsWithPpdbTestDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->ensurePpdbTestingSchemaReady(['tenants', 'domains']);
        $this->truncatePpdbTables(['domains', 'tenants']);
    }

    /** Salin entri albakids dari config ke tenant-key runtime (simulasi tenant white-label). */
    protected function applyAlbaKidsEntryFor(string $tenantKey): void
    {
        $entry = (array) config('tenant-customization.tenants.albakids');
        unset($entry['hosts']);
        Config::set('tenant-customization.tenants.'.$tenantKey, $entry);
    }

    /** Bangun Panel lewat provider — jalur yang sama dengan boot Filament. */
    protected function buildPanel(): Panel
    {
        return (new TenantPanelProvider($this->app))->panel(Panel::make());
    }

    public function test_registry_profil_panel_default_dan_override(): void
    {
        $tenant = $this->createRuntimeTenant('prof-reg', 'Pesantren Profil');
        tenancy()->initialize($tenant);

        $this->assertSame('default', TenantCustomizationRegistry::panelProfile());

        $this->applyAlbaKidsEntryFor((string) $tenant->getTenantKey());

        $this->assertSame('albakids_ringkas', TenantCustomizationRegistry::panelProfile());

        tenancy()->end();
    }

    public function test_alias_host_albakids_meresolusi_profil_ringkas_tanpa_tenancy(): void
    {
        // Jalur alias host — sama dengan cek host lama AlbaKidsTenant::isActive()
        // saat panel dibangun sebelum tenancy terinisialisasi (boot request).
        $original = $this->app['request'];
        $this->app->instance('request', Request::create('http://albakids.pesantren.online/admin'));

        try {
            $this->assertSame('albakids_ringkas', TenantCustomizationRegistry::panelProfile());
            $this->assertSame('bawaan', PanelTheme::theme(), 'AlbaKids default tema bawaan (perilaku lama)');
        } finally {
            $this->app->instance('request', $original);
        }
    }

    public function test_panel_tenant_biasa_tetap_penuh(): void
    {
        $tenant = $this->createRuntimeTenant('prof-def', 'Pesantren Penuh');
        tenancy()->initialize($tenant);

        $panel = $this->buildPanel();
        $resources = array_values($panel->getResources());

        foreach ([
            SantriResource::class,
            UserResource::class,
            CalonSantriResource::class,
            TemplateNotifikasiResource::class,
            BroadcastWaResource::class,
        ] as $resource) {
            $this->assertContains($resource, $resources, "Panel penuh harus memuat {$resource}");
        }

        // TagihanPpdbResource memang TIDAK ada di panel default (hanya profil ringkas).
        $this->assertNotContains(TagihanPpdbResource::class, $resources);

        $this->assertContains(TenantDashboard::class, array_values($panel->getPages()));
        $this->assertContains(StatsPesantren::class, array_values($panel->getWidgets()));

        // Default tema tenant biasa: bawaan (Standar Sidebar Putih, via registry).
        $this->assertSame('bawaan', PanelTheme::theme());

        tenancy()->end();
    }

    public function test_panel_profil_ringkas_identik_perilaku_albakids_lama(): void
    {
        $tenant = $this->createRuntimeTenant('prof-ak', 'Alba Kids Pre-School');
        tenancy()->initialize($tenant);
        $this->applyAlbaKidsEntryFor((string) $tenant->getTenantKey());

        $panel = $this->buildPanel();
        $resources = array_values($panel->getResources());

        // Persis 4 resource surface ringkas (PPDB + WA) — tidak kurang, tidak lebih.
        sort($resources);
        $expected = [
            CalonSantriResource::class,
            TagihanPpdbResource::class,
            TemplateNotifikasiResource::class,
            BroadcastWaResource::class,
        ];
        sort($expected);
        $this->assertSame($expected, $resources);

        // Resource lain TIDAK terdaftar → /admin/* lain 404 BY DESIGN.
        $this->assertNotContains(SantriResource::class, $resources);
        $this->assertNotContains(UserResource::class, $resources);

        // Pages: hanya Dashboard bawaan (tanpa discoverPages).
        $pages = array_values($panel->getPages());
        $this->assertContains(Dashboard::class, $pages);
        $this->assertNotContains(TenantDashboard::class, $pages);

        // Widgets: hanya GreetingBanner + AccountWidget.
        $widgets = array_values($panel->getWidgets());
        $this->assertContains(GreetingBanner::class, $widgets);
        $this->assertContains(AccountWidget::class, $widgets);
        $this->assertNotContains(StatsPesantren::class, $widgets);

        // Default tema profil custom: bawaan (perilaku lama).
        $this->assertSame('bawaan', PanelTheme::theme());

        tenancy()->end();
    }

    public function test_guest_home_login_di_domain_sistem_untuk_tenant_custom(): void
    {
        // Perilaku LauncherController + StaffLoginController via registry
        // ('guest_home' = 'login', 'login_variant' = 'albakids'): tamu di '/'
        // domain 'sistem' langsung melihat form login ber-body-class
        // tenant-albakids (guard gaya asli — perilaku lama AlbaKids).
        $this->withoutVite();

        $tenant = $this->createRuntimeTenant('prof-sys', 'Alba Kids Sistem');
        $host = $tenant->domains()->value('domain');
        $tenant->domains()->update(['type' => 'sistem']);
        $this->applyAlbaKidsEntryFor((string) $tenant->getTenantKey());
        tenancy()->end();

        $response = $this->call('GET', "http://{$host}/", [], [], [], [
            'HTTP_HOST' => $host,
            'SERVER_NAME' => $host,
        ]);

        $response->assertOk();
        $response->assertSee('tenant-albakids', false);
    }
}
