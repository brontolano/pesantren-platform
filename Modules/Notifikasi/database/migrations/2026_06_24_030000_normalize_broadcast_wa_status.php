<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Normalisasi status broadcast_wa lama: 'selesai'->'sent', 'sebagian'->'partial_failed'
 * (kosakata kanonik dipakai BroadcastWaService + BroadcastWaResource + badge UI).
 * NotifDispatcher lama menulis 'selesai'/'sebagian' yang tak dikenali UI → badge polos.
 *
 * Idempotent (UPDATE WHERE — kosong saat dijalankan ulang). Migrasi TENANT
 * (broadcast_wa tenant-scoped). Kolom default 'draft' sudah benar, tak diubah.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('broadcast_wa')) {
            return;
        }

        DB::table('broadcast_wa')->where('status', 'selesai')->update(['status' => 'sent']);
        DB::table('broadcast_wa')->where('status', 'sebagian')->update(['status' => 'partial_failed']);
    }

    public function down(): void
    {
        // Tidak dibalik: 'selesai'/'sebagian' tak dikenali UI/service.
    }
};
