<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * T-A002 — ai_usage_event: meter pemakaian AI KOPI PER-TENANT (Blueprint §5)
 * untuk dashboard admin tenant. Sumber kebenaran billing tetap ai_credit_ledger
 * di DB central; tabel ini jejak pemakaian yang boleh dilihat tenant sendiri.
 *
 * Path Modules/AI/database/migrations terdaftar di config/tenancy.php →
 * ikut tenants:migrate (dan juga tercipta di central, pola platform yang
 * disengaja — lihat komentar AIServiceProvider).
 *
 * Append-only via trigger PostgreSQL (pola persis ai_audit_log).
 * idempotency_key NOT NULL UNIQUE (pelajaran PR#49).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_usage_event', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->string('agent_key', 64);
            $table->string('channel', 32)->default('internal');
            $table->string('provider', 32);
            $table->string('model', 128);
            $table->string('tier', 16)->default('standar');
            $table->integer('token_masuk')->nullable();
            $table->integer('token_keluar')->nullable();
            $table->decimal('kredit', 14, 4)->default(0);
            $table->boolean('degraded')->default(false);
            $table->string('idempotency_key')->unique(); // NOT NULL (pelajaran PR#49)
            $table->timestamp('created_at')->useCurrent();
            // TANPA updated_at — append-only.

            $table->index(['agent_key', 'created_at']);
            $table->index('created_at');
        });

        if (DB::connection($this->getConnection())->getDriverName() === 'pgsql') {
            DB::unprepared(<<<'SQL'
                CREATE OR REPLACE FUNCTION ai_usage_event_append_only() RETURNS trigger AS $$
                BEGIN
                    RAISE EXCEPTION 'ai_usage_event bersifat append-only: % tidak diizinkan.', TG_OP;
                END;
                $$ LANGUAGE plpgsql;

                CREATE TRIGGER trg_ai_usage_event_append_only
                    BEFORE UPDATE OR DELETE ON ai_usage_event
                    FOR EACH ROW EXECUTE FUNCTION ai_usage_event_append_only();
            SQL);
        }
    }

    public function down(): void
    {
        if (DB::connection($this->getConnection())->getDriverName() === 'pgsql') {
            DB::unprepared('DROP TRIGGER IF EXISTS trg_ai_usage_event_append_only ON ai_usage_event');
            DB::unprepared('DROP FUNCTION IF EXISTS ai_usage_event_append_only()');
        }

        Schema::dropIfExists('ai_usage_event');
    }
};
