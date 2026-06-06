<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * F2 (BILL-2) — bukti transfer manual untuk tagihan PPDB peserta.
 * Aditif & ber-guard (hasColumn). Saat peserta unggah bukti, status tagihan
 * → 'menunggu_verifikasi' (antrean verifikasi panitia).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tagihan_ppdb', function (Blueprint $table) {
            if (! Schema::hasColumn('tagihan_ppdb', 'bukti_transfer')) {
                $table->string('bukti_transfer')->nullable();
            }
            if (! Schema::hasColumn('tagihan_ppdb', 'bukti_diunggah_at')) {
                $table->timestamp('bukti_diunggah_at')->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('tagihan_ppdb', function (Blueprint $table) {
            foreach (['bukti_transfer', 'bukti_diunggah_at'] as $col) {
                if (Schema::hasColumn('tagihan_ppdb', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
