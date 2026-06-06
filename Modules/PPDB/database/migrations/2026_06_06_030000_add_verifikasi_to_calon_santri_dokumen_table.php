<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * F2 (DOC-3) — status verifikasi dokumen peserta + jejak verifikator.
 * Menyiapkan antrean verifikasi ERP: pending → verified/rejected.
 *
 * Aditif & defensif (hasColumn guard) agar aman dijalankan pada tabel tenant
 * yang mungkin sudah/belum punya kolom-kolom ini.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('calon_santri_dokumen', function (Blueprint $table) {
            if (! Schema::hasColumn('calon_santri_dokumen', 'status')) {
                $table->string('status', 20)->default('pending')->index();
            }
            if (! Schema::hasColumn('calon_santri_dokumen', 'verified_at')) {
                $table->timestamp('verified_at')->nullable();
            }
            if (! Schema::hasColumn('calon_santri_dokumen', 'verified_by')) {
                // ID user staf yang memverifikasi (tanpa FK lintas-konteks; nullable).
                $table->unsignedBigInteger('verified_by')->nullable();
            }
            if (! Schema::hasColumn('calon_santri_dokumen', 'catatan_verifikasi')) {
                $table->text('catatan_verifikasi')->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('calon_santri_dokumen', function (Blueprint $table) {
            foreach (['status', 'verified_at', 'verified_by', 'catatan_verifikasi'] as $col) {
                if (Schema::hasColumn('calon_santri_dokumen', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
