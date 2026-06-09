<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * G3-18 — Presensi kelas harian (santri), rubrik kompetensi berbobot, dan target
 * Tahfidz KPI. Additif & guarded. TIDAK mengubah Nilai/Rapor/Tahfidz yang teruji.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('presensi_santri')) {
            Schema::create('presensi_santri', function (Blueprint $table) {
                $table->id();
                $table->string('tenant_id');
                $table->foreignId('santri_id')->constrained('santri')->cascadeOnDelete();
                $table->unsignedBigInteger('kelas_id')->nullable();
                $table->date('tanggal');
                $table->string('status', 10)->default('hadir'); // hadir/izin/sakit/alpa
                $table->string('keterangan')->nullable();
                $table->unsignedBigInteger('dicatat_oleh')->nullable();
                $table->timestamps();

                $table->unique(['tenant_id', 'santri_id', 'tanggal'], 'presensi_santri_unik');
                $table->index(['tenant_id', 'kelas_id', 'tanggal']);
            });
        }

        if (! Schema::hasTable('rubrik_kriteria')) {
            Schema::create('rubrik_kriteria', function (Blueprint $table) {
                $table->id();
                $table->string('tenant_id');
                $table->string('nama');
                $table->unsignedBigInteger('pelajaran_id')->nullable();
                $table->unsignedBigInteger('periode_akademik_id')->nullable();
                $table->decimal('bobot', 6, 2)->default(1); // bobot relatif
                $table->boolean('is_active')->default(true);
                $table->timestamps();

                $table->index(['tenant_id', 'pelajaran_id', 'periode_akademik_id']);
            });
        }

        if (! Schema::hasTable('rubrik_skor')) {
            Schema::create('rubrik_skor', function (Blueprint $table) {
                $table->id();
                $table->string('tenant_id');
                $table->foreignId('kriteria_id')->constrained('rubrik_kriteria')->cascadeOnDelete();
                $table->foreignId('santri_id')->constrained('santri')->cascadeOnDelete();
                $table->unsignedBigInteger('periode_akademik_id')->nullable();
                $table->decimal('skor', 5, 2)->default(0); // 0..100
                $table->timestamps();

                $table->unique(['tenant_id', 'kriteria_id', 'santri_id', 'periode_akademik_id'], 'rubrik_skor_unik');
                $table->index(['tenant_id', 'santri_id']);
            });
        }

        if (! Schema::hasTable('tahfidz_target')) {
            Schema::create('tahfidz_target', function (Blueprint $table) {
                $table->id();
                $table->string('tenant_id');
                $table->foreignId('santri_id')->constrained('santri')->cascadeOnDelete();
                $table->string('periode', 20)->nullable();   // 2026 / 2026-S1
                $table->unsignedInteger('target_surah')->default(0); // jumlah surah/hafalan ditargetkan
                $table->string('keterangan')->nullable();
                $table->timestamps();

                $table->unique(['tenant_id', 'santri_id', 'periode'], 'tahfidz_target_unik');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('tahfidz_target');
        Schema::dropIfExists('rubrik_skor');
        Schema::dropIfExists('rubrik_kriteria');
        Schema::dropIfExists('presensi_santri');
    }
};
