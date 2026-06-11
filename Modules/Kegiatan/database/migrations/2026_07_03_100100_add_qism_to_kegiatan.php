<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * WP-21 — Penanda domain pengurus pada kegiatan (ADDITIVE).
 * qism NULL = kegiatan biasa (bukan domain pengurus, pengurus TIDAK boleh
 * mengisi presensinya); terisi = hanya pengurus ber-qism sama yang boleh.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('kegiatan', function (Blueprint $table): void {
            $table->string('qism')->nullable()->after('jenis');
        });
    }

    public function down(): void
    {
        Schema::table('kegiatan', function (Blueprint $table): void {
            $table->dropColumn('qism');
        });
    }
};
