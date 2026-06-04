<?php

namespace Tests\Concerns;

use App\Models\Tenant;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

trait InteractsWithPpdbTestDatabase
{
    protected static bool $ppdbTestingSchemaReady = false;

    /**
     * @param  array<int, string>  $tables
     */
    protected function truncatePpdbTables(array $tables): void
    {
        $this->configurePpdbTestingDatabase();
        DB::disconnect('pgsql');
        DB::purge('pgsql');
        DB::reconnect('pgsql');

        DB::statement(sprintf(
            'TRUNCATE TABLE %s RESTART IDENTITY CASCADE',
            implode(', ', $tables),
        ));
    }

    protected function configurePpdbTestingDatabase(): void
    {
        Config::set('database.default', 'pgsql');
        Config::set('database.connections.pgsql.host', '127.0.0.1');
        Config::set('database.connections.pgsql.port', '5432');
        Config::set('database.connections.pgsql.database', 'pesantren_platform_test');
        Config::set('database.connections.pgsql.username', 'postgres');
        Config::set('database.connections.pgsql.password', 'secret');
    }

    /**
     * @param  array<int, string>  $requiredTables
     */
    protected function ensurePpdbTestingSchemaReady(array $requiredTables = []): void
    {
        $this->configurePpdbTestingDatabase();

        if (static::$ppdbTestingSchemaReady && $this->allTablesExist($requiredTables)) {
            return;
        }

        DB::disconnect('pgsql');
        DB::purge('pgsql');
        DB::reconnect('pgsql');

        if (! Schema::connection('pgsql')->hasTable('migrations') || ! $this->allTablesExist($requiredTables)) {
            Artisan::call('migrate:fresh', ['--force' => true]);
        }

        static::$ppdbTestingSchemaReady = true;
    }

    /**
     * @param  array<int, string>  $tables
     */
    protected function allTablesExist(array $tables): bool
    {
        $schema = Schema::connection('pgsql');

        foreach ($tables as $table) {
            if (! $schema->hasTable($table)) {
                return false;
            }
        }

        return true;
    }

    protected function createTestTenant(string $id, string $name, ?string $domain = null): Tenant
    {
        $timestamp = CarbonImmutable::now();

        DB::table('tenants')->insert([
            'id' => $id,
            'name' => $name,
            'is_active' => true,
            'data' => json_encode([]),
            'created_at' => $timestamp,
            'updated_at' => $timestamp,
        ]);

        if ($domain !== null) {
            DB::table('domains')->insert([
                'domain' => $domain,
                'tenant_id' => $id,
                'created_at' => $timestamp,
                'updated_at' => $timestamp,
            ]);
        }

        return Tenant::query()->findOrFail($id);
    }
}
