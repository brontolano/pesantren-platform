<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Jadwal ujian PPDB per kategori (F3/EXAM-1). Dipakai kartu peserta digital
 * untuk menampilkan tanggal/waktu/ruang ujian sesuai kategori peserta.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('jadwal_ujian', function (Blueprint $table) {
            $table->id();
            $table->string('tenant_id');
            $table->string('kategori_ujian', 100);
            $table->date('tanggal');
            $table->time('waktu_mulai')->nullable();
            $table->time('waktu_selesai')->nullable();
            $table->string('ruang')->nullable();
            $table->text('keterangan')->nullable();
            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->index(['tenant_id', 'kategori_ujian']);
            $table->index(['tenant_id', 'tanggal']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('jadwal_ujian');
    }
};
