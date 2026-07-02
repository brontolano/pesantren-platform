<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Refs #327 — kelengkapan field EMIS 4.0 PD-Pontren.
 * Dua kolom identitas kependudukan yang WAJIB di form EMIS tapi belum ada
 * di skema santri: No. Kartu Keluarga & No. Akta Kelahiran.
 * Additif + nullable + idempoten (hasColumn) agar portabel dijalankan ulang
 * di semua DB tenant (tenants:migrate) maupun DB test.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('santri', function (Blueprint $table) {
            if (! Schema::hasColumn('santri', 'no_kk')) {
                $table->string('no_kk', 20)->nullable()->after('nisn')
                    ->comment('Nomor Kartu Keluarga (16 digit) — wajib EMIS');
            }
            if (! Schema::hasColumn('santri', 'no_akta_lahir')) {
                $table->string('no_akta_lahir', 60)->nullable()->after('no_kk')
                    ->comment('Nomor Akta Kelahiran — wajib EMIS');
            }
        });
    }

    public function down(): void
    {
        Schema::table('santri', function (Blueprint $table) {
            foreach (['no_kk', 'no_akta_lahir'] as $col) {
                if (Schema::hasColumn('santri', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
