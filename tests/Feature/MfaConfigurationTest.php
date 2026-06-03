<?php

namespace Tests\Feature;

use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class MfaConfigurationTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);

        $this->tenant = Tenant::create(['id' => 'asy-syifaa', 'name' => 'Asy-Syifaa', 'is_active' => true]);
        $this->tenant->domains()->create(['domain' => 'asy-syifaa']);
    }

    public function test_filament_registers_profile_and_mfa_provider(): void
    {
        $panel = Filament::getDefaultPanel();

        $this->assertTrue(Route::has('filament.admin.auth.profile'));
        $this->assertTrue($panel->hasProfile());
        $this->assertTrue($panel->hasMultiFactorAuthentication());
        $this->assertArrayHasKey('email_code', $panel->getMultiFactorAuthenticationProviders());
    }

    public function test_super_admin_is_required_to_use_mfa(): void
    {
        tenancy()->initialize($this->tenant);

        $user = User::create([
            'name' => 'Super Admin',
            'email' => 'admin@asy-syifaa.localhost',
            'password' => 'password',
        ]);
        $user->assignRole('super_admin');

        Filament::setCurrentPanel('admin');
        $this->actingAs($user);

        $this->assertTrue(Filament::getCurrentOrDefaultPanel()->isMultiFactorAuthenticationRequired());

        tenancy()->end();
    }

    public function test_user_can_toggle_email_mfa_flag(): void
    {
        tenancy()->initialize($this->tenant);

        $user = User::create([
            'name' => 'Admin',
            'email' => 'admin2@asy-syifaa.localhost',
            'password' => 'password',
        ]);

        $this->assertFalse($user->hasEmailAuthentication());

        $user->toggleEmailAuthentication(true);
        $user->refresh();

        $this->assertTrue($user->hasEmailAuthentication());

        tenancy()->end();
    }
}
