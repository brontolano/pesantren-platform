<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Surat & Administrasi (Tema TU): surat masuk/keluar + penomoran otomatis + arsip.
 * Additif & guarded.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('surat')) {
            return;
        }

        Schema::create('surat', function (Blueprint $table) {
            $table->id();
            $table->string('tenant_id');
            $table->string('jenis', 10);                  // masuk|keluar
            $table->unsignedInteger('tahun');
            $table->unsignedInteger('nomor_urut');        // agenda per jenis+tahun
            $table->string('nomor_surat');                // keluar: dihasilkan; masuk: nomor asal
            $table->date('tanggal_surat');
            $table->string('pihak');                      // masuk: pengirim; keluar: tujuan
            $table->string('perihal');
            $table->string('lampiran_path')->nullable();
            $table->text('keterangan')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'jenis', 'tahun']);
            // Backstop penomoran race-safe: satu nomor urut per (tenant, jenis, tahun).
            $table->unique(['tenant_id', 'jenis', 'tahun', 'nomor_urut']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('surat');
    }
};
