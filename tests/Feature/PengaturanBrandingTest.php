<?php

namespace Tests\Feature;

use App\Filament\Pages\PengaturanBranding;
use App\Models\Setting;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Route;
use Livewire\Livewire;
use Tests\TestCase;

class PengaturanBrandingTest extends TestCase
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

        // Halaman branding hanya terdaftar pada panel tenant.
        Filament::setCurrentPanel(Filament::getPanel('tenant'));
    }

    private function actingAsRole(string $role): User
    {
        $user = User::create([
            'name' => ucfirst($role),
            'email' => $role.'@asy-syifaa.localhost',
            'password' => 'password',
        ]);
        $user->assignRole($role);

        $this->actingAs($user);

        return $user;
    }

    public function test_user_with_manage_settings_can_access_branding_page(): void
    {
        tenancy()->initialize($this->tenantA);
        $this->actingAsRole('super_admin');

        $this->assertTrue(PengaturanBranding::canAccess());
        $this->assertTrue(Route::has('filament.tenant.pages.settings'));

        tenancy()->end();
    }

    public function test_user_without_manage_settings_permission_cannot_access(): void
    {
        tenancy()->initialize($this->tenantA);
        $this->actingAsRole('tu');

        $this->assertFalse(PengaturanBranding::canAccess());

        tenancy()->end();
    }

    public function test_save_persists_branding_for_current_tenant_only(): void
    {
        tenancy()->initialize($this->tenantA);
        $this->actingAsRole('super_admin');

        Livewire::test(PengaturanBranding::class)
            ->fillForm([
                'branding_name' => 'Asy-Syifaa',
                'branding_tagline' => 'Pesantren Tahfidz',
                'branding_primary_color' => '#123456',
                'branding_font' => 'serif',
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame('Asy-Syifaa', Setting::getTenantStringValue('branding_name'));
        $this->assertSame('Pesantren Tahfidz', Setting::getTenantStringValue('branding_tagline'));
        $this->assertSame('#123456', Setting::getTenantStringValue('branding_primary_color'));
        $this->assertSame('serif', Setting::getTenantStringValue('branding_font'));

        tenancy()->end();

        // Tenant lain tidak boleh ikut terisi.
        tenancy()->initialize($this->tenantB);
        $this->assertNull(Setting::getTenantStringValue('branding_name'));
        tenancy()->end();
    }

    public function test_mount_loads_existing_settings_into_form(): void
    {
        tenancy()->initialize($this->tenantA);
        $this->actingAsRole('super_admin');

        Setting::create(['key' => 'branding_name', 'value' => ['text' => 'Nama Tersimpan']]);
        Setting::create(['key' => 'branding_primary_color', 'value' => ['color' => '#abcdef']]);

        Livewire::test(PengaturanBranding::class)
            ->assertFormSet([
                'branding_name' => 'Nama Tersimpan',
                'branding_primary_color' => '#abcdef',
            ]);

        tenancy()->end();
    }
}
