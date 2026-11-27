<?php

namespace App\Services\Provisioning;

use App\Models\Organization;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PDO;
use RuntimeException;

class PostgresTenantDatabaseProvisioner implements TenantDatabaseProvisioner
{
    public function provision(Organization $organization): void
    {
        $suffix = str_replace('-', '', $organization->getKey());
        $prefix = (string) config('tenancy.database_prefix');
        $database = $organization->tenant_database_name ?: $prefix.$suffix;
        $username = $organization->tenant_database_username ?: $prefix.'u_'.$suffix;
        $password = $organization->databasePassword() ?: Str::password(48);
        $this->assertIdentifier($database);
        $this->assertIdentifier($username);

        $organization->tenant_database_name = $database;
        $organization->tenant_database_username = $username;
        $organization->setDatabasePassword($password);
        $organization->save();

        $admin = DB::connection('provisioner');
        $roleExists = $admin->selectOne('SELECT 1 FROM pg_roles WHERE rolname = ?', [$username]);
        if (! $roleExists) {
            $quotedPassword = $admin->getPdo()->quote($password, PDO::PARAM_STR);
            $admin->unprepared('CREATE ROLE "'.$username.'" LOGIN PASSWORD '.$quotedPassword);
        }

        $databaseExists = $admin->selectOne('SELECT 1 FROM pg_database WHERE datname = ?', [$database]);
        if (! $databaseExists) {
            $admin->unprepared('CREATE DATABASE "'.$database.'" OWNER "'.$username.'"');
        }

        Config::set('database.connections.tenant', [
            ...Config::get('database.connections.central'),
            'database' => $database,
            'username' => $username,
            'password' => $password,
        ]);
        DB::purge('tenant');
        DB::connection('tenant')->getPdo();

        $exit = Artisan::call('migrate', [
            '--database' => 'tenant',
            '--path' => 'database/migrations/tenant',
            '--force' => true,
        ]);

        if ($exit !== 0) {
            throw new RuntimeException('Tenant database migration failed.');
        }
    }

    private function assertIdentifier(string $identifier): void
    {
        if (! preg_match('/^[a-zA-Z_][a-zA-Z0-9_]{0,62}$/', $identifier)) {
            throw new RuntimeException('Generated tenant database identifier is invalid.');
        }
    }
}
