<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class AuditLogTest extends TestCase
{
    use DatabaseTransactions;

    private Tenant $tenantA;

    private Tenant $tenantB;

    private User $userA;

    private User $userB;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenantA = Tenant::create(['id' => 'tenant-a', 'name' => 'Tenant A', 'is_active' => true]);
        $this->tenantB = Tenant::create(['id' => 'tenant-b', 'name' => 'Tenant B', 'is_active' => true]);

        $this->userA = User::withoutGlobalScopes()->create([
            'name' => 'User A',
            'email' => 'user@tenant-a.localhost',
            'password' => 'password',
            'tenant_id' => 'tenant-a',
        ]);

        $this->userB = User::withoutGlobalScopes()->create([
            'name' => 'User B',
            'email' => 'user@tenant-b.localhost',
            'password' => 'password',
            'tenant_id' => 'tenant-b',
        ]);
    }

    protected function tearDown(): void
    {
        tenancy()->end();

        parent::tearDown();
    }

    public function test_auth_events_are_written_to_tenant_audit_log(): void
    {
        tenancy()->initialize($this->tenantA);

        event(new Login('web', $this->userA, false));
        event(new PasswordReset($this->userA));
        event(new Logout('web', $this->userA));

        $logs = AuditLog::query()->orderBy('id')->get();

        $this->assertCount(3, $logs);
        $this->assertSame(
            ['auth.login', 'auth.password_reset', 'auth.logout'],
            $logs->pluck('action')->all(),
        );
        $this->assertTrue($logs->every(fn (AuditLog $log) => $log->tenant_id === 'tenant-a'));
        $this->assertTrue($logs->every(fn (AuditLog $log) => $log->user_id === $this->userA->id));
    }

    public function test_audit_log_queries_stay_isolated_per_tenant(): void
    {
        tenancy()->initialize($this->tenantA);
        event(new Login('web', $this->userA, false));
        tenancy()->end();

        tenancy()->initialize($this->tenantB);
        event(new Login('web', $this->userB, true));

        $tenantBLogs = AuditLog::all();
        $allLogs = AuditLog::withoutGlobalScopes()->count();

        $this->assertCount(1, $tenantBLogs);
        $this->assertSame('tenant-b', $tenantBLogs->first()->tenant_id);
        $this->assertSame(2, $allLogs);
    }
}
