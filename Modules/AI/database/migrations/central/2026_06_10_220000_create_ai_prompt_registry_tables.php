<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * T-A004 — Prompt registry ber-versi & feature flag per tenant. Tabel CENTRAL
 * (blueprint §5: ai_agent / ai_prompt_version / ai_prompt_tenant_override —
 * dikelola operator, rollback tanpa deploy).
 *
 * Kontrak penting:
 *  - HANYA SATU versi 'aktif' per agen → partial unique index PostgreSQL.
 *  - system_prompt SEKALI TULIS (ADR-4: prompt = artefak ber-versi) — versi
 *    baru = baris baru. Ditegakkan via guard model AiPromptVersion (bukan
 *    trigger, karena STATUS memang boleh berubah: draft→aktif→arsip).
 *  - ai_prompt_tenant_override = feature flag per tenant per agen + pin versi.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_agent', function (Blueprint $table): void {
            $table->id();
            $table->string('key')->unique(); // kunci agen, mis. uji-internal
            $table->string('nama');
            $table->text('deskripsi')->nullable();
            $table->boolean('is_active')->default(true);
            $table->string('default_tier')->default('standar');
            $table->timestamps();
        });

        Schema::create('ai_prompt_version', function (Blueprint $table): void {
            $table->id();
            $table->string('agent_key');
            $table->unsignedInteger('versi');
            $table->text('system_prompt');
            $table->text('changelog')->nullable();
            $table->string('status')->default('draft'); // draft|aktif|arsip
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();

            $table->foreign('agent_key')->references('key')->on('ai_agent')->cascadeOnDelete();
            $table->unique(['agent_key', 'versi']);
            $table->index(['agent_key', 'status']);
        });

        // HANYA SATU versi aktif per agen — partial unique index (pgsql).
        DB::statement(
            'CREATE UNIQUE INDEX ai_prompt_version_satu_aktif_per_agen '
            ."ON ai_prompt_version (agent_key) WHERE status = 'aktif'",
        );

        Schema::create('ai_prompt_tenant_override', function (Blueprint $table): void {
            $table->id();
            $table->string('tenant_id');
            $table->string('agent_key');
            // NULL = ikuti versi aktif global; terisi = pin versi tertentu.
            $table->foreignId('prompt_version_id')->nullable()
                ->constrained('ai_prompt_version')->nullOnDelete();
            // Feature flag per tenant per agen (false = agen mati utk tenant ini).
            $table->boolean('enabled')->default(true);
            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->foreign('agent_key')->references('key')->on('ai_agent')->cascadeOnDelete();
            $table->unique(['tenant_id', 'agent_key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_prompt_tenant_override');
        Schema::dropIfExists('ai_prompt_version');
        Schema::dropIfExists('ai_agent');
    }
};
