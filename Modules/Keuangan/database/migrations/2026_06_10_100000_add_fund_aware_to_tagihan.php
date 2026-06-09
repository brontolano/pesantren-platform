<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * G2-15 — Fund-aware Billing. Tambah penanda SPP dibayar di muka (deferred)
 * pada tagihan. Kolom sumber_dana_id/divisi_unit_id sudah ada (kini benar-benar
 * diisi oleh TagihanService). Additif.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tagihan', function (Blueprint $table) {
            if (! Schema::hasColumn('tagihan', 'is_dibayar_dimuka')) {
                $table->boolean('is_dibayar_dimuka')->default(false)->after('status'); // deferred revenue
            }
        });
    }

    public function down(): void
    {
        Schema::table('tagihan', function (Blueprint $table) {
            if (Schema::hasColumn('tagihan', 'is_dibayar_dimuka')) {
                $table->dropColumn('is_dibayar_dimuka');
            }
        });
    }
};
