<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * F1-PWA: idempotensi setoran tahfidz dari PWA Guru — klien mengirim
 * client_ref (uuid per entri); unique index (tenant_id, client_ref) menjadi
 * gerbang dedup di tingkat DB (race-safe), pola WP-32 setor_keliling_item.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('tahfidz_hafalan', 'client_ref')) {
            Schema::table('tahfidz_hafalan', function (Blueprint $table): void {
                $table->string('client_ref', 191)->nullable();
                $table->unique(['tenant_id', 'client_ref'], 'tahfidz_hafalan_client_ref_unique');
            });
        }
    }

    public function down(): void
    {
        Schema::table('tahfidz_hafalan', function (Blueprint $table): void {
            $table->dropUnique('tahfidz_hafalan_client_ref_unique');
            $table->dropColumn('client_ref');
        });
    }
};
