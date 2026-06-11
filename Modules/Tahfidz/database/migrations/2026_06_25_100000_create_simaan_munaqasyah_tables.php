<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * WP-11 — Tahfidz Lanjutan: sima'an (penyimakan hafalan, rentang bebas
 * juz/surah-ayat) + munaqasyah (ujian kelulusan juz, gerbang daftar wisuda).
 *
 * client_ref: idempotensi entri dari PWA Guru (pola WP-32 / F1-PWA setoran) —
 * unique (tenant_id, client_ref); NULL-safe di Postgres karena unique index
 * standar mengizinkan banyak NULL (entri dari panel admin tanpa client_ref
 * tidak saling bentrok).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sima_an', function (Blueprint $table): void {
            $table->id();
            $table->string('tenant_id');
            $table->foreignId('santri_id')->constrained('santri')->cascadeOnDelete();
            // Rentang bebas: "Juz 1-5", "Al-Baqarah 1-141", dst.
            $table->string('rentang');
            $table->foreignId('penyimak_id')->constrained('users')->cascadeOnDelete();
            $table->smallInteger('nilai_kelancaran')->nullable();
            $table->smallInteger('nilai_tajwid')->nullable();
            $table->text('catatan')->nullable();
            $table->date('tanggal');
            $table->string('client_ref', 191)->nullable();
            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->index(['tenant_id', 'santri_id', 'tanggal']);
            $table->unique(['tenant_id', 'client_ref'], 'sima_an_client_ref_unique');
        });

        Schema::create('munaqasyah', function (Blueprint $table): void {
            $table->id();
            $table->string('tenant_id');
            $table->foreignId('santri_id')->constrained('santri')->cascadeOnDelete();
            $table->string('juz_diuji');
            $table->foreignId('penguji_id')->constrained('users')->cascadeOnDelete();
            $table->string('hasil'); // lulus | mengulang (CHECK di bawah)
            $table->decimal('nilai', 5, 2)->nullable();
            $table->date('tanggal');
            $table->foreignId('periode_akademik_id')->nullable()->constrained('periode_akademik')->nullOnDelete();
            $table->boolean('is_peserta_wisuda')->default(false);
            $table->string('client_ref', 191)->nullable();
            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->index(['tenant_id', 'santri_id']);
            $table->index(['tenant_id', 'periode_akademik_id', 'is_peserta_wisuda'], 'munaqasyah_wisuda_index');
            $table->unique(['tenant_id', 'client_ref'], 'munaqasyah_client_ref_unique');
        });

        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::statement("ALTER TABLE munaqasyah ADD CONSTRAINT munaqasyah_hasil_check CHECK (hasil IN ('lulus','mengulang'))");
            DB::statement('ALTER TABLE sima_an ADD CONSTRAINT sima_an_nilai_kelancaran_check CHECK (nilai_kelancaran IS NULL OR nilai_kelancaran BETWEEN 0 AND 100)');
            DB::statement('ALTER TABLE sima_an ADD CONSTRAINT sima_an_nilai_tajwid_check CHECK (nilai_tajwid IS NULL OR nilai_tajwid BETWEEN 0 AND 100)');
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('munaqasyah');
        Schema::dropIfExists('sima_an');
    }
};
