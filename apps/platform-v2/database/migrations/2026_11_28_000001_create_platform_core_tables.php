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

        Schema::connection('central')->create('tenant_domains', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnUpdate()->restrictOnDelete();
            $table->string('domain')->unique();
            $table->boolean('is_canonical')->default(false);
            $table->timestampTz('verified_at')->nullable();
            $table->timestampsTz();
            $table->index(['organization_id', 'is_canonical']);
        });

        Schema::connection('central')->create('platform_invitations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnUpdate()->restrictOnDelete();
            $table->string('email');
            $table->string('token_hash', 255);
            $table->timestampTz('expires_at')->index();
            $table->timestampTz('used_at')->nullable();
            $table->timestampsTz();
            $table->index(['organization_id', 'email', 'used_at']);
        });

        Schema::connection('central')->create('provisioning_attempts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnUpdate()->restrictOnDelete();
            $table->uuid('idempotency_key');
            $table->string('state', 24);
            $table->jsonb('steps')->default('{}');
            $table->string('error_code')->nullable();
            $table->timestampTz('started_at');
            $table->timestampTz('finished_at')->nullable();
            $table->timestampsTz();
            $table->unique(['organization_id', 'idempotency_key']);
        });

        Schema::connection('central')->create('audit_events', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->nullable()->constrained()->cascadeOnUpdate()->restrictOnDelete();
            $table->string('actor_type');
            $table->unsignedBigInteger('actor_id')->nullable();
            $table->string('event');
            $table->string('subject_type')->nullable();
            $table->string('subject_id')->nullable();
            $table->jsonb('metadata')->default('{}');
            $table->timestampTz('created_at')->useCurrent();
            $table->index(['organization_id', 'created_at']);
            $table->index(['actor_type', 'actor_id', 'created_at']);
            $table->index('created_at');
        });

        Schema::connection('central')->create('module_catalog_items', function (Blueprint $table): void {
            $table->id();
            $table->string('key')->unique();
            $table->string('name');
            $table->text('description');
            $table->boolean('available')->default(false);
            $table->boolean('implemented')->default(false);
            $table->timestampsTz();
        });

        Schema::connection('central')->create('tenant_module_entitlements', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnUpdate()->restrictOnDelete();
            $table->foreignId('module_catalog_item_id')->constrained()->cascadeOnUpdate()->restrictOnDelete();
            $table->foreignId('enabled_by')->nullable()->constrained('platform_operators')->nullOnDelete();
            $table->timestampTz('enabled_at')->nullable();
            $table->timestampsTz();
            $table->unique(['organization_id', 'module_catalog_item_id']);
        });

        Schema::connection('central')->create('technical_checks', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnUpdate()->restrictOnDelete();
            $table->string('check_key', 64);
            $table->string('state', 24);
            $table->timestampTz('checked_at');
            $table->string('failure_code')->nullable();
            $table->string('recovery_step')->nullable();
            $table->timestampsTz();
            $table->index(['organization_id', 'checked_at']);
        });

        Schema::connection('central')->create('sessions', function (Blueprint $table): void {
            $table->string('id')->primary();
            $table->foreignId('user_id')->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
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
