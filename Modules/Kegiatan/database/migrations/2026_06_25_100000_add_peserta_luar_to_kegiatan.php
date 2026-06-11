<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * WP-11 — Kegiatan 'pasaran/kilatan': peserta luar (santri kalong / tamu
 * pesantren lain) disimpan sebagai JSON [{nama, asal, hadir}] di baris
 * kegiatan itu sendiri — TIDAK menyentuh tabel santri / presensi_kegiatan
 * (kedua tabel itu khusus santri terdaftar).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('kegiatan', 'peserta_luar')) {
            Schema::table('kegiatan', function (Blueprint $table): void {
                $table->json('peserta_luar')->nullable();
            });
        }
    }

    public function down(): void
    {
        Schema::table('kegiatan', function (Blueprint $table): void {
            $table->dropColumn('peserta_luar');
        });
    }
};
