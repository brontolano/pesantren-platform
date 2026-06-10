<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * T-A020 — ai_escalation: TABEL TENANT, antrean "Tanya Ustadz" dasar.
 *
 * Diisi PolicyEngine saat sebuah pertanyaan diblokir kategori blacklist_topik
 * / level_fatwa: pertanyaannya disimpan RINGKAS dan SUDAH TERREDAKSI PII
 * (PiiRedactor) — jangan pernah menulis prompt mentah ke sini.
 * UI inbox ustadz = T-A023 (belum dibangun di tugas ini).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_escalation', function (Blueprint $table) {
            $table->id();
            $table->string('conversation_ref')->nullable();
            // [M2] Dedup eskalasi per idempotency_key request (firstOrCreate
            // di PolicyEngine) — retry sah tidak menggandakan antrean ustadz.
            $table->string('idempotency_key')->nullable()->index();
            $table->string('kanal', 24);
            // TERREDAKSI via PiiRedactor + dipangkas (config eskalasi_ringkas_max).
            $table->text('pertanyaan_ringkas');
            $table->string('kategori', 32);
            $table->string('status', 16)->default('baru'); // baru|ditugaskan|dijawab|ditutup
            // Tanpa FK users: lifecycle user tidak boleh menghapus antrean.
            $table->unsignedBigInteger('ditugaskan_ke')->nullable();
            $table->text('jawaban')->nullable();
            $table->timestamps();

            $table->index(['status', 'created_at']);
            $table->index('kategori');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_escalation');
    }
};
