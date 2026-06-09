<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * G2-16 — Skema Biaya (Pricing Rule). Tarif baku per (tahun ajaran, divisi/
 * program, jenis tagihan) sebagai sumber generate Tagihan. Additif & tenant-scoped.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('skema_biaya', function (Blueprint $table) {
            $table->id();
            $table->string('tenant_id');
            $table->string('tahun_ajaran', 20);                 // 2026/2027
            $table->foreignId('divisi_id')->nullable()->constrained('divisi_unit')->nullOnDelete(); // program/jenjang
            $table->string('jenis_tagihan', 40);                // spp, daftar_ulang, seragam, ...
            $table->decimal('nominal', 15, 2);
            $table->foreignId('sumber_dana_id')->nullable()->constrained('sumber_dana')->nullOnDelete(); // dana penanggung
            $table->string('keterangan')->nullable();
            $table->boolean('aktif')->default(true);
            $table->timestamps();

            // Satu tarif per (tahun, divisi, jenis). divisi null = berlaku umum.
            $table->unique(['tenant_id', 'tahun_ajaran', 'divisi_id', 'jenis_tagihan'], 'skema_biaya_unik');
            $table->index(['tenant_id', 'tahun_ajaran', 'aktif']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('skema_biaya');
    }
};
