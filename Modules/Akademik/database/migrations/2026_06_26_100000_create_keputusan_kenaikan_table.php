<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * WP-07 — Hasil sidang kenaikan kelas per santri per periode.
 * Unique (tenant, santri, periode): satu keputusan per santri per periode.
 * finalized_at NULL = draft sidang; terisi = sudah dieksekusi (idempoten).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('keputusan_kenaikan', function (Blueprint $table) {
            $table->id();
            $table->string('tenant_id');
            $table->foreignId('santri_id')->constrained('santri')->cascadeOnDelete();
            $table->foreignId('periode_akademik_id')->constrained('periode_akademik')->cascadeOnDelete();
            $table->foreignId('kelas_asal_id')->constrained('kelas');
            $table->foreignId('kelas_tujuan_id')->nullable()->constrained('kelas');
            $table->string('rekomendasi'); // naik|tinggal|lulus
            $table->string('keputusan'); // naik|tinggal|lulus
            $table->json('alasan')->nullable();
            $table->text('catatan')->nullable();
            $table->foreignId('diputuskan_oleh')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('finalized_at')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'santri_id', 'periode_akademik_id'], 'keputusan_kenaikan_unik');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('keputusan_kenaikan');
    }
};
