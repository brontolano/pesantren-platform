<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\AuditEvent;
use App\Models\ModuleCatalogItem;
use App\Models\PlatformOperator;
use App\Models\TenantModuleEntitlement;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DesignSystemIntegrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_platform_shell_and_page_header_come_from_the_shared_blade_package(): void
    {
        $operator = PlatformOperator::query()->create(['name' => 'Operator Desain', 'email' => 'design@example.test', 'password' => 'test-password', 'is_active' => true]);

        $this->actingAs($operator, 'platform')
            ->get(route('platform.dashboard'))
            ->assertOk()
            ->assertSee('class="pu-shell"', false)
            ->assertSee('class="pu-page-header"', false)
            ->assertSee('data-drawer-toggle', false)
            ->assertSee('Superadmin')
            ->assertSee('class="pu-metric-card"', false)
            ->assertSee('Aktivitas 7 hari terakhir');
    }

    public function test_organization_table_supplies_a_mobile_labelled_card_layout(): void
    {
        $operator = PlatformOperator::query()->create(['name' => 'Operator Desain', 'email' => 'design2@example.test', 'password' => 'test-password', 'is_active' => true]);
        Organization::query()->create([
            'name' => 'Pondok Responsif Fiktif',
            'slug' => 'pondok-responsif-fiktif',
            'organization_type' => 'pesantren',
            'canonical_domain' => 'responsif-pesantren.test',
            'first_admin_email' => 'admin@responsif-pesantren.test',
        ]);

        $this->actingAs($operator, 'platform')
            ->get(route('platform.organizations.index'))
            ->assertOk()
            ->assertSee('class="pu-data-table__desktop"', false)
            ->assertSee('class="pu-data-table__mobile"', false)
            ->assertSee('aria-label="Daftar organisasi: tampilan kartu"', false)
            ->assertSee('Pondok Responsif Fiktif');
    }

    public function test_dashboard_metrics_and_trend_use_real_central_records(): void
    {
        $operator = PlatformOperator::query()->create(['name' => 'Operator Metrik', 'email' => 'metrics@example.test', 'password' => 'test-password', 'is_active' => true]);
        Organization::query()->create([
            'name' => 'Pondok Gagal Metrik', 'slug' => 'pondok-gagal-metrik', 'organization_type' => 'pesantren',
            'canonical_domain' => 'gagal-metrik.test', 'first_admin_email' => 'admin@gagal-metrik.test', 'setup_state' => 'failed',
        ]);
        ModuleCatalogItem::query()->create(['key' => 'santri-metrik', 'name' => 'Data Santri', 'description' => 'Modul uji', 'available' => true, 'implemented' => true]);
        AuditEvent::query()->create(['actor_type' => PlatformOperator::class, 'actor_id' => $operator->id, 'event' => 'test.dashboard_event', 'created_at' => now()]);

        $response = $this->actingAs($operator, 'platform')->get(route('platform.dashboard'))->assertOk();

        $this->assertSame(['total' => 1, 'failed' => 1, 'modules' => 1], $response->viewData('counts'));
        $this->assertSame(7, $response->viewData('trend')->count());
        $this->assertSame(1, $response->viewData('trend')->sum('count'));
        $response->assertSee('Test dashboard event');
    }

    public function test_organization_filters_apply_service_state_and_available_module(): void
    {
        $operator = PlatformOperator::query()->create(['name' => 'Operator Filter', 'email' => 'filter@example.test', 'password' => 'test-password', 'is_active' => true]);
        $module = ModuleCatalogItem::query()->create(['key' => 'keuangan-filter', 'name' => 'Keuangan', 'description' => 'Modul uji', 'available' => true, 'implemented' => true]);
        $matched = Organization::query()->create([
            'name' => 'Pondok Aktif Filter', 'slug' => 'pondok-aktif-filter', 'organization_type' => 'pesantren',
            'canonical_domain' => 'aktif-filter.test', 'first_admin_email' => 'admin@aktif-filter.test', 'service_state' => 'active',
        ]);
        TenantModuleEntitlement::query()->create(['organization_id' => $matched->id, 'module_catalog_item_id' => $module->id]);
        Organization::query()->create([
            'name' => 'Pondok Nonaktif Filter', 'slug' => 'pondok-nonaktif-filter', 'organization_type' => 'pesantren',
            'canonical_domain' => 'nonaktif-filter.test', 'first_admin_email' => 'admin@nonaktif-filter.test', 'service_state' => 'inactive',
        ]);

        $this->actingAs($operator, 'platform')
            ->get(route('platform.organizations.index', ['service_state' => 'active', 'module_id' => $module->id]))
            ->assertOk()
            ->assertSee('Pondok Aktif Filter')
            ->assertDontSee('Pondok Nonaktif Filter')
            ->assertSee('value="active" selected', false)
            ->assertSee('value="'.$module->id.'" selected', false);
    }
}
