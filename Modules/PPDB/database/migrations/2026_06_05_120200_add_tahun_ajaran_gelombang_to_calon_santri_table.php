<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * F0.5 — Periode SPMB (KEPUTUSAN OQ-2, Hamdan 2026-06-05: masuk skema v1).
 *
 * Berjalan di DATABASE TENANT.
 * `tahun_ajaran` format "2026/2027"; `gelombang` string bebas ("1", "2",
 * "khusus") — configuration over customization (Blueprint §14): tiap tenant
 * bebas menamai gelombangnya, bukan enum keras di kode.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('calon_santri', function (Blueprint $table) {
            $table->string('tahun_ajaran', 9)
                ->nullable()
                ->after('program_diminati');
            $table->string('gelombang', 30)
                ->nullable()
                ->after('tahun_ajaran');

            // Filter utama panitia: pendaftar per periode per gelombang
            $table->index(['tenant_id', 'tahun_ajaran', 'gelombang'], 'calon_santri_periode_index');
        });
    }

    public function down(): void
    {
        Schema::table('calon_santri', function (Blueprint $table) {
            $table->dropIndex('calon_santri_periode_index');
            $table->dropColumn(['tahun_ajaran', 'gelombang']);
        });
    }
};
