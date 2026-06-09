<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Dimensi ke-4: KEHALALAN (halal / syubhat / non_halal).
 *
 * Fitur pembeda Baitul Maal — memisahkan uang halal dari syubhat. Uang syubhat
 * (mis. bunga bank) diperlakukan sebagai "Dana Kebajikan/Pembersihan", BUKAN
 * pendapatan, dan dilarang untuk sarana ibadah/masjid (ditegakkan KehalalanGuard).
 * Aktif/non-aktif via setting tenant `keuangan.pemisahan_kehalalan_aktif`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sumber_dana', function (Blueprint $table) {
            $table->string('kehalalan', 12)->default('halal')->after('kategori'); // halal / syubhat / non_halal
            $table->boolean('is_dana_kebajikan')->default(false)->after('kehalalan');
            $table->index(['tenant_id', 'kehalalan']);
        });

        Schema::table('akun', function (Blueprint $table) {
            // peruntukan: umum / ibadah / dhuafa / fasilitas_umum / operasional
            $table->string('peruntukan', 20)->nullable()->after('is_kas_bank');
            $table->boolean('boleh_dana_kebajikan')->default(false)->after('peruntukan');
        });

        // Kebijakan kehalalan (configurable, disahkan otoritas syar'i pesantren).
        // Memetakan: sumber kehalalan X boleh/tidak untuk peruntukan Y.
        Schema::create('kebijakan_kehalalan', function (Blueprint $table) {
            $table->id();
            $table->string('tenant_id');
            $table->string('kehalalan_sumber', 12);   // syubhat / non_halal (halal selalu boleh)
            $table->string('peruntukan_tujuan', 20);  // fasilitas_umum / dhuafa / operasional / ibadah / umum
            $table->boolean('diizinkan')->default(false);
            $table->string('dasar')->nullable();      // catatan dasar syar'i
            $table->timestamps();

            $table->unique(['tenant_id', 'kehalalan_sumber', 'peruntukan_tujuan'], 'kebijakan_kehalalan_unik');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kebijakan_kehalalan');
        Schema::table('akun', function (Blueprint $table) {
            $table->dropColumn(['peruntukan', 'boleh_dana_kebajikan']);
        });
        Schema::table('sumber_dana', function (Blueprint $table) {
            $table->dropColumn(['kehalalan', 'is_dana_kebajikan']);
        });
    }
};
