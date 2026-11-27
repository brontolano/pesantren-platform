<?php

namespace App\Services\Provisioning;

use App\Models\Organization;
use App\Models\PlatformOperator;
use App\Models\PlatformInvitation;
use App\Models\ProvisioningAttempt;
use App\Support\PlatformAudit;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class TenantProvisioningService
{
    public function __construct(
        private readonly TenantDatabaseProvisioner $databases,
        private readonly PlatformAudit $audit,
        private readonly PlatformInvitationService $invitations,
    ) {}

    public function retry(Organization $organization, string $idempotencyKey, PlatformOperator $operator): Organization
    {
        if (! hash_equals((string) $organization->provisioning_key, $idempotencyKey)) {
            abort(409, 'Kunci setup tidak cocok. Muat ulang halaman sebelum mencoba lagi.');
        }

        $central = DB::connection('central');
        $lockKey = 'pesantren-v2-tenant-setup:'.$organization->getKey();
        $usesAdvisoryLock = $central->getDriverName() === 'pgsql';
        if ($usesAdvisoryLock) {
            $central->selectOne('SELECT pg_advisory_lock(hashtext(?))', [$lockKey]);
        }

        try {
            return $this->performRetry($organization, $idempotencyKey, $operator);
        } finally {
            if ($usesAdvisoryLock) {
                $central->selectOne('SELECT pg_advisory_unlock(hashtext(?))', [$lockKey]);
            }
        }
    }

    private function performRetry(Organization $organization, string $idempotencyKey, PlatformOperator $operator): Organization
    {
        $organization->refresh();
        if ($organization->setup_state === 'ready') {
            return $organization->fresh();
        }

        $attempt = DB::connection('central')->transaction(function () use ($organization, $idempotencyKey): ProvisioningAttempt {
            $locked = Organization::query()->lockForUpdate()->findOrFail($organization->id);
            $attempt = ProvisioningAttempt::query()->firstOrCreate(
                ['organization_id' => $locked->id, 'idempotency_key' => $idempotencyKey],
                ['state' => 'running', 'steps' => [], 'started_at' => now()],
            );

            if ($attempt->state === 'ready') {
                return $attempt;
            }

            $locked->forceFill([
                'setup_state' => 'running',
                'last_setup_error_code' => null,
                'setup_steps' => array_merge($locked->setup_steps ?? [], ['registry_reserved' => true]),
            ])->save();
            $attempt->forceFill(['state' => 'running', 'error_code' => null, 'finished_at' => null])->save();

            return $attempt;
        });

        if ($attempt->state === 'ready') {
            return $organization->fresh();
        }

        try {
            $organization->refresh();
            $this->databases->provision($organization);
            $organization->forceFill(['setup_steps' => array_merge($organization->setup_steps ?? [], ['tenant_database' => true])])->save();

            $this->invitations->issueForSetup($organization);

            DB::connection('central')->transaction(function () use ($organization, $attempt): void {
                $locked = Organization::query()->lockForUpdate()->findOrFail($organization->id);
                $locked->forceFill([
                    'setup_state' => 'ready',
                    'last_setup_at' => now(),
                    'last_setup_error_code' => null,
                    'setup_steps' => array_merge($locked->setup_steps ?? [], ['administrator_invited' => true, 'reviewed' => true]),
                ])->save();
                $attempt->forceFill(['state' => 'ready', 'steps' => $locked->setup_steps, 'finished_at' => now()])->save();
            });

            $this->audit->record('organization.setup_ready', $organization->id, ['attempt_id' => $attempt->id], Organization::class, $organization->id, $operator);

            return $organization->fresh();
        } catch (Throwable $exception) {
            DB::connection('central')->transaction(function () use ($organization, $attempt): void {
                $locked = Organization::query()->lockForUpdate()->findOrFail($organization->id);
                $locked->forceFill([
                    'setup_state' => 'failed',
                    'last_setup_error_code' => 'setup_step_failed',
                    'setup_steps' => array_merge($locked->setup_steps ?? [], ['failed_at' => now()->toIso8601String()]),
                ])->save();
                $attempt->forceFill(['state' => 'failed', 'error_code' => 'setup_step_failed', 'steps' => $locked->setup_steps, 'finished_at' => now()])->save();
            });
            $this->audit->record('organization.setup_failed', $organization->id, ['attempt_id' => $attempt->id, 'failure_code' => 'setup_step_failed'], Organization::class, $organization->id, $operator);

            Log::warning('Tenant setup step failed.', [
                'organization_id' => $organization->id,
                'attempt_id' => $attempt->id,
                'failure_code' => 'setup_step_failed',
                'exception_class' => $exception::class,
                'exception_origin' => basename($exception->getFile()).':'.$exception->getLine(),
            ]);

            return $organization->fresh();
        }
    }
}
