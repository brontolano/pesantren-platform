<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * F0-PWA — Jurnal mengajar (berita acara KBM).
 *
 * Satu sesi = jadwal_pelajaran_id (KBM formal) XOR halaqah_id (non-formal).
 * Anti-dobel: unique parsial per kombinasi (Postgres tidak menjaga unique
 * komposit yang memuat kolom NULL — dua baris NULL dianggap berbeda).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('jurnal_mengajar', function (Blueprint $table): void {
            $table->id();
            $table->string('tenant_id');
            $table->foreignId('guru_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('jadwal_pelajaran_id')->nullable()->constrained('jadwal_pelajaran')->cascadeOnDelete();
            $table->foreignId('halaqah_id')->nullable()->constrained('halaqah')->cascadeOnDelete();
            $table->date('tanggal');
            $table->time('jam_mulai')->nullable();
            $table->time('jam_selesai')->nullable();
            $table->text('materi')->nullable();
            $table->string('metode')->nullable();
            $table->unsignedInteger('jumlah_hadir')->default(0);
            $table->string('status', 20)->default('terlaksana'); // terlaksana|kosong|diganti
            $table->foreignId('pengganti_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('catatan')->nullable();
            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->index('tenant_id');
            $table->index(['tenant_id', 'guru_id', 'tanggal']);
        });

        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE jurnal_mengajar ADD CONSTRAINT jurnal_mengajar_konteks_xor_check CHECK (num_nonnulls(jadwal_pelajaran_id, halaqah_id) = 1)');
            DB::statement("ALTER TABLE jurnal_mengajar ADD CONSTRAINT jurnal_mengajar_status_check CHECK (status IN ('terlaksana','kosong','diganti'))");
            DB::statement('CREATE UNIQUE INDEX jurnal_mengajar_guru_jadwal_tanggal_unique ON jurnal_mengajar (tenant_id, guru_id, jadwal_pelajaran_id, tanggal) WHERE jadwal_pelajaran_id IS NOT NULL');
            DB::statement('CREATE UNIQUE INDEX jurnal_mengajar_guru_halaqah_tanggal_unique ON jurnal_mengajar (tenant_id, guru_id, halaqah_id, tanggal) WHERE halaqah_id IS NOT NULL');
        } else {
            Schema::table('jurnal_mengajar', function (Blueprint $table): void {
                $table->unique(['tenant_id', 'guru_id', 'jadwal_pelajaran_id', 'tanggal'], 'jurnal_mengajar_guru_jadwal_tanggal_unique');
                $table->unique(['tenant_id', 'guru_id', 'halaqah_id', 'tanggal'], 'jurnal_mengajar_guru_halaqah_tanggal_unique');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('jurnal_mengajar');
    }
};
