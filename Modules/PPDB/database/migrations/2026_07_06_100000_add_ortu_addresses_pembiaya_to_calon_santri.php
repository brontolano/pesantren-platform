<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Alamat domisili TERPISAH untuk ayah, ibu, dan wali (mirror struktur alamat
 * santri & modul Santri) + field "Penanggung Biaya" (pembiaya). Additif &
 * nullable. Diisi peserta di portal; fitur "salin alamat" mengisi cepat.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('calon_santri')) {
            return;
        }

        Schema::table('calon_santri', function (Blueprint $table): void {
            foreach (['ayah', 'ibu', 'wali'] as $pihak) {
                if (! Schema::hasColumn('calon_santri', "alamat_{$pihak}")) {
                    $table->text("alamat_{$pihak}")->nullable();
                }
                foreach ([
                    "rt_rw_{$pihak}" => 20,
                    "desa_kelurahan_{$pihak}" => 255,
                    "kecamatan_{$pihak}" => 255,
                    "kabupaten_kota_{$pihak}" => 255,
                    "provinsi_{$pihak}" => 255,
                    "kode_pos_{$pihak}" => 10,
                ] as $col => $len) {
                    if (! Schema::hasColumn('calon_santri', $col)) {
                        $table->string($col, $len)->nullable();
                    }
                }
            }

            if (! Schema::hasColumn('calon_santri', 'pembiaya')) {
                $table->string('pembiaya', 50)->nullable();
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('calon_santri')) {
            return;
        }

        $cols = ['pembiaya'];
        foreach (['ayah', 'ibu', 'wali'] as $pihak) {
            $cols[] = "alamat_{$pihak}";
            foreach (['rt_rw', 'desa_kelurahan', 'kecamatan', 'kabupaten_kota', 'provinsi', 'kode_pos'] as $c) {
                $cols[] = "{$c}_{$pihak}";
            }
        }

        Schema::table('calon_santri', function (Blueprint $table) use ($cols): void {
            foreach ($cols as $col) {
                if (Schema::hasColumn('calon_santri', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
