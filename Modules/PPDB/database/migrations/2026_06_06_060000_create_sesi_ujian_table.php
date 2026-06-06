<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Sesi/percobaan ujian online peserta. Menyimpan jawaban + hasil auto-grade
 * (dari UjianScoringService) per kategori ujian. Status: berlangsung → selesai
 * (auto-grade tersimpan) → dinilai (essay manual sudah dinilai penguji).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sesi_ujian', function (Blueprint $table) {
            $table->id();
            $table->string('tenant_id');
            $table->foreignId('calon_santri_id')->constrained('calon_santri')->cascadeOnDelete();
            $table->string('kategori_ujian', 100);
            $table->string('status', 20)->default('berlangsung');
            $table->json('jawaban')->nullable();        // {soal_id: jawaban}
            $table->unsignedInteger('auto_score')->nullable();
            $table->unsignedInteger('max_auto_score')->nullable();
            $table->json('butuh_manual')->nullable();    // [{soal_id, bobot}]
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->index(['tenant_id', 'calon_santri_id']);
            $table->index(['tenant_id', 'kategori_ujian']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sesi_ujian');
    }
};
