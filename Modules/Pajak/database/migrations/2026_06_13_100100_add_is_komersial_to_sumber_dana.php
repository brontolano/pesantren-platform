<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * WP-T4: Tandai sumber dana komersial vs pendidikan untuk perhitungan PPh Badan.
 * Pendapatan komersial (kantin, sewa, koperasi) kena PPh 22%, pendidikan bebas.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sumber_dana', function (Blueprint $table) {
            $table->boolean('is_komersial')->default(false)->after('is_active');
        });

        // Set default: unit_usaha dan sewa = komersial
        DB::table('sumber_dana')
            ->whereIn('kategori', ['komersial'])
            ->where('kode_dana', 'like', '04%') // Unit Usaha
            ->update(['is_komersial' => true]);
    }

    public function down(): void
    {
        Schema::table('sumber_dana', function (Blueprint $table) {
            $table->dropColumn('is_komersial');
        });
    }
};
