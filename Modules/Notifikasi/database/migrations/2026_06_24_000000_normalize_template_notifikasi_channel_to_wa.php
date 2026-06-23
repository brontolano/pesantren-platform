<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Normalisasi nilai channel template notifikasi: 'whatsapp' (nilai keliru dari
 * seeder lama) -> 'wa' (nilai standar: default kolom, opsi form Select, dan
 * filter dropdown Broadcast). Tanpa ini, template ber-channel 'whatsapp' tak
 * pernah muncul di dropdown Broadcast & field channel tampak kosong saat diedit.
 *
 * Idempotent (UPDATE WHERE channel='whatsapp' — kosong saat dijalankan ulang).
 * Migrasi TENANT (dijalankan per-pesantren) — tabel template_notifikasi tenant-scoped.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('template_notifikasi')) {
            return;
        }

        DB::table('template_notifikasi')
            ->where('channel', 'whatsapp')
            ->update(['channel' => 'wa']);
    }

    public function down(): void
    {
        // Tidak dibalik: 'whatsapp' adalah nilai keliru yang tak dikenali UI/filter.
    }
};
