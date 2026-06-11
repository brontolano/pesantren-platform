<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * WP-16 — Kontrak, SK & Pengabdian.
 *
 * Arsip kontrak/SK per pegawai (sk_gty | kontrak | sk_pengabdian | sk_dpk).
 * Perpanjangan = baris BARU bertaut parent_id (rantai), baris lama
 * status 'diperpanjang' dan arsip file-nya UTUH (tidak pernah ditimpa).
 *
 * last_reminded_at = guard idempotensi pengingat kontrak habis per hari
 * (command payroll:cek-kontrak-habis tidak spam notifikasi dobel).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('kontrak_kerja', function (Blueprint $table) {
            $table->id();
            $table->string('tenant_id');
            $table->foreignId('employee_id')->constrained('employee')->cascadeOnDelete();
            $table->string('jenis', 30);
            $table->string('nomor');
            $table->date('mulai');
            $table->date('selesai')->nullable();
            $table->string('file')->nullable(); // arsip PDF/scan — serve via /_tenant-storage
            $table->string('status', 20)->default('aktif');
            $table->foreignId('parent_id')->nullable()->constrained('kontrak_kerja')->nullOnDelete();
            $table->string('periode_khidmat')->nullable(); // angkatan pengabdian, mis. "2026/2027"
            $table->string('lembaga_pengirim')->nullable(); // utk sk_dpk / pengabdian kiriman
            $table->date('tanggal_purna')->nullable();      // purna pengabdian (hint WP-03, tanpa auto-create)
            $table->date('last_reminded_at')->nullable();   // guard pengingat per hari
            $table->timestamps();

            $table->index(['tenant_id', 'employee_id']);
            $table->index(['tenant_id', 'status', 'selesai']);
        });

        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::statement("ALTER TABLE kontrak_kerja ADD CONSTRAINT kontrak_kerja_jenis_check CHECK (jenis IN ('sk_gty','kontrak','sk_pengabdian','sk_dpk'))");
            DB::statement("ALTER TABLE kontrak_kerja ADD CONSTRAINT kontrak_kerja_status_check CHECK (status IN ('aktif','berakhir','diperpanjang'))");
            DB::statement('ALTER TABLE kontrak_kerja ADD CONSTRAINT kontrak_kerja_tanggal_check CHECK (selesai IS NULL OR selesai >= mulai)');
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('kontrak_kerja');
    }
};
