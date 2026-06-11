<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * F4-PWA — Ujian Semester Online: EXTRACT additif dari engine ujian PPDB.
 *
 * - paket_soal: kolom konteks akademik (pelajaran_id NULL = mode PPDB,
 *   perilaku lama UTUH) + kepemilikan (created_by) + bank lembaga.
 * - sesi_ujian_akademik: jadwal sesi per rombel (kelas × paket × periode).
 *   Tabel BARU — sesi_ujian existing milik PPDB punya semantik lain
 *   (rekaman jawaban per calon, bukan jadwal) sehingga TIDAK disentuh.
 * - ujian_attempt: santri_id + sesi_ujian_akademik_id NULLABLE;
 *   calon_santri_id di-drop NOT NULL agar attempt akademik bisa tanpa
 *   calon. Attempt PPDB tetap mengisi calon_santri_id — unique lama
 *   (tenant, calon, paket) tetap berlaku utk PPDB (NULL tak ber-dedup di
 *   Postgres, maka attempt akademik tak tersangkut constraint PPDB).
 * - Unique partial baru: satu attempt per santri per sesi akademik.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('paket_soal') && ! Schema::hasColumn('paket_soal', 'pelajaran_id')) {
            Schema::table('paket_soal', function (Blueprint $table) {
                $table->unsignedBigInteger('pelajaran_id')->nullable()->index();
                $table->unsignedBigInteger('created_by')->nullable();
                $table->boolean('is_bank_lembaga')->default(false);
            });
        }

        if (! Schema::hasTable('sesi_ujian_akademik')) {
            Schema::create('sesi_ujian_akademik', function (Blueprint $table) {
                $table->id();
                $table->string('tenant_id');
                $table->foreignId('paket_soal_id')->constrained('paket_soal')->cascadeOnDelete();
                $table->unsignedBigInteger('pelajaran_id');
                $table->unsignedBigInteger('kelas_id');
                $table->unsignedBigInteger('periode_akademik_id')->nullable();
                $table->string('nama')->nullable();
                $table->timestamp('mulai_at');
                $table->timestamp('selesai_at');
                $table->boolean('is_active')->default(true);
                $table->unsignedBigInteger('created_by')->nullable();
                $table->timestamps();

                $table->index(['tenant_id', 'kelas_id', 'is_active']);
            });
        }

        if (Schema::hasTable('ujian_attempt') && ! Schema::hasColumn('ujian_attempt', 'santri_id')) {
            Schema::table('ujian_attempt', function (Blueprint $table) {
                $table->unsignedBigInteger('santri_id')->nullable()->index();
                $table->unsignedBigInteger('sesi_ujian_akademik_id')->nullable()->index();
            });

            DB::statement('ALTER TABLE ujian_attempt ALTER COLUMN calon_santri_id DROP NOT NULL');

            // Satu attempt per santri per sesi (anti mulai-ulang). Partial:
            // baris PPDB (santri_id NULL) tidak tersentuh.
            DB::statement(
                'CREATE UNIQUE INDEX ujian_attempt_santri_sesi_unique
                 ON ujian_attempt (tenant_id, santri_id, sesi_ujian_akademik_id)
                 WHERE santri_id IS NOT NULL',
            );
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('ujian_attempt', 'santri_id')) {
            DB::statement('DROP INDEX IF EXISTS ujian_attempt_santri_sesi_unique');
            Schema::table('ujian_attempt', function (Blueprint $table) {
                $table->dropColumn(['santri_id', 'sesi_ujian_akademik_id']);
            });
        }

        Schema::dropIfExists('sesi_ujian_akademik');

        if (Schema::hasColumn('paket_soal', 'pelajaran_id')) {
            Schema::table('paket_soal', function (Blueprint $table) {
                $table->dropColumn(['pelajaran_id', 'created_by', 'is_bank_lembaga']);
            });
        }
    }
};
