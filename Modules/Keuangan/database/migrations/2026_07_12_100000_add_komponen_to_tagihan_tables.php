<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Syahriah multi-komponen (#332) — ADDITIF & portabel.
 *
 * - tagihan_rutin.komponen : rincian komponen aturan
 *   (array of {nama, nominal, masuk_tabungan}). NULL = aturan lama tanpa
 *   rincian, perilaku tidak berubah.
 * - tagihan.komponen : salinan rincian saat tagihan diterbitkan (transparansi
 *   untuk wali — tak ikut berubah bila aturan diedit belakangan).
 * - tagihan.tabungan_disetor_at : penanda idempotensi — porsi komponen
 *   masuk_tabungan hanya disetor SEKALI ke Tabungan santri saat lunas.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('tagihan_rutin', 'komponen')) {
            Schema::table('tagihan_rutin', function (Blueprint $table) {
                $table->json('komponen')->nullable()->after('nominal');
            });
        }

        if (! Schema::hasColumn('tagihan', 'komponen')) {
            Schema::table('tagihan', function (Blueprint $table) {
                $table->json('komponen')->nullable()->after('nominal');
            });
        }

        if (! Schema::hasColumn('tagihan', 'tabungan_disetor_at')) {
            Schema::table('tagihan', function (Blueprint $table) {
                $table->timestamp('tabungan_disetor_at')->nullable()->after('status');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('tagihan_rutin', 'komponen')) {
            Schema::table('tagihan_rutin', fn (Blueprint $table) => $table->dropColumn('komponen'));
        }

        if (Schema::hasColumn('tagihan', 'komponen')) {
            Schema::table('tagihan', fn (Blueprint $table) => $table->dropColumn('komponen'));
        }

        if (Schema::hasColumn('tagihan', 'tabungan_disetor_at')) {
            Schema::table('tagihan', fn (Blueprint $table) => $table->dropColumn('tabungan_disetor_at'));
        }
    }
};
