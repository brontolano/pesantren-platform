Warning: truncated output (original token count: 2090)
Total output lines: 172

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'central';

    public function up(): void
    {
        Schema::connection('central')->create('platform_operators', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('password');
            $table->boolean('is_active')->default(true);
            $table->boolean('mfa_enabled')->default(false);
            $table->rememberToken();
            $table->timestampsTz();
        });

        Schema::connection('central')->create('organizations', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('organization_type', 32);
            $table->string('canonical_domain')->unique();
            $table->string('setup_state', 24)->default('draft')->index();
            $table->string('service_state', 24)->default('inactive')->index();
            $table->string('first_admin_email');
            $table->uuid('provisioning_key')->unique();
            $table->jsonb('setup_steps')->default('{}');
            $table->string('tenant_database_name')->nullable()->unique();
            $table->string('tenant_database_username')->nullable()->unique();
            $table->text('tenant_database_password')->nullable();
            $table->string('last_setup_error_code')->nullable();
            $table->timestampTz('last_setup_at')->nullable();
            $table->timestampTz('last_technical_check_at')->nullable();
            $table->string('last_technical_check_state', 24)->nullable();
            $table->timestampsTz();
        });

        Schema::connection('central')->create('tenant_domains', function (Blueprint $ta…1090 tokens truncated…
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });

        Schema::connection('central')->create('cache', function (Blueprint $table): void {
            $table->string('key')->primary();
            $table->mediumText('value');
            $table->integer('expiration')->index();
        });
        Schema::connection('central')->create('cache_locks', function (Blueprint $table): void {
            $table->string('key')->primary();
            $table->string('owner');
            $table->integer('expiration')->index();
        });
        Schema::connection('central')->create('password_reset_tokens', function (Blueprint $table): void {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });

        DB::connection('central')->unprepared(<<<'SQL'
            CREATE OR REPLACE FUNCTION prevent_audit_event_mutation() RETURNS trigger
            LANGUAGE plpgsql AS $$
            BEGIN
                RAISE EXCEPTION 'audit_events are append-only';
            END;
            $$;
            CREATE TRIGGER audit_events_no_update_delete
                BEFORE UPDATE OR DELETE ON audit_events
                FOR EACH ROW EXECUTE FUNCTION prevent_audit_event_mutation();
            SQL);
    }

    public function down(): void
    {
        DB::connection('central')->unprepared('DROP TRIGGER IF EXISTS audit_events_no_update_delete ON audit_events');
        DB::connection('central')->unprepared('DROP FUNCTION IF EXISTS prevent_audit_event_mutation()');
        foreach (['password_reset_tokens', 'cache_locks', 'cache', 'sessions', 'technical_checks', 'tenant_module_entitlements', 'module_catalog_items', 'audit_events', 'provisioning_attempts', 'platform_invitations', 'tenant_domains', 'organizations', 'platform_operators'] as $table) {
            Schema::connection('central')->dropIfExists($table);
        }
    }
};
