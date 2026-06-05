<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * F0.4 — Tabel OTP peserta (PRD PWA Peserta SPMB §10.1, AUTH-2/4/5).
 *
 * Berjalan di DATABASE TENANT. BUKAN sistem auth baru (Hukum #2 Blueprint):
 * hanya tabel pendukung verifikasi OTP WA untuk akun `users` ber-role `peserta`.
 * Kode OTP disimpan sebagai HASH (bukan plaintext), single-use + TTL 5 menit
 * (ditegakkan di service F1.1.2, bukan di skema).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_otps', function (Blueprint $table) {
            $table->id();
            $table->string('tenant_id')->nullable()->index();
            $table->foreignId('user_id')
                ->constrained('users')
                ->cascadeOnDelete();
            $table->string('kode'); // hash OTP (bcrypt/sha256) — JANGAN plaintext
            $table->string('purpose', 20)->default('aktivasi'); // aktivasi | reset
            $table->timestamp('expires_at');
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->timestamp('used_at')->nullable();
            $table->timestamps();

            // Lookup utama: OTP aktif milik user untuk purpose tertentu
            $table->index(['user_id', 'purpose', 'used_at']);
            // Pembersihan OTP kedaluwarsa (scheduled prune)
            $table->index('expires_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_otps');
    }
};
