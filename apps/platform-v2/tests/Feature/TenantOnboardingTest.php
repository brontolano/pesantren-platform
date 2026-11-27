<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\PlatformInvitation;
use App\Models\PlatformOperator;
use App\Models\ProvisioningAttempt;
use App\Models\TenantDomain;
use App\Services\Provisioning\CreateTenantDraft;
use App\Services\Provisioning\TenantDatabaseProvisioner;
use App\Services\Provisioning\TenantProvisioningService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;
use Tests\Support\FakeTenantDatabaseProvisioner;
use Tests\TestCase;

class TenantOnboardingTest extends TestCase
{
    use RefreshDatabase;

    private PlatformOperator $operator;
    private FakeTenantDatabaseProvisioner $provisioner;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        $this->operator = PlatformOperator::query()->create(['name' => 'Operator Fiktif', 'email' => 'operator@example.test', 'password' => 'test-password', 'is_active' => true]);
        $this->provisioner = new FakeTenantDatabaseProvisioner;
        $this->app->instance(TenantDatabaseProvisioner::class, $this->provisioner);
    }

    public function test_draft_normalizes_domain_and_rejects_duplicate_canonical_domain(): void
    {
        $creator = app(CreateTenantDraft::class);
        $organization = $creator->handle($this->draftData(['canonical_domain' => 'DEMO-PESANTREN.test.']));

        $this->assertSame('demo-pesantren.test', $organization->canonical_domain);
        $this->assertSame('draft', $organization->setup_state);
        $this->assertSame('inactive', $organization->service_state);
        $this->assertDatabaseHas('tenant_domains', ['domain' => 'demo-pesantren.test'], 'central');

        try {
            $creator->handle($this->draftData(['canonical_domain' => 'demo-pesantren.test']));
            $this->fail('Canonical duplicate domain should be rejected.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('canonical_domain', $exception->errors());
        }
    }

    public function test_failed_setup_can_resume_for_the_same_domain_database_and_attempt(): void
    {
        $organization = app(CreateTenantDraft::class)->handle($this->draftData());
        $this->provisioner->failNext = true;
        $service = app(TenantProvisioningService::class);

        $failed = $service->retry($organization, $organization->provisioning_key, $this->operator);
        $this->assertSame('failed', $failed->setup_state);
        $this->assertSame('inactive', $failed->service_state);

        $ready = $service->retry($failed, $organization->provisioning_key, $this->operator);
        $this->assertSame('ready', $ready->setup_state);
        $this->assertSame('inactive', $ready->service_state);
        $this->assertSame(2, $this->provisioner->calls);
        $this->assertSame(1, TenantDomain::query()->where('domain', $organization->canonical_domain)->count());
        $this->assertSame(1, ProvisioningAttempt::query()->where('organization_id', $organization->id)->count());
        $this->assertSame(1, PlatformInvitation::query()->where('organization_id', $organization->id)->count());
        $this->assertNotEmpty($ready->tenant_database_name);
    }

    public function test_active_service_state_cannot_be_set_before_setup_is_ready(): void
    {
        $organization = app(CreateTenantDraft::class)->handle($this->draftData());
        $this->actingAs($this->operator, 'platform')
            ->post(route('platform.organizations.service-state', $organization), ['service_state' => 'active'])
            ->assertStatus(409);
        $this->assertSame('inactive', $organization->fresh()->service_state);
    }

    private function draftData(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Pondok Demo Fiktif',
            'organization_type' => 'pesantren',
            'canonical_domain' => 'demo-pesantren.test',
            'first_admin_email' => 'admin@demo-pesantren.test',
        ], $overrides);
    }
}
