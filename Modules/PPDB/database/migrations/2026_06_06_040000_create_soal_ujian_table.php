<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Bank soal ujian PPDB (admin authoring) — fondasi ujian online + auto-grading.
 * kategori_ujian selaras dengan hasil_ujian.kategori_ujian (1 kategori = 1 paket ujian).
 *
 * tipe 'pg'    : pilihan ganda → punya `pilihan` (opsi) + `kunci_jawaban` → auto-grade.
 * tipe 'essay' : isian → dinilai manual (nilaiUntukJawaban() mengembalikan null).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('soal_ujian', function (Blueprint $table) {
            $table->id();
            $table->string('tenant_id');
            $table->string('kategori_ujian', 100);
            $table->text('pertanyaan');
            $table->string('tipe', 10)->default('pg'); // pg | essay
            $table->json('pilihan')->nullable();        // {"A":"...","B":"..."}
            $table->string('kunci_jawaban', 10)->nullable();
            $table->unsignedInteger('bobot')->default(1);
            $table->boolean('aktif')->default(true);
            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->index(['tenant_id', 'kategori_ujian']);
            $table->index(['tenant_id', 'aktif']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('soal_ujian');
    }
};
