<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * G2-12 — Tambah potongan BPJS pada slip gaji. Diisi hanya oleh
 * PayrollService::terapkanPotongan() (opt-in); buatSlip() lama tak terpengaruh.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('slip_gaji', function (Blueprint $table) {
            if (! Schema::hasColumn('slip_gaji', 'bpjs_dipotong')) {
                $table->decimal('bpjs_dipotong', 15, 2)->default(0)->after('pph21_dipotong');
            }
        });
    }

    public function down(): void
    {
        Schema::table('slip_gaji', function (Blueprint $table) {
            if (Schema::hasColumn('slip_gaji', 'bpjs_dipotong')) {
                $table->dropColumn('bpjs_dipotong');
            }
        });
    }
};
