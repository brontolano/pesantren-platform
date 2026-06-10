<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Bank Soal PPDB (authoring): paket_soal + soal (PG berkunci / essay).
 * Additif & guarded. Ujian online (attempt + grading) menyusul terpisah.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('paket_soal')) {
            Schema::create('paket_soal', function (Blueprint $table) {
                $table->id();
                $table->string('tenant_id');
                $table->string('nama');
                $table->string('kategori_ujian', 40)->default('Tulis'); // selaras hasil_ujian.kategori
                $table->unsignedSmallInteger('durasi_menit')->default(60);
                $table->unsignedSmallInteger('jumlah_tampil')->nullable(); // subset acak; null = semua
                $table->boolean('is_active')->default(true);
                $table->timestamps();

                $table->index(['tenant_id', 'is_active']);
            });
        }

        if (! Schema::hasTable('soal')) {
            Schema::create('soal', function (Blueprint $table) {
                $table->id();
                $table->string('tenant_id');
                $table->foreignId('paket_soal_id')->constrained('paket_soal')->cascadeOnDelete();
                $table->string('tipe', 10)->default('pg');     // pg|essay
                $table->text('pertanyaan');
                $table->json('pilihan')->nullable();            // {"A":"...","B":"..."} utk pg
                $table->string('kunci', 5)->nullable();         // huruf kunci utk pg
                $table->unsignedSmallInteger('bobot')->default(1);
                $table->unsignedSmallInteger('sort')->default(0);
                $table->timestamps();

                $table->index(['tenant_id', 'paket_soal_id']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('soal');
        Schema::dropIfExists('paket_soal');
    }
};
