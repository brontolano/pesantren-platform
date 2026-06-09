<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * G2-13 — Mutasi & pelepasan aset (movement + disposal).
 * tipe: mutasi_lokasi (tanpa jurnal) | disposal (posting laba/rugi; WAKAF tanpa P/L).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('mutasi_aset')) {
            return;
        }

        Schema::create('mutasi_aset', function (Blueprint $table) {
            $table->id();
            $table->string('tenant_id');
            $table->unsignedBigInteger('aset_id');
            $table->string('tipe');                    // mutasi_lokasi | disposal
            $table->date('tanggal');
            $table->string('dari_lokasi')->nullable();
            $table->string('ke_lokasi')->nullable();
            $table->decimal('harga_jual', 18, 2)->default(0);   // disposal: penerimaan
            $table->decimal('nilai_buku', 18, 2)->default(0);   // disposal: nilai buku saat lepas
            $table->decimal('laba_rugi', 18, 2)->default(0);    // disposal non-wakaf
            $table->boolean('tanpa_pl')->default(false);        // true → wakaf, tidak diakui P/L
            $table->string('keterangan')->nullable();
            $table->string('status')->default('draft');         // draft | posted
            $table->unsignedBigInteger('jurnal_id')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'aset_id']);
            $table->index(['tenant_id', 'tipe']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mutasi_aset');
    }
};
