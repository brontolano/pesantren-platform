<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Data wali lebih detail: HP & alamat AYAH dan IBU bisa BERBEDA. Additif &
 * nullable — kolom *_wali lama dipertahankan sebagai "kontak/akun wali utama".
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('santri')) {
            return;
        }

        Schema::table('santri', function (Blueprint $table): void {
            // Nomor HP terpisah ayah/ibu (no_hp_wali tetap = nomor akun utama).
            foreach (['no_hp_ayah', 'no_hp_ibu'] as $col) {
                if (! Schema::hasColumn('santri', $col)) {
                    $table->string($col, 20)->nullable();
                }
            }

            // Set alamat ayah & ibu (terurai), mirror struktur *_wali.
            foreach (['ayah', 'ibu'] as $pihak) {
                if (! Schema::hasColumn('santri', "alamat_{$pihak}")) {
                    $table->text("alamat_{$pihak}")->nullable();
                }
                foreach ([
                    "rt_rw_{$pihak}" => 10,
                    "desa_kelurahan_{$pihak}" => 255,
                    "kecamatan_{$pihak}" => 255,
                    "kabupaten_kota_{$pihak}" => 255,
                    "provinsi_{$pihak}" => 255,
                    "kode_pos_{$pihak}" => 5,
                ] as $col => $len) {
                    if (! Schema::hasColumn('santri', $col)) {
                        $table->string($col, $len)->nullable();
                    }
                }
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('santri')) {
            return;
        }

        $cols = ['no_hp_ayah', 'no_hp_ibu'];
        foreach (['ayah', 'ibu'] as $pihak) {
            $cols[] = "alamat_{$pihak}";
            foreach (['rt_rw', 'desa_kelurahan', 'kecamatan', 'kabupaten_kota', 'provinsi', 'kode_pos'] as $c) {
                $cols[] = "{$c}_{$pihak}";
            }
        }
        Schema::table('santri', function (Blueprint $table) use ($cols): void {
            foreach ($cols as $col) {
                if (Schema::hasColumn('santri', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
