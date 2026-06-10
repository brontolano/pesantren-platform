<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Ujian online PPDB: attempt per calon per paket (timer server-side). Guarded. */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('ujian_attempt')) {
            return;
        }

        Schema::create('ujian_attempt', function (Blueprint $table) {
            $table->id();
            $table->string('tenant_id');
            $table->foreignId('calon_santri_id')->constrained('calon_santri')->cascadeOnDelete();
            $table->foreignId('paket_soal_id')->constrained('paket_soal')->cascadeOnDelete();
            $table->json('soal_tampil');                    // [soal_id,...] urutan tampil (subset acak)
            $table->json('jawaban')->nullable();            // {soal_id: "A"|teks essay}
            $table->timestamp('mulai_at');
            $table->timestamp('deadline_at');               // timer SERVER-side
            $table->timestamp('selesai_at')->nullable();
            $table->decimal('nilai_pg', 5, 2)->nullable();
            $table->decimal('nilai_essay', 5, 2)->nullable();
            $table->decimal('nilai_final', 5, 2)->nullable();
            $table->string('status', 20)->default('berjalan'); // berjalan|menunggu_nilai|selesai
            $table->timestamps();

            // Satu attempt per calon per paket (anti mulai-ulang utk nilai lebih baik).
            $table->unique(['tenant_id', 'calon_santri_id', 'paket_soal_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ujian_attempt');
    }
};
