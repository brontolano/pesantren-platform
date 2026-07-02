<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Normalisasi status hafalan: 'proses' (dipakai importer lama) -> 'berjalan'
 * (kosakata kanonik: opsi form HafalanTahfidzResource + hitungan
 * TahfidzService::ringkasan). Tanpa ini, hafalan hasil impor ber-status 'proses'
 * tak terhitung di ringkasan 'berjalan' & gagal lolos validasi re-import.
 *
 * Idempotent (UPDATE WHERE 'proses' — kosong saat dijalankan ulang).
 * Migrasi TENANT (tahfidz_hafalan tenant-scoped). Default kolom 'direncanakan'
 * sudah benar, jadi tak diubah.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('tahfidz_hafalan')) {
            return;
        }

        DB::table('tahfidz_hafalan')
            ->where('status', 'proses')
            ->update(['status' => 'berjalan']);
    }

    public function down(): void
    {
        // Tidak dibalik: 'proses' tak dikenali UI/ringkasan/validasi importer.
    }
};
