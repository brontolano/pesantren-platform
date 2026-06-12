<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * TER PPh 21 (PP 58/2023, PMK 168/2023) — tabel lookup tarif efektif
 * rata-rata bulanan. Kategori A/B/C berdasarkan status PTKP.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ter_pph21', function (Blueprint $table) {
            $table->id();
            $table->string('tenant_id');
            $table->char('kategori', 1);              // A, B, C
            $table->decimal('batas_bawah', 15, 2);    // bruto bulanan >= ini
            $table->decimal('batas_atas', 15, 2);     // bruto bulanan < ini (999999999999 = tak terbatas)
            $table->decimal('tarif_persen', 5, 2);    // TER%
            $table->string('dasar', 50)->default('PP 58/2023');
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['tenant_id', 'kategori', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ter_pph21');
    }
};
