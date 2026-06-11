<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * T-A051 — status verifikasi wali per percakapan WA (tabel TENANT).
 *
 * Verifikasi ringan sesi pertama (Blueprint §6 / PRD §8.2): nomor pengirim
 * dicocokkan ke wali eksisting (users + AccessWali + santri.no_hp_wali — TANPA
 * auth baru, Aturan Emas #10), lalu wali diminta tanggal lahir salah satu anak.
 *
 *  - verifikasi_status: belum | menunggu_tgl_lahir | terverifikasi;
 *  - verifikasi_percobaan: hitungan tanggal lahir salah (3x → kunci 24 jam);
 *  - verifikasi_terkunci_sampai: batas kunci; lewat = hitungan di-reset.
 * Kolom wali_user_id & santri_id sudah ada sejak T-A050 (tanpa FK — lifecycle
 * user tidak boleh menghapus riwayat percakapan).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ai_conversation', function (Blueprint $table) {
            $table->string('verifikasi_status', 24)->default('belum');
            $table->unsignedSmallInteger('verifikasi_percobaan')->default(0);
            $table->timestamp('verifikasi_terkunci_sampai')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('ai_conversation', function (Blueprint $table) {
            $table->dropColumn([
                'verifikasi_status',
                'verifikasi_percobaan',
                'verifikasi_terkunci_sampai',
            ]);
        });
    }
};
