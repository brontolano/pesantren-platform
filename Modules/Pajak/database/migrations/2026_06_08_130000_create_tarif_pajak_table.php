<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tarif pajak CONFIGURABLE per tenant (bukan konstanta hardcode) — §14.
 * Default mengikuti PMK 59/2022; dapat diubah saat regulasi berubah / ditinjau penasihat.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tarif_pajak', function (Blueprint $table) {
            $table->id();
            $table->string('tenant_id');
            $table->string('kode', 30);                 // ppn, pph22, pph23_npwp, pph23_non_npwp, pph_final_42
            $table->string('nama');
            $table->decimal('tarif_persen', 5, 2);      // 11.00, 1.50, 2.00, 4.00, 10.00
            $table->decimal('ambang_min', 15, 2)->nullable(); // batas minimum DPP; null = tanpa ambang
            $table->string('dasar')->nullable();        // dasar regulasi
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['tenant_id', 'kode']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tarif_pajak');
    }
};
