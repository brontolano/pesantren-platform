<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Dokumentasi visual & audit GPS untuk belanja jasa/sarpras (M5).
 * Foto disimpan di disk tenant, disajikan via /_tenant-storage. Anti-manipulasi:
 * kamera langsung + geotag + timestamp server.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('laporan_jasa_dan_barang', function (Blueprint $table) {
            $table->id();
            $table->string('tenant_id');
            $table->unsignedBigInteger('belanja_id')->nullable();
            $table->string('jenis_laporan', 20); // belanja_barang / belanja_jasa
            $table->string('foto_nota')->nullable();
            $table->string('foto_barang')->nullable();
            $table->string('foto_sebelum')->nullable();  // wajib utk jasa/proyek sarpras
            $table->string('foto_sedang')->nullable();
            $table->string('foto_sesudah')->nullable();
            $table->decimal('gps_latitude', 10, 7)->nullable();
            $table->decimal('gps_longitude', 10, 7)->nullable();
            $table->timestamp('diambil_pada')->nullable(); // stempel waktu (anti-manipulasi)
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['tenant_id', 'belanja_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('laporan_jasa_dan_barang');
    }
};
