<?php

namespace Tests\Feature;

use App\Support\TenantCustomizationRegistry;
use Illuminate\Support\Facades\Config;
use Tests\Concerns\InteractsWithPpdbTestDatabase;
use Tests\TestCase;

/**
 * Guard refactor #343 (slice 1): pemilihan komponen landing via
 * TenantCustomizationRegistry harus berperilaku IDENTIK dengan cabang
 * `AlbaKidsTenant::isActive()` lama — albakids → CMS/AlbaKidsLandingV2,
 * tenant lain → default CMS/LandingPage.
 *
 * Pakai createRuntimeTenant (provisioning DB tenant fisik, pola
 * TenantPanelAuthedSmokeTest) — pola createTestTenant tanpa DB tenant
 * tidak jalan di harness lokal DB-per-tenant.
 */
class TenantLandingComponentTest extends TestCase
{
    use InteractsWithPpdbTestDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->ensurePpdbTestingSchemaReady(['tenants', 'domains']);
        $this->truncatePpdbTables(['domains', 'tenants']);
    }

    public function test_tenant_biasa_root_merender_komponen_landing_default(): void
    {
        $tenant = $this->createRuntimeTenant('landing', 'Pesantren Landing');
        $host = $tenant->id.'.localhost';
        tenancy()->end();

        $response = $this
            ->withHeaders([
                'X-Inertia' => 'true',
                'X-Requested-With' => 'XMLHttpRequest',
            ])
            ->call('GET', "http://{$host}/", [], [], [], [
                'HTTP_HOST' => $host,
                'SERVER_NAME' => $host,
            ]);

        $response->assertOk();
        $response->assertSee('CMS\\/LandingPage', false);
        $response->assertDontSee('AlbaKidsLandingV2', false);
    }

    public function test_host_albakids_merender_komponen_landing_v2_via_alias_host_registry(): void
    {
        // Jalur alias host registry — sama dengan cek host lama di
        // AlbaKidsTenant::isActive() ('albakids.pesantren.online').
        $tenant = $this->createRuntimeTenant('landing-ak', 'Alba Kids Pre-School', 'albakids.pesantren.online');
        tenancy()->end();

        $response = $this
            ->withHeaders([
                'X-Inertia' => 'true',
                'X-Requested-With' => 'XMLHttpRequest',
            ])
            ->call('GET', 'http://albakids.pesantren.online/', [], [], [], [
                'HTTP_HOST' => 'albakids.pesantren.online',
                'SERVER_NAME' => 'albakids.pesantren.online',
            ]);

        $response->assertOk();
        $response->assertSee('CMS\\/AlbaKidsLandingV2', false);
    }

    public function test_registry_resolve_komponen_berdasar_tenant_key_saat_tenancy_aktif(): void
    {
        // Jalur tenant-key registry (prioritas di atas host) — sama dengan cek
        // tenant()->getTenantKey() === 'albakids' lama. Entri disuntik untuk key
        // runtime acak agar tidak bentrok DB tenant 'tenantalbakids' antar-run.
        $tenant = $this->createRuntimeTenant('landing-key', 'Pesantren Key');

        tenancy()->initialize($tenant);

        $this->assertSame('CMS/LandingPage', TenantCustomizationRegistry::landingComponent());

        Config::set('tenant-customization.tenants.'.$tenant->getTenantKey(), [
            'landing_component' => 'CMS/AlbaKidsLandingV2',
        ]);

        $this->assertSame('CMS/AlbaKidsLandingV2', TenantCustomizationRegistry::landingComponent());

        tenancy()->end();
    }
}
