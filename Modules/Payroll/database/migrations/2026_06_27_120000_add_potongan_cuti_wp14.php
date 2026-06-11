<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * WP-14 — Potongan cuti tak berbayar.
 *
 * - cuti.is_berbayar: penanda jenis cuti berbayar/tidak. Backfill default TRUE
 *   (konservatif: data cuti lama dianggap berbayar — tidak tiba-tiba memotong
 *   gaji atas cuti yang sudah disetujui sebelum fitur ini ada).
 * - slip_gaji.potongan_cuti: diisi hanya oleh PayrollService::terapkanPotongan()
 *   (opt-in); buatSlip() lama tak terpengaruh.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cuti', function (Blueprint $table) {
            if (! Schema::hasColumn('cuti', 'is_berbayar')) {
                $table->boolean('is_berbayar')->default(true)->after('jenis');
            }
        });

        Schema::table('slip_gaji', function (Blueprint $table) {
            if (! Schema::hasColumn('slip_gaji', 'potongan_cuti')) {
                $table->decimal('potongan_cuti', 15, 2)->default(0)->after('bpjs_dipotong');
            }
        });
    }

    public function down(): void
    {
        Schema::table('slip_gaji', function (Blueprint $table) {
            if (Schema::hasColumn('slip_gaji', 'potongan_cuti')) {
                $table->dropColumn('potongan_cuti');
            }
        });

        Schema::table('cuti', function (Blueprint $table) {
            if (Schema::hasColumn('cuti', 'is_berbayar')) {
                $table->dropColumn('is_berbayar');
            }
        });
    }
};
