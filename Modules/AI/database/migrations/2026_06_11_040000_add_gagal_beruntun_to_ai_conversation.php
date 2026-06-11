<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * T-A052 — penghitung jawaban gagal beruntun per percakapan WA (tabel TENANT).
 *
 * gagal_beruntun = jumlah jawaban free-form RAG yang degraded/tanpa-rujukan
 * BERTURUT-TURUT dalam sesi. Mencapai ambang config ai.wa.handoff.
 * maks_gagal_beruntun (default 2) → bot menawarkan handoff otomatis dan
 * status percakapan pindah ke 'handoff' (PRD §8.2 — eskalasi ke manusia).
 * Jawaban sukses (tool/RAG normal) me-reset hitungan ke 0; aksi admin
 * "Kembalikan ke Bot" di Kotak Masuk juga me-reset.
 *
 * Status 'handoff' sendiri SUDAH ada sejak T-A050 (kolom status) — migrasi
 * ini hanya menambah penghitungnya.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ai_conversation', function (Blueprint $table) {
            $table->unsignedSmallInteger('gagal_beruntun')->default(0);
        });
    }

    public function down(): void
    {
        Schema::table('ai_conversation', function (Blueprint $table) {
            $table->dropColumn('gagal_beruntun');
        });
    }
};
