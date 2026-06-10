<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Tabel CENTRAL ai_model_config — konfigurasi router model per (agent, tier).
 *
 * Path migrasi ini (database/migrations/central) SENGAJA tidak terdaftar di
 * config/tenancy.php sehingga TIDAK ikut tenants:migrate ke DB tenant.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_model_config', function (Blueprint $table): void {
            $table->id();
            $table->string('agent_key')->nullable()->comment('NULL = default untuk tier ini');
            $table->string('tier'); // ekonomis|standar|premium|embedding
            $table->string('provider');
            $table->string('model');
            $table->unsignedInteger('max_context_tokens')->default(4096);
            $table->jsonb('options')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['tier', 'is_active']);
        });

        // Unik per kombinasi (tier, agent_key) TERMASUK baris default
        // (agent_key NULL) — PostgreSQL 15+: NULLS NOT DISTINCT.
        DB::statement(
            'CREATE UNIQUE INDEX ai_model_config_tier_agent_unique '
            .'ON ai_model_config (tier, agent_key) NULLS NOT DISTINCT',
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_model_config');
    }
};
