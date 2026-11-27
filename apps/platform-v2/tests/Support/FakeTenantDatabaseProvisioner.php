<?php

namespace Tests\Support;

use App\Models\Organization;
use App\Services\Provisioning\TenantDatabaseProvisioner;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class FakeTenantDatabaseProvisioner implements TenantDatabaseProvisioner
{
    public int $calls = 0;
    public bool $failNext = false;

    public function provision(Organization $organization): void
    {
        $this->calls++;
        if ($this->failNext) {
            $this->failNext = false;
            throw new RuntimeException('Synthetic provisioning failure.');
        }

        $directory = storage_path('framework/testing-tenants');
        if (! is_dir($directory) && ! mkdir($directory, 0700, true) && ! is_dir($directory)) {
            throw new RuntimeException('Could not create disposable tenant database directory.');
        }
        $database = $directory.'/tenant-'.$organization->id.'.sqlite';
        if (! is_file($database)) {
            touch($database);
        }

        $organization->forceFill([
            'tenant_database_name' => $database,
            'tenant_database_username' => 'test_tenant_'.$organization->id,
        ])->save();
        $organization->setDatabasePassword('test-only-not-a-secret');
        $organization->save();

        Config::set('database.connections.tenant', [
            'driver' => 'sqlite',
            'database' => $database,
            'prefix' => '',
            'foreign_key_constraints' => true,
        ]);
        DB::purge('tenant');
        $status = Artisan::call('migrate', [
            '--database' => 'tenant',
            '--path' => 'database/migrations/tenant',
            '--force' => true,
        ]);
        if ($status !== 0) {
            throw new RuntimeException('Disposable tenant schema could not be migrated.');
        }
    }
}
