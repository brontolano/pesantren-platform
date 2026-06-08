<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tambah dimensi Ledger (opsional) ke tagihan. Tanpa FK keras agar modul
 * Keuangan tidak tergantung pada keberadaan tabel modul Ledger (loose coupling).
 * Dipakai hanya bila modul Ledger aktif; default null → listener pakai SPP.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tagihan', function (Blueprint $table) {
            $table->unsignedBigInteger('divisi_unit_id')->nullable()->after('santri_id');
            $table->unsignedBigInteger('sumber_dana_id')->nullable()->after('divisi_unit_id');
        });
    }

    public function down(): void
    {
        Schema::table('tagihan', function (Blueprint $table) {
            $table->dropColumn(['divisi_unit_id', 'sumber_dana_id']);
        });
    }
};
