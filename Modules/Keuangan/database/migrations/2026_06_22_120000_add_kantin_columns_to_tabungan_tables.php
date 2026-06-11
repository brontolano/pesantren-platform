<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * POS Kantin via Tabungan (2b):
 *  - tipe tabungan_transaksi dilebarkan (belanja_kantin/refund_kantin > 10 char)
 *  - ref_transaksi_id: tautan refund -> transaksi belanja asal (guard refund 1x)
 *  - kasir_id sudah terwakili created_by (tidak ditambah kolom baru)
 *  - tabungan.batas_belanja_harian: NULL = ikut default tenant
 *    (Setting kantin.batas_belanja_default), 0 = blokir belanja kantin.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tabungan_transaksi', function (Blueprint $table) {
            $table->string('tipe', 30)->change();
        });

        Schema::table('tabungan_transaksi', function (Blueprint $table) {
            if (! Schema::hasColumn('tabungan_transaksi', 'ref_transaksi_id')) {
                $table->foreignId('ref_transaksi_id')->nullable()->after('created_by')
                    ->constrained('tabungan_transaksi')->nullOnDelete();
                $table->index(['tenant_id', 'ref_transaksi_id']);
            }
        });

        Schema::table('tabungan', function (Blueprint $table) {
            if (! Schema::hasColumn('tabungan', 'batas_belanja_harian')) {
                $table->decimal('batas_belanja_harian', 15, 2)->nullable()->after('is_frozen');
            }
        });
    }

    public function down(): void
    {
        Schema::table('tabungan', function (Blueprint $table) {
            $table->dropColumn('batas_belanja_harian');
        });
        Schema::table('tabungan_transaksi', function (Blueprint $table) {
            $table->dropConstrainedForeignId('ref_transaksi_id');
        });
    }
};
