<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * G2-14 — Unique index batch (cegah lot ganda di bawah race firstOrCreate).
 * Kunci = (tenant, item, lokasi, nomor_batch, kedaluwarsa) — selaras kunci
 * firstOrCreate. Additif & guarded.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('inventory_batch')) {
            return;
        }
        if ($this->indexAda('inventory_batch', 'inventory_batch_lot_unique')) {
            return;
        }

        Schema::table('inventory_batch', function (Blueprint $table) {
            $table->unique(
                ['tenant_id', 'item_id', 'lokasi_id', 'nomor_batch', 'tanggal_kedaluwarsa'],
                'inventory_batch_lot_unique',
            );
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('inventory_batch')) {
            return;
        }
        if (! $this->indexAda('inventory_batch', 'inventory_batch_lot_unique')) {
            return;
        }

        Schema::table('inventory_batch', function (Blueprint $table) {
            $table->dropUnique('inventory_batch_lot_unique');
        });
    }

    private function indexAda(string $table, string $index): bool
    {
        try {
            return DB::table('pg_indexes')->where('indexname', $index)->exists();
        } catch (\Throwable) {
            return false;
        }
    }
};
