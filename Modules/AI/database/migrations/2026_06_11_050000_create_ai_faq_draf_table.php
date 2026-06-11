<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * FAQ-Mining (permintaan owner 2026-06-11) — ai_faq_draf: TABEL TENANT,
 * draf FAQ hasil penambangan pertanyaan berulang (FaqMiner).
 *
 * HUMAN-IN-THE-LOOP MUTLAK: baris di sini hanyalah USULAN. Tanpa pengesahan
 * admin/ustadz (status disahkan via FaqMiner::sahkan) TIDAK ADA yang masuk
 * Sumber Pengetahuan/RAG. Tanpa LLM training — clustering teknik klasik lokal.
 *
 * PRIVASI: pertanyaan_perwakilan + variasi diambil dari sumber yang SUDAH
 * terredaksi by-construction (ai_escalation.pertanyaan_ringkas,
 * ai_message.isi_terredaksi) DAN diredaksi-paksa ulang oleh FaqMiner sebelum
 * disimpan (sabuk pengaman ganda). Kategori eskalasi sensitif
 * (blacklist_topik/level_fatwa — wilayah ustadz) tidak pernah ditambang.
 *
 * pertanyaan_hash = sha256 token ternormalisasi pertanyaan perwakilan;
 * unique (hash, dibuat_periode) = jaring idempotensi penambangan berulang.
 * source_id terisi SETELAH disahkan → diingest (nullOnDelete: hapus tuntas
 * sumber pengetahuan tidak menghapus jejak keputusan FAQ).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_faq_draf', function (Blueprint $table) {
            $table->id();
            // TERREDAKSI (by-construction + redactPaksa ulang di FaqMiner).
            $table->text('pertanyaan_perwakilan');
            $table->string('pertanyaan_hash', 64);
            // Array pertanyaan serupa terredaksi (maks config maks_variasi).
            $table->jsonb('variasi')->nullable();
            $table->unsignedInteger('frekuensi')->default(0);
            $table->string('sumber', 16); // eskalasi|percakapan
            // Diisi admin/ustadz; wajib terisi sebelum disahkan.
            $table->text('jawaban')->nullable();
            $table->string('status', 16)->default('usulan'); // usulan|dijawab|disahkan|diabaikan
            // Terisi setelah disahkan → ingest ke Sumber Pengetahuan (RAG).
            $table->foreignId('source_id')
                ->nullable()
                ->constrained('ai_knowledge_source')
                ->nullOnDelete();
            $table->date('dibuat_periode');
            // Tanpa FK users: lifecycle user tak boleh menghapus jejak keputusan.
            $table->unsignedBigInteger('disahkan_oleh')->nullable();
            $table->timestamp('disahkan_pada')->nullable();
            $table->timestamps();

            $table->unique(['pertanyaan_hash', 'dibuat_periode']);
            $table->index(['status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_faq_draf');
    }
};
