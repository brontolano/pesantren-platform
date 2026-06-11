<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * WP-33 — Lapor Kerusakan & Piket (Sarpras).
 *
 * - laporan_kerusakan: laporan dari PWA Guru (foto WAJIB), siklus
 *   masuk → dikerjakan → selesai; biaya nullable — pengeluaran nyata
 *   DIBUAT MANUAL via modul Keuangan (tidak auto-posting).
 * - area_piket: area dengan kode QR unik per tenant (keyboard-wedge).
 * - ceklis_piket: hasil scan piket; satu baris per petugas+area+tanggal
 *   (scan ulang = update status terakhir).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('laporan_kerusakan', function (Blueprint $table): void {
            $table->id();
            $table->string('tenant_id');
            $table->foreignId('pelapor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('lokasi');
            $table->text('deskripsi');
            $table->string('foto_path')->nullable();
            $table->enum('status', ['masuk', 'dikerjakan', 'selesai'])->default('masuk');
            $table->decimal('biaya', 14, 2)->nullable();
            $table->text('catatan')->nullable();
            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->index(['tenant_id', 'status']);
        });

        Schema::create('area_piket', function (Blueprint $table): void {
            $table->id();
            $table->string('tenant_id');
            $table->string('nama');
            $table->string('kode_qr');
            $table->boolean('aktif')->default(true);
            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->unique(['tenant_id', 'kode_qr'], 'uq_area_piket_kode_qr');
        });

        Schema::create('ceklis_piket', function (Blueprint $table): void {
            $table->id();
            $table->string('tenant_id');
            $table->foreignId('area_id')->constrained('area_piket')->cascadeOnDelete();
            $table->foreignId('petugas_id')->constrained('users')->cascadeOnDelete();
            $table->date('tanggal');
            $table->time('jam');
            $table->enum('status', ['bersih', 'kotor', 'rusak']);
            $table->text('catatan')->nullable();
            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            // Satu ceklis per petugas per area per hari — scan ulang meng-update.
            $table->unique(['tenant_id', 'area_id', 'petugas_id', 'tanggal'], 'uq_ceklis_piket_harian');
            $table->index(['tenant_id', 'tanggal']);
        });

        DB::statement('ALTER TABLE laporan_kerusakan ADD CONSTRAINT chk_laporan_kerusakan_biaya CHECK (biaya IS NULL OR biaya >= 0)');
    }

    public function down(): void
    {
        Schema::dropIfExists('ceklis_piket');
        Schema::dropIfExists('area_piket');
        Schema::dropIfExists('laporan_kerusakan');
    }
};
