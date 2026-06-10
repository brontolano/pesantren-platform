<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * T-A003 — ai_audit_log: jejak forensik APPEND-ONLY per tenant (Blueprint §5).
 *
 * Tabel TENANT pertama modul AI — path Modules/AI/database/migrations terdaftar
 * di config/tenancy.php 'migration_parameters' (tenants:migrate). Mengikuti pola
 * platform (sama dgn Modules/Ledger): juga ikut `php artisan migrate` central —
 * disengaja, bukan bug isolasi (lihat komentar AIServiceProvider).
 *
 * Append-only DITEGAKKAN DI DB via trigger PostgreSQL (Aturan Emas #6):
 * REVOKE UPDATE/DELETE tidak mempan karena aplikasi connect sebagai superuser,
 * maka BEFORE UPDATE OR DELETE → RAISE EXCEPTION. TANPA updated_at.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_audit_log', function (Blueprint $table) {
            // ULID: terurut waktu (cocok log append-only) — modul AI greenfield,
            // tabel bisnis lama tetap bigint, tidak diubah.
            $table->ulid('id')->primary();
            $table->string('tenant_id')->nullable(); // konsisten pola BelongsToTenant modul lain
            $table->unsignedBigInteger('user_id')->nullable(); // tanpa FK: log forensik tidak boleh gugur/berubah krn lifecycle users
            $table->string('agent_key', 64);
            $table->string('channel', 32)->default('internal'); // web/wa/internal/cli
            $table->unsignedBigInteger('prompt_version_id')->nullable(); // registry prompt = T-A004 (central), tanpa FK lintas DB
            $table->text('ringkasan_konteks')->nullable(); // WAJIB sudah terredaksi PII di hulu
            $table->jsonb('keputusan_kebijakan')->nullable(); // hasil PolicyEngine (T-A020)
            $table->jsonb('sumber_sitasi')->nullable(); // sitasi deterministik retriever (T-A021)
            $table->string('provider', 32);
            $table->string('model', 128);
            $table->string('tier', 16)->default('standar');
            $table->boolean('degraded')->default(false);
            $table->integer('token_masuk')->nullable();
            $table->integer('token_keluar')->nullable();
            $table->decimal('biaya_kredit', 12, 4)->default(0);
            $table->jsonb('latency')->nullable(); // breakdown per langkah pipeline: {router_ms, provider_ms, total_ms, ...}
            $table->string('status', 16); // sukses / gagal / ditolak_policy / degradasi
            $table->string('error_ringkas', 500)->nullable();
            $table->string('idempotency_key')->nullable()->unique();
            $table->timestamp('created_at')->useCurrent();
            // TANPA updated_at — append-only.

            $table->index(['agent_key', 'created_at']);
            $table->index(['status', 'created_at']);
            $table->index('created_at');
        });

        // Penegakan append-only di level DB (hanya PostgreSQL; driver lain —
        // mis. sqlite di test unit ringan — dilindungi konvensi model/recorder).
        if (DB::connection($this->getConnection())->getDriverName() === 'pgsql') {
            DB::unprepared(<<<'SQL'
                CREATE OR REPLACE FUNCTION ai_audit_log_append_only() RETURNS trigger AS $$
                BEGIN
                    RAISE EXCEPTION 'ai_audit_log bersifat append-only: % tidak diizinkan.', TG_OP;
                END;
                $$ LANGUAGE plpgsql;

                CREATE TRIGGER trg_ai_audit_log_append_only
                    BEFORE UPDATE OR DELETE ON ai_audit_log
                    FOR EACH ROW EXECUTE FUNCTION ai_audit_log_append_only();
            SQL);
        }
    }

    public function down(): void
    {
        if (DB::connection($this->getConnection())->getDriverName() === 'pgsql') {
            DB::unprepared('DROP TRIGGER IF EXISTS trg_ai_audit_log_append_only ON ai_audit_log');
            DB::unprepared('DROP FUNCTION IF EXISTS ai_audit_log_append_only()');
        }

        Schema::dropIfExists('ai_audit_log');
    }
};
