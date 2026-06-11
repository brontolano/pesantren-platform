<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * WP-10 — Rapor Diniyah: snapshot rapor lintas-domain (imtihan, kitab,
 * tahfidz, ubudiyah/akhlak, kehadiran kegiatan, disiplin) per santri per
 * periode. blok = JSON snapshot saat generate (rapor TIDAK live-query agar
 * arsip stabil walau data sumber berubah). Unique (tenant, santri, periode)
 * → generate idempoten via updateOrCreate.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rapor_diniyah', function (Blueprint $table) {
            $table->id();
            $table->string('tenant_id');
            $table->foreignId('santri_id')->constrained('santri')->cascadeOnDelete();
            $table->foreignId('periode_akademik_id')->constrained('periode_akademik')->cascadeOnDelete();
            $table->json('blok'); // ringkasan per blok (lihat RaporDiniyah::BLOK)
            $table->text('catatan_mustahiq')->nullable();
            $table->text('catatan_pengasuh')->nullable();
            $table->timestamp('generated_at')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'santri_id', 'periode_akademik_id'], 'rapor_diniyah_unik');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rapor_diniyah');
    }
};
