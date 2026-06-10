<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * G3-22 — Partial unique index: maksimal SATU penempatan aktif (is_current) per
 * santri. Jaring DB anti double-place di bawah konkurensi (dua admin/klik ganda)
 * — INSERT kedua gagal & tertangkap sebagai 'gagal', bukan korupsi data.
 * Additif & idempoten (IF NOT EXISTS).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('penempatan_asrama')) {
            return;
        }

        try {
            DB::statement(
                'CREATE UNIQUE INDEX IF NOT EXISTS penempatan_asrama_current_unik '
                .'ON penempatan_asrama (tenant_id, santri_id) WHERE is_current = true'
            );
        } catch (\Throwable) {
            // Non-fatal: bila ada data ganda historis, index dilewati (invarian
            // tetap dijaga PenempatanAsramaService di jalur aplikasi).
        }
    }

    public function down(): void
    {
        try {
            DB::statement('DROP INDEX IF EXISTS penempatan_asrama_current_unik');
        } catch (\Throwable) {
        }
    }
};
