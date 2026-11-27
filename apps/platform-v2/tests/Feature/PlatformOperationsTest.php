<?php

namespace Tests\Feature;

use App\Models\AuditEvent;
use App\Models\Organization;
use App\Models\PlatformOperator;
use App\Models\TenantDomain;
use App\Services\Provisioning\TenantDatabaseProvisioner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Tests\Support\FakeTenantDatabaseProvisioner;
use Tests\TestCase;

class PlatformOperationsTest extends TestCase
{
    use RefreshDatabase;

    public function test_service_state_and_technical_health_are_separate_and_audited(): void
    {
        $operator = PlatformOperator::query()->create(['name' => 'Operator Uji', 'email' => 'operator@example.test', 'password' => 'test-password', 'is_active' => true]);
        $organization = Organization::query()->create([
            'name' => 'Pondok Operasi Fiktif',
            'slug' => 'pondok-operasi-fiktif',
            'organization_type' => 'pesantren',
            'canonical_domain' => 'operasi-pesantren.test',
            'first_admin_email' => 'admin@operasi-pesantren.test',
            'setup_state' => 'ready',
            'service_state' => 'inactive',
        ]);
        TenantDomain::query()->create(['organization_id' => $organization->id, 'domain' => $organization->canonical_domain, 'is_canonical' => true]);
        $this->app->instance(TenantDatabaseProvisioner::class, new FakeTenantDatabaseProvisioner);
        app(TenantDatabaseProvisioner::class)->provision($organization);

        $this->actingAs($operator, 'platform')
            ->post(route('platform.organizations.service-state', $organization), ['service_state' => 'active'])
            ->assertRedirect();

        $this->actingAs($operator, 'platform')
            ->post(route('platform.organizations.check', $organization))
            ->assertRedirect();

        $organization->refresh();
        $this->assertSame('ready', $organization->setup_state);
        $this->assertSame('active', $organization->service_state);
        $this->assertSame('healthy', $organization->last_technical_check_state);
        $this->assertDatabaseHas('audit_events', ['event' => 'organization.service_state_changed', 'organization_id' => $organization->id], 'central');
        $this->assertDatabaseHas('audit_events', ['event' => 'organization.technical_check', 'organization_id' => $organization->id], 'central');

        $event = AuditEvent::query()->firstOrFail();
        $this->expectException(\LogicException::class);
        $event->update(['event' => 'rewritten']);
    }

    public function test_platform_operation_pages_do_not_load_student_records(): void
    {
        $operator = PlatformOperator::query()->create(['name' => 'Operator Uji', 'email' => 'operator2@example.test', 'password' => 'test-password', 'is_active' => true]);
        $this->actingAs($operator, 'platform')
            ->get(route('platform.modules'))
            ->assertOk()
            ->assertDontSee('Nama santri uji');

        $this->assertFalse(\Illuminate\Support\Facades\Schema::connection('central')->hasTable('students'));
        $this->assertFalse(\Illuminate\Support\Facades\Schema::connection('central')->hasTable('santri'));
    }

    public function test_audit_events_cannot_be_rewritten_through_direct_database_queries(): void
    {
        $event = AuditEvent::query()->create([
            'actor_type' => 'system',
            'event' => 'test.append_only',
            'metadata' => [],
            'created_at' => now(),
        ]);

        $this->expectException(QueryException::class);
        DB::connection('central')->table('audit_events')->where('id', $event->id)->update(['event' => 'test.rewritten']);
    }
}