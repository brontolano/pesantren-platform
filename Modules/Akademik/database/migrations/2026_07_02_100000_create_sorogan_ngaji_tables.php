<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Issue #330 — Sorogan & Ngaji Kitab.
 *
 * sorogan_setoran   : setoran baca kitab PER SANTRI (sorogan) + penilaian
 *                     kelancaran / ketepatan makna (ngabsahi) / nahwu-i'rab
 *                     (skala 0–100, decimal(5,2) — konsisten tahfidz_penilaian).
 * ngaji_sesi        : sesi kelas massal (bandongan/halaqah) — kitab, bab,
 *                     tanggal, ustadz.
 * ngaji_sesi_peserta: kehadiran + nilai ringkas per santri per sesi.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sorogan_setoran', function (Blueprint $table): void {
            $table->id();
            $table->string('tenant_id');
            $table->foreignId('santri_id')->constrained('santri')->cascadeOnDelete();
            $table->foreignId('kitab_id')->constrained('kitab')->cascadeOnDelete();
            $table->string('bab')->nullable(); // "Bab Thaharah"
            $table->unsignedInteger('halaman_dari')->nullable();
            $table->unsignedInteger('halaman_sampai')->nullable();
            $table->date('tanggal');
            $table->foreignId('ustadz_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('metode', 20)->default('sorogan'); // sorogan|bandongan|halaqah
            $table->decimal('nilai_kelancaran', 5, 2)->nullable(); // 0-100
            $table->decimal('nilai_makna', 5, 2)->nullable(); // ketepatan makna / ngabsahi
            $table->decimal('nilai_nahwu', 5, 2)->nullable(); // kedudukan nahwu / i'rab
            $table->string('status', 20)->default('lanjut'); // lanjut|ulang|khatam
            $table->text('catatan')->nullable(); // catatan ustadz
            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->index('tenant_id');
            $table->index(['tenant_id', 'santri_id', 'tanggal']);
            $table->index(['tenant_id', 'kitab_id']);
        });

        Schema::create('ngaji_sesi', function (Blueprint $table): void {
            $table->id();
            $table->string('tenant_id');
            $table->foreignId('kitab_id')->constrained('kitab')->cascadeOnDelete();
            $table->string('bab')->nullable();
            $table->unsignedInteger('halaman_dari')->nullable();
            $table->unsignedInteger('halaman_sampai')->nullable();
            $table->date('tanggal');
            $table->foreignId('ustadz_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('metode', 20)->default('bandongan'); // bandongan|halaqah
            $table->text('catatan')->nullable();
            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->index('tenant_id');
            $table->index(['tenant_id', 'kitab_id', 'tanggal']);
        });

        Schema::create('ngaji_sesi_peserta', function (Blueprint $table): void {
            $table->id();
            $table->string('tenant_id');
            $table->foreignId('ngaji_sesi_id')->constrained('ngaji_sesi')->cascadeOnDelete();
            $table->foreignId('santri_id')->constrained('santri')->cascadeOnDelete();
            $table->boolean('hadir')->default(true);
            $table->decimal('nilai', 5, 2)->nullable(); // nilai ringkas 0-100
            $table->string('catatan')->nullable();
            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->index('tenant_id');
            $table->unique(['ngaji_sesi_id', 'santri_id']);
            $table->index(['tenant_id', 'santri_id']);
        });

        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::statement("ALTER TABLE sorogan_setoran ADD CONSTRAINT sorogan_setoran_metode_check CHECK (metode IN ('sorogan','bandongan','halaqah'))");
            DB::statement("ALTER TABLE sorogan_setoran ADD CONSTRAINT sorogan_setoran_status_check CHECK (status IN ('lanjut','ulang','khatam'))");
            DB::statement("ALTER TABLE ngaji_sesi ADD CONSTRAINT ngaji_sesi_metode_check CHECK (metode IN ('bandongan','halaqah'))");
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('ngaji_sesi_peserta');
        Schema::dropIfExists('ngaji_sesi');
        Schema::dropIfExists('sorogan_setoran');
    }
};
