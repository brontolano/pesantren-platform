<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Normalisasi status izin santri: 'pending' (dipakai panel admin & KegiatanService
 * lama) -> 'diajukan' (kosakata kanonik: IzinSantriService, controller PWA,
 * dashboard pimpinan, antrean persetujuan PWA where status='diajukan').
 *
 * Tanpa ini, izin yang dibuat lewat panel admin / jalur KegiatanService tak pernah
 * muncul di antrean persetujuan PWA (filter 'diajukan') — alur persetujuan terputus.
 *
 * Idempotent (UPDATE WHERE 'pending' — kosong saat dijalankan ulang).
 * Migrasi TENANT (izin_santri tenant-scoped).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('izin_santri')) {
            return;
        }

        DB::table('izin_santri')
            ->where('status', 'pending')
            ->update(['status' => 'diajukan']);

        DB::statement("ALTER TABLE izin_santri ALTER COLUMN status SET DEFAULT 'diajukan'");
    }

    public function down(): void
    {
        // Tidak dibalik: 'pending' adalah nilai keliru yang memutus antrean PWA.
    }
};
