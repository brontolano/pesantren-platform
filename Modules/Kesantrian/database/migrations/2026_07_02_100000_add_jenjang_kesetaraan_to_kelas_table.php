<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Issue #338 — Pemetaan kelas diniyah internal → jenjang kesetaraan (PKPPS/SPM).
 * Portabel: idempoten (aman dijalankan ulang via tenants:migrate).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('kelas', function (Blueprint $table) {
            if (! Schema::hasColumn('kelas', 'jenjang_kesetaraan')) {
                // ula | wustha | ulya — nullable: kelas tanpa kesetaraan tetap sah
                $table->string('jenjang_kesetaraan', 20)->nullable()->after('tingkat');
            }
            if (! Schema::hasColumn('kelas', 'tingkat_kesetaraan')) {
                // Ula 1-6, Wustha 1-3, Ulya 1-3
                $table->unsignedTinyInteger('tingkat_kesetaraan')->nullable()->after('jenjang_kesetaraan');
            }
        });
    }

    public function down(): void
    {
        Schema::table('kelas', function (Blueprint $table) {
            if (Schema::hasColumn('kelas', 'tingkat_kesetaraan')) {
                $table->dropColumn('tingkat_kesetaraan');
            }
            if (Schema::hasColumn('kelas', 'jenjang_kesetaraan')) {
                $table->dropColumn('jenjang_kesetaraan');
            }
        });
    }
};
