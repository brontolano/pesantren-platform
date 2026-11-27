<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\PlatformOperator;
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
            ->assertSee('vendor/pesantren-ui/pesantren-ui.css');
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
}
