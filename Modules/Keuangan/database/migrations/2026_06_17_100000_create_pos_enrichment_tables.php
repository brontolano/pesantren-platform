<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * G3-21 — POS enrichment: profil loket + kolom metode bayar/diskon/poin pada
 * pos_transaksi + profil_id pada pos_sesi. Additif & guarded. TIDAK mengubah math
 * PosKasService (diskon diterapkan sebagai NETTO sebelum posting; poin non-uang).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('pos_profil')) {
            Schema::create('pos_profil', function (Blueprint $table) {
                $table->id();
                $table->string('tenant_id');
                $table->string('kode', 40)->nullable();
                $table->string('nama');
                $table->string('pos_jenis', 40)->default('koperasi'); // label loket
                $table->unsignedBigInteger('akun_kas_id')->nullable();
                $table->unsignedBigInteger('akun_qris_id')->nullable();
                $table->decimal('tarif_poin', 12, 2)->default(0);      // Rp per 1 poin (0=nonaktif)
                $table->boolean('is_active')->default(true);
                $table->timestamps();

                $table->index(['tenant_id', 'is_active']);
            });
        }

        if (Schema::hasTable('pos_sesi')) {
            Schema::table('pos_sesi', function (Blueprint $table) {
                if (! Schema::hasColumn('pos_sesi', 'profil_id')) {
                    $table->unsignedBigInteger('profil_id')->nullable()->after('pos_jenis');
                }
            });
        }

        if (Schema::hasTable('pos_transaksi')) {
            Schema::table('pos_transaksi', function (Blueprint $table) {
                if (! Schema::hasColumn('pos_transaksi', 'metode_bayar')) {
                    $table->string('metode_bayar', 12)->default('tunai')->after('nominal'); // tunai|transfer|qris
                }
                if (! Schema::hasColumn('pos_transaksi', 'harga_kotor')) {
                    $table->decimal('harga_kotor', 15, 2)->nullable()->after('metode_bayar');
                }
                if (! Schema::hasColumn('pos_transaksi', 'diskon')) {
                    $table->decimal('diskon', 15, 2)->default(0)->after('harga_kotor');
                }
                if (! Schema::hasColumn('pos_transaksi', 'poin')) {
                    $table->decimal('poin', 12, 2)->default(0)->after('diskon');
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('pos_transaksi')) {
            Schema::table('pos_transaksi', function (Blueprint $table) {
                foreach (['metode_bayar', 'harga_kotor', 'diskon', 'poin'] as $col) {
                    if (Schema::hasColumn('pos_transaksi', $col)) {
                        $table->dropColumn($col);
                    }
                }
            });
        }
        if (Schema::hasTable('pos_sesi') && Schema::hasColumn('pos_sesi', 'profil_id')) {
            Schema::table('pos_sesi', function (Blueprint $table) {
                $table->dropColumn('profil_id');
            });
        }
        Schema::dropIfExists('pos_profil');
    }
};
