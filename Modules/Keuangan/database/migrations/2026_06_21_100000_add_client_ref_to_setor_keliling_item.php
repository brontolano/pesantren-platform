<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * WP-32: idempotensi setoran dari PWA — klien mengirim client_ref (uuid per
 * entri); unique index menjadi gerbang dedup di tingkat DB (race-safe),
 * pola sama dengan kolom client_ref di tabel belanja.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('setor_keliling_item', 'client_ref')) {
            Schema::table('setor_keliling_item', function (Blueprint $table): void {
                $table->string('client_ref', 191)->nullable();
                $table->unique(['tenant_id', 'client_ref'], 'setor_keliling_item_client_ref_unique');
            });
        }
    }

    public function down(): void
    {
        Schema::table('setor_keliling_item', function (Blueprint $table): void {
            $table->dropUnique('setor_keliling_item_client_ref_unique');
            $table->dropColumn('client_ref');
        });
    }
};
