<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * WP-21 — Penugasan pengurus-santri (OSIS/ISMI pesantren): santri yang
 * diberi amanah qism (keamanan/kebersihan/bahasa/ibadah/... string bebas)
 * untuk satu periode jabatan. Akses fitur pengurus di App Santri DICEK
 * per-request dari baris aktif di tabel ini (tanpa cron — masa habis =
 * otomatis tertutup).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pengurus_santri', function (Blueprint $table): void {
            $table->id();
            $table->string('tenant_id');
            $table->foreignId('santri_id')->constrained('santri')->cascadeOnDelete();
            $table->string('qism'); // domain tugas: keamanan|kebersihan|bahasa|ibadah|... (string bebas)
            $table->string('label')->nullable(); // jabatan tampil, mis. "Ketua Qism Keamanan"
            $table->date('periode_mulai');
            $table->date('periode_selesai')->nullable(); // null = sampai dicabut
            $table->boolean('aktif')->default(true);
            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->index(['tenant_id', 'santri_id']);
            $table->index(['tenant_id', 'qism']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pengurus_santri');
    }
};
