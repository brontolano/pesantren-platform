<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * WP-08 — Mutasi MASUK (santri pindahan dari pondok lain) lewat PPDB.
 * `jalur` string bebas ('reguler' default UI, 'pindahan' utk mutasi masuk) —
 * konsisten dengan keputusan `gelombang` (string bebas, bukan enum keras).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('calon_santri', function (Blueprint $table) {
            $table->string('jalur', 30)->nullable()->after('gelombang')
                ->comment('reguler|pindahan|... — string bebas per tenant');
            $table->string('asal_pondok')->nullable()->after('jalur')
                ->comment('Nama pondok asal utk jalur pindahan');
        });
    }

    public function down(): void
    {
        Schema::table('calon_santri', function (Blueprint $table) {
            $table->dropColumn(['jalur', 'asal_pondok']);
        });
    }
};
