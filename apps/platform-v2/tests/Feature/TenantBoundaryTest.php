<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\PlatformInvitation;
use App\Models\TenantDomain;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\Support\FakeTenantDatabaseProvisioner;
use Tests\TestCase;

class TenantBoundaryTest extends TestCase
{
    use RefreshDatabase;

    public function test_invited_admin_claims_one_use_link_and_sees_only_their_organization(): void
    {
        $organization = $this->readyOrganization('demo-pesantren.test', 'Pondok Demo Fiktif');
        $token = str_repeat('x', 64);
        $invitation = PlatformInvitation::query()->create([
            'organization_id' => $organization->id,
            'email' => $organization->first_admin_email,
            'token_hash' => Hash::make($token),
            'expires_at' => now()->addMinutes(30),
        ]);

        $this->post('http://'.$organization->canonical_domain.'/pondok/invitation/'.$token, [
                'name' => 'Admin Demo',
                'password' => 'password-test-1234',
                'password_confirmation' => 'password-test-1234',
            ])
            ->assertRedirect('/pondok/beranda');

        $this->assertNotNull($invitation->fresh()->used_at);
        $this->get('http://'.$organization->canonical_domain.'/pondok/beranda')
            ->assertOk()
            ->assertSee('Pondok Demo Fiktif')
            ->assertSee('Admin Demo')
            ->assertDontSee('data santri');

        $this->post('http://'.$organization->canonical_domain.'/pondok/invitation/'.$token, [
                'name' => 'Admin Kedua',
                'password' => 'password-test-1234',
                'password_confirmation' => 'password-test-1234',
            ])
            ->assertStatus(410);
    }

    public function test_expired_invitation_is_rejected(): void
    {
        $organization = $this->readyOrganization('expired-pesantren.test', 'Pondok Kedaluwarsa Fiktif');
        $token = str_repeat('z', 64);
        PlatformInvitation::query()->create([
            'organization_id' => $organization->id,
            'email' => $organization->first_admin_email,
            'token_hash' => Hash::make($token),
            'expires_at' => now()->subMinute(),
        ]);

        $this->get('http://'.$organization->canonical_domain.'/pondok/invitation/'.$token)
            ->assertStatus(410);
    }

    public function test_tenant_session_cannot_cross_to_another_canonical_domain(): void
    {
        $first = $this->readyOrganization('first-pesantren.test', 'Pondok Pertama Fiktif');
        $second = $this->readyOrganization('second-pesantren.test', 'Pondok Kedua Fiktif');
        $this->createTenantAdmin($first, 'admin@first-pesantren.test', 'password-test-1234');
        $this->createTenantAdmin($second, 'admin@second-pesantren.test', 'password-test-1234');

        $this->post('http://'.$first->canonical_domain.'/pondok/login', ['email' => 'admin@first-pesantren.test', 'password' => 'password-test-1234'])
            ->assertRedirect('/pondok/beranda');

        $this->get('http://'.$second->canonical_domain.'/pondok/beranda')
            ->assertStatus(403);
    }

    public function test_inactive_tenant_admin_cannot_log_in(): void
    {
        $organization = $this->readyOrganization('inactive-pesantren.test', 'Pondok Admin Nonaktif');
        $this->createTenantAdmin($organization, 'admin@inactive-pesantren.test', 'password-test-1234');
        \App\Models\TenantAdmin::query()->where('email', 'admin@inactive-pesantren.test')->update(['is_active' => false]);

        $this->post('http://'.$organization->canonical_domain.'/pondok/login', ['email' => 'admin@inactive-pesantren.test', 'password' => 'password-test-1234'])
            ->assertRedirect('/pondok/login')
            ->assertSessionHasErrors('email');
    }

    public function test_disabling_tenant_admin_revokes_an_existing_session(): void
    {
        $organization = $this->readyOrganization('revoked-pesantren.test', 'Pondok Sesi Dicabut');
        $this->createTenantAdmin($organization, 'admin@revoked-pesantren.test', 'password-test-1234');
        $this->post('http://'.$organization->canonical_domain.'/pondok/login', ['email' => 'admin@revoked-pesantren.test', 'password' => 'password-test-1234'])
            ->assertRedirect('/pondok/beranda');
        \App\Models\TenantAdmin::query()->where('email', 'admin@revoked-pesantren.test')->update(['is_active' => false]);

        $this->get('http://'.$organization->canonical_domain.'/pondok/beranda')
            ->assertForbidden();
    }

    public function test_unavailable_modules_are_denied_server_side(): void
    {
        $organization = $this->readyOrganization('modules-pesantren.test', 'Pondok Modul Fiktif');
        $this->createTenantAdmin($organization, 'admin@modules-pesantren.test', 'password-test-1234');
        \App\Models\ModuleCatalogItem::query()->create(['key' => 'ppdb', 'name' => 'PPDB', 'description' => 'Belum tersedia.', 'available' => false, 'implemented' => false]);

        $this->post('http://'.$organization->canonical_domain.'/pondok/login', ['email' => 'admin@modules-pesantren.test', 'password' => 'password-test-1234'])
            ->assertRedirect('/pondok/beranda');
        $this->get('http://'.$organization->canonical_domain.'/pondok/modul/ppdb')
            ->assertStatus(404);
    }

    private function readyOrganization(string $domain, string $name): Organization
    {
        $organization = Organization::query()->create([
            'name' => $name,
            'slug' => str_replace('.', '-', $domain),
            'organization_type' => 'pesantren',
            'canonical_domain' => $domain,
            'first_admin_email' => 'admin@'.$domain,
            'setup_state' => 'ready',
            'service_state' => 'active',
        ]);
        TenantDomain::query()->create(['organization_id' => $organization->id, 'domain' => $domain, 'is_canonical' => true]);
        $this->app->instance(\App\Services\Provisioning\TenantDatabaseProvisioner::class, new FakeTenantDatabaseProvisioner);
        app(\App\Services\Provisioning\TenantDatabaseProvisioner::class)->provision($organization);

        return $organization->fresh();
    }

    private function createTenantAdmin(Organization $organization, string $email, string $password): void
    {
        $this->get('http://'.$organization->canonical_domain.'/pondok/login')
            ->assertOk();
        \App\Models\TenantAdmin::query()->create(['name' => 'Admin Demo', 'email' => $email, 'password' => $password, 'is_active' => true]);
    }
}
