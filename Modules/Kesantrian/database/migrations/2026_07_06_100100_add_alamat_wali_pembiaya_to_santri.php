<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Parity Santri: `alamat_wali` (teks jalan/dusun wali — kolom alamat ayah/ibu &
 * cascade wilayah ayah/ibu/wali sudah ada) + `pembiaya` (penanggung biaya).
 * Agar data alamat wali & pembiaya ikut terbawa saat daftar ulang. Additif & nullable.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('santri')) {
            return;
        }

        Schema::table('santri', function (Blueprint $table): void {
            if (! Schema::hasColumn('santri', 'alamat_wali')) {
                $table->text('alamat_wali')->nullable();
            }
            if (! Schema::hasColumn('santri', 'pembiaya')) {
                $table->string('pembiaya', 50)->nullable();
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('santri')) {
            return;
        }

        Schema::table('santri', function (Blueprint $table): void {
            foreach (['alamat_wali', 'pembiaya'] as $col) {
                if (Schema::hasColumn('santri', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
