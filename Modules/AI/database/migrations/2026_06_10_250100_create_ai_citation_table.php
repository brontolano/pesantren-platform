<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * T-A031 — ai_citation: daftar sitasi DETERMINISTIK per jawaban (tabel TENANT).
 *
 * Diisi RagAnswerer dari chunk yang BENAR-BENAR dipakai pipeline — model
 * TIDAK pernah diminta menulis sitasi (Blueprint §4.2 langkah 6). Dipakai
 * CitationEnforcer (T-A022) dan UI sumber jawaban (T-A090).
 *
 * judul/bab/halaman didenormalisasi dari metadata chunk saat jawaban dibuat
 * supaya sitasi tetap terbaca walau metadata sumber berubah; right-to-erasure
 * tetap tegak via FK cascade (hapus sumber/chunk = sitasi ikut hilang).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_citation', function (Blueprint $table) {
            $table->id();
            // Tautan ke baris audit gateway (via idempotency_key request) —
            // nullable: audit best-effort, kegagalannya tak membatalkan sitasi.
            // ULID string mengikuti pk ai_audit_log; SENGAJA TANPA FK (pola
            // log forensik T-A003): ai_audit_log harus tetap bisa di-rollback/
            // dipangkas tanpa tersandera tabel lain.
            $table->ulid('audit_log_id')->nullable();
            $table->foreignId('source_id')
                ->constrained('ai_knowledge_source')->cascadeOnDelete();
            $table->foreignId('chunk_id')
                ->constrained('ai_chunk')->cascadeOnDelete();
            $table->unsignedInteger('urutan'); // urutan tampil pada jawaban (1..n)
            $table->string('judul');
            $table->string('bab')->nullable();
            $table->unsignedInteger('halaman')->nullable();
            $table->float('skor'); // skor fusi RRF saat chunk terpilih
            $table->timestamp('created_at')->nullable();

            $table->index('audit_log_id');
            $table->index('source_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_citation');
    }
};
