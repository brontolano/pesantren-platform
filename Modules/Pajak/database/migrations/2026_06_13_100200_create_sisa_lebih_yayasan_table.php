<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * WP-T5: Sisa lebih yayasan (PMK 68/2020). Surplus pendidikan BEBAS PPh Badan
 * jika diinvestasikan ke sarana pendidikan dalam 4 tahun. Tabel ini melacak
 * surplus per tahun dan alokasi reinvestasinya.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sisa_lebih_yayasan', function (Blueprint $table) {
            $table->id();
            $table->string('tenant_id');
            $table->year('tahun_fiskal');
            $table->decimal('surplus_pendidikan', 15, 2);
            $table->decimal('reinvestasi_teralokasi', 15, 2)->default(0);
            $table->date('batas_reinvestasi');               // tahun_fiskal + 4 tahun
            $table->enum('status', ['terbuka', 'terpenuhi', 'kena_pajak'])->default('terbuka');
            $table->text('catatan')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'tahun_fiskal']);
        });

        Schema::create('alokasi_reinvestasi', function (Blueprint $table) {
            $table->id();
            $table->string('tenant_id');
            $table->foreignId('sisa_lebih_id')->constrained('sisa_lebih_yayasan')->cascadeOnDelete();
            $table->date('tanggal');
            $table->decimal('nominal', 15, 2);
            $table->string('keterangan');                    // "Pembangunan lab komputer", etc.
            $table->string('ref_type')->nullable();          // Jurnal, AsetTetap, etc.
            $table->unsignedBigInteger('ref_id')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('alokasi_reinvestasi');
        Schema::dropIfExists('sisa_lebih_yayasan');
    }
};
