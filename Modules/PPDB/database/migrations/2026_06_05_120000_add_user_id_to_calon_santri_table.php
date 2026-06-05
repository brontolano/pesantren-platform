<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * F0.3 — Penghubung akun peserta <-> pendaftaran (PRD PWA Peserta SPMB §10.1).
 *
 * Berjalan di DATABASE TENANT (mode multi-database stancl/tenancy).
 * Satu akun user (role `peserta`) terhubung ke tepat satu pendaftaran
 * calon_santri (1 pendaftaran = 1 akun peserta, sesuai Non-Goal multi-anak v1).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('calon_santri', function (Blueprint $table) {
            $table->foreignId('user_id')
                ->nullable()
                ->after('santri_id')
                ->constrained('users')
                ->nullOnDelete();

            // 1 akun = 1 pendaftaran (unique, NULL diabaikan oleh PostgreSQL)
            $table->unique('user_id');
        });
    }

    public function down(): void
    {
        Schema::table('calon_santri', function (Blueprint $table) {
            $table->dropForeign(['user_id']);
            $table->dropUnique(['user_id']);
            $table->dropColumn('user_id');
        });
    }
};
