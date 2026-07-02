<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Custom Field per-tenant (pilot Santri): kolom JSON nullable menampung nilai
 * field kustom yang didefinisikan tiap tenant. Additif & guarded — tenant polos
 * tetap NULL (nol beban). Definisi skema field disimpan terpisah sebagai Setting
 * (key `custom_fields.santri`), bukan di sini.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('santri') || Schema::hasColumn('santri', 'custom_fields')) {
            return;
        }

        Schema::table('santri', function (Blueprint $table) {
            $table->json('custom_fields')->nullable();
        });
    }

    public function down(): void
    {
        if (Schema::hasColumn('santri', 'custom_fields')) {
            Schema::table('santri', fn (Blueprint $t) => $t->dropColumn('custom_fields'));
        }
    }
};
