<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * F0-PWA — Fondasi data PWA Guru: halaqah + keanggotaan riwayat (soft-close).
 *
 * Keanggotaan TIDAK pernah dihapus — keluar dari halaqah = isi left_at.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('halaqah', function (Blueprint $table): void {
            $table->id();
            $table->string('tenant_id');
            $table->string('nama');
            $table->foreignId('musyrif_id')->constrained('users')->restrictOnDelete();
            $table->string('jenis', 20)->default('tahfidz'); // tahfidz|kitab|diniyah
            $table->boolean('aktif')->default(true);
            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->index('tenant_id');
            $table->index(['tenant_id', 'musyrif_id']);
            $table->index(['tenant_id', 'aktif']);
        });

        Schema::create('halaqah_santri', function (Blueprint $table): void {
            $table->id();
            $table->string('tenant_id');
            $table->foreignId('halaqah_id')->constrained('halaqah')->cascadeOnDelete();
            $table->foreignId('santri_id')->constrained('santri')->cascadeOnDelete();
            $table->date('joined_at');
            $table->date('left_at')->nullable(); // NULL = masih anggota (soft-close)
            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->index('tenant_id');
            $table->index(['tenant_id', 'halaqah_id']);
            $table->index(['tenant_id', 'santri_id']);
        });

        Schema::table('tahfidz_hafalan', function (Blueprint $table): void {
            // Tanpa backfill — data lama tetap sah tanpa halaqah.
            $table->foreignId('halaqah_id')->nullable()->constrained('halaqah')->nullOnDelete();
        });

        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::statement("ALTER TABLE halaqah ADD CONSTRAINT halaqah_jenis_check CHECK (jenis IN ('tahfidz','kitab','diniyah'))");
            // Satu keanggotaan AKTIF per santri per halaqah (riwayat tertutup boleh banyak).
            DB::statement('CREATE UNIQUE INDEX halaqah_santri_aktif_unique ON halaqah_santri (halaqah_id, santri_id) WHERE left_at IS NULL');
        }
    }

    public function down(): void
    {
        Schema::table('tahfidz_hafalan', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('halaqah_id');
        });
        Schema::dropIfExists('halaqah_santri');
        Schema::dropIfExists('halaqah');
    }
};
