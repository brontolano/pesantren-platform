<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * G3-22 — Gender blok asrama (putra/putri/campur) untuk algoritma penempatan
 * otomatis (anti gender-mix). Additif & guarded; null/campur = tanpa batasan
 * (kompatibel data lama).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('blok_asrama') && ! Schema::hasColumn('blok_asrama', 'gender')) {
            Schema::table('blok_asrama', function (Blueprint $table) {
                $table->string('gender', 10)->nullable()->after('nama_blok'); // putra|putri|campur|null
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('blok_asrama') && Schema::hasColumn('blok_asrama', 'gender')) {
            Schema::table('blok_asrama', function (Blueprint $table) {
                $table->dropColumn('gender');
            });
        }
    }
};
