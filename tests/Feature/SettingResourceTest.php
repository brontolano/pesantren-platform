<?php

namespace Tests\Feature;

use App\Filament\Resources\Settings\SettingResource;
use App\Models\Setting;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class SettingResourceTest extends TestCase
{
    use DatabaseTransactions;

    private Tenant $tenantA;
    private Tenant $tenantB;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);

        $this->tenantA = Tenant::create(['id' => 'asy-syifaa', 'name' => 'Asy-Syifaa', 'is_active' => true]);
        $this->tenantA->domains()->create(['domain' => 'asy-syifaa']);

        $this->tenantB = Tenant::create(['id' => 'demo', 'name' => 'Demo', 'is_active' => true]);
        $this->tenantB->domains()->create(['domain' => 'demo']);
    }

    public function test_super_admin_can_access_setting_resource_routes(): void
    {
        tenancy()->initialize($this->tenantA);

        $user = User::create([
            'name' => 'Super Admin',
            'email' => 'admin@asy-syifaa.localhost',
            'password' => 'password',
        ]);
        $user->assignRole('super_admin');

        $this->actingAs($user);

        $this->assertTrue(SettingResource::canAccess());
        $this->assertTrue(SettingResource::canViewAny());
        $this->assertTrue(Route::has('filament.admin.resources.settings.index'));
        $this->assertTrue(Route::has('filament.admin.resources.settings.create'));
        $this->assertTrue(Route::has('filament.admin.resources.settings.edit'));

        tenancy()->end();
    }

    public function test_setting_resource_only_queries_current_tenant_settings(): void
    {
        tenancy()->initialize($this->tenantA);
        Setting::create([
            'key' => 'branding_name',
            'value' => ['text' => 'Asy-Syifaa'],
        ]);
        tenancy()->end();

        tenancy()->initialize($this->tenantB);
        Setting::create([
            'key' => 'branding_name',
            'value' => ['text' => 'Demo'],
        ]);

        $settings = SettingResource::getEloquentQuery()->get();

        $this->assertCount(1, $settings);
        $this->assertSame('demo', $settings->first()->tenant_id);
        $this->assertSame('Demo', $settings->first()->value['text']);

        tenancy()->end();
    }

    public function test_user_without_manage_settings_permission_cannot_access_setting_resource(): void
    {
        tenancy()->initialize($this->tenantA);

        $user = User::create([
            'name' => 'TU',
            'email' => 'tu@asy-syifaa.localhost',
            'password' => 'password',
        ]);
        $user->assignRole('tu');

        $this->actingAs($user);

        $this->assertFalse(SettingResource::canAccess());
        $this->assertFalse(SettingResource::canViewAny());

        tenancy()->end();
    }
}
