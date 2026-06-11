<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * WP-17 — Izin Pegawai Harian. Jenis baru (pulang_kampung/keperluan_keluarga/
 * dinas) TIDAK butuh ALTER — cuti.jenis adalah string(20) bebas tanpa CHECK
 * (lihat 2026_06_10_130000). Hanya tambah client_ref untuk idempoten PWA
 * (pola WP-26 izin_santri).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cuti', function (Blueprint $table) {
            $table->string('client_ref')->nullable()->after('diajukan_oleh');
            // Unik per tenant; NULL bebas berulang (Postgres standard).
            $table->unique(['tenant_id', 'client_ref'], 'cuti_client_ref_unik');
        });
    }

    public function down(): void
    {
        Schema::table('cuti', function (Blueprint $table) {
            $table->dropUnique('cuti_client_ref_unik');
            $table->dropColumn('client_ref');
        });
    }
};
