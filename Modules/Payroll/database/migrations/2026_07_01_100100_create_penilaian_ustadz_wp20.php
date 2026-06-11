<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * WP-20 — Rapor Ustadz Tahunan: penilaian kualitatif mudir per pegawai.
 *
 * Tabel kecil terpisah (BUKAN reuse rubrik_skor — rubrik_skor ber-FK
 * santri_id NOT NULL, khusus penilaian santri G3-18; menumpang akan merusak
 * semantik FK). skor JSON {kriteria: skor 0..100}. Satu penilaian per
 * pegawai per tahun (unique).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('penilaian_ustadz', function (Blueprint $table): void {
            $table->id();
            $table->string('tenant_id');
            $table->foreignId('employee_id')->constrained('employee')->cascadeOnDelete();
            $table->unsignedSmallInteger('tahun');
            $table->json('skor')->nullable();       // {kriteria: skor}
            $table->text('catatan')->nullable();    // catatan mudir
            $table->foreignId('dinilai_oleh')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->index('tenant_id');
            $table->unique(['tenant_id', 'employee_id', 'tahun']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('penilaian_ustadz');
    }
};
