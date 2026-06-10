<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * T-A020 — ai_policy + ai_policy_rule: TABEL TENANT (Sharia Guardrails).
 *
 * STRUKTUR SAJA — isi rule keagamaan (preset blacklist per manhaj, dst.)
 * HANYA boleh masuk dari berkas seed yang sudah DITINJAU USTADZ (GATE-3,
 * PRD §12.2). Aturan Emas #8: DILARANG mengarang konten keagamaan di kode.
 *
 * - ai_policy: pengelompokan rule. sumber 'preset' = dari seed GATE-3,
 *   'tenant' = milik pesantren (termasuk mirror blacklist_topik setting).
 * - ai_policy_rule: satu aturan teknis. pola jsonb {keyword: [], regex: [],
 *   param: {}}; respons_template null = pakai template default config
 *   ai.guardrails.* (tenant boleh override per rule).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_policy', function (Blueprint $table) {
            $table->id();
            $table->string('nama');
            $table->string('sumber', 16)->default('tenant'); // preset|tenant
            $table->boolean('aktif')->default(true);
            $table->timestamps();

            $table->index(['sumber', 'aktif']);
        });

        Schema::create('ai_policy_rule', function (Blueprint $table) {
            $table->id();
            $table->foreignId('policy_id')
                ->constrained('ai_policy')
                ->cascadeOnDelete();
            // blacklist_topik|level_fatwa|scope_kanal|jam_layanan|disclaimer
            $table->string('tipe', 32);
            $table->jsonb('pola'); // {keyword: [], regex: [], param: {}}
            // null = template default dari config ai.guardrails.*
            $table->text('respons_template')->nullable();
            $table->integer('prioritas')->default(100);
            $table->boolean('aktif')->default(true);
            $table->timestamps();

            $table->index(['tipe', 'aktif']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_policy_rule');
        Schema::dropIfExists('ai_policy');
    }
};
