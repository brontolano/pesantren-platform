<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * #339 — Penanda idempotensi notifikasi WA "tagihan terbit".
 *
 * NULL = belum pernah dikirim; terisi = WA sudah TERKIRIM (bukan sekadar
 * dicoba) untuk tagihan ini → jalur kirim ulang aman dari pesan ganda.
 * Additif & portabel: nullable, tidak menyentuh baris existing.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tagihan', function (Blueprint $table): void {
            $table->timestamp('notif_wa_sent_at')->nullable()->after('bulan_hijriah');
        });
    }

    public function down(): void
    {
        Schema::table('tagihan', function (Blueprint $table): void {
            $table->dropColumn('notif_wa_sent_at');
        });
    }
};
