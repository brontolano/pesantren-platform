<?php

namespace Modules\Kesantrian\Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\Kesantrian\Models\JenisPelanggaran;

/**
 * WP-09 — Contoh jenis pelanggaran umum pesantren (master per tenant).
 *
 * Poin = CONTOH dari riset, tenant bebas mengubah (prinsip "master di-seed
 * tapi bebas diubah"). Idempoten: firstOrCreate by nama — entri yang sudah
 * diedit tenant TIDAK ditimpa, reseed tidak menduplikasi.
 *
 * Jalankan dalam konteks tenant.
 */
class JenisPelanggaranSeeder extends Seeder
{
    /** @return array<int, array{nama:string,kategori:string,poin:int,sanksi_default:?string}> */
    public static function contoh(): array
    {
        return [
            // Ringan
            ['nama' => 'Terlambat sholat berjamaah', 'kategori' => 'ringan', 'poin' => 5, 'sanksi_default' => 'Teguran + istighfar'],
            ['nama' => 'Tidak piket kebersihan', 'kategori' => 'ringan', 'poin' => 5, 'sanksi_default' => 'Piket tambahan'],
            ['nama' => 'Berbicara tidak sopan', 'kategori' => 'ringan', 'poin' => 10, 'sanksi_default' => 'Teguran + minta maaf'],
            ['nama' => 'Tidak mengikuti kegiatan wajib', 'kategori' => 'ringan', 'poin' => 10, 'sanksi_default' => 'Hafalan tambahan'],
            // Sedang
            ['nama' => 'Membawa HP tanpa izin', 'kategori' => 'sedang', 'poin' => 20, 'sanksi_default' => 'HP disita + panggilan pembina'],
            ['nama' => 'Keluar pondok tanpa izin', 'kategori' => 'sedang', 'poin' => 25, 'sanksi_default' => 'Panggilan pengasuhan + khidmah'],
            ['nama' => 'Ghosob (memakai milik orang lain)', 'kategori' => 'sedang', 'poin' => 20, 'sanksi_default' => 'Mengganti + minta maaf'],
            // Berat
            ['nama' => 'Merokok', 'kategori' => 'berat', 'poin' => 50, 'sanksi_default' => 'Panggilan wali + pembinaan khusus'],
            ['nama' => 'Berkelahi', 'kategori' => 'berat', 'poin' => 50, 'sanksi_default' => 'Panggilan wali + pembinaan khusus'],
            ['nama' => 'Mencuri', 'kategori' => 'berat', 'poin' => 75, 'sanksi_default' => 'Panggilan wali + sidang mahkamah'],
            // Prestasi (poin NEGATIF — penyeimbang reward-punishment)
            ['nama' => 'Hafalan melampaui target', 'kategori' => 'prestasi', 'poin' => -10, 'sanksi_default' => null],
            ['nama' => 'Juara lomba / harumkan pondok', 'kategori' => 'prestasi', 'poin' => -25, 'sanksi_default' => null],
        ];
    }

    public function run(): void
    {
        foreach (self::contoh() as $jenis) {
            JenisPelanggaran::firstOrCreate(
                ['nama' => $jenis['nama']],
                [
                    'kategori' => $jenis['kategori'],
                    'poin' => $jenis['poin'],
                    'sanksi_default' => $jenis['sanksi_default'],
                    'aktif' => true,
                ],
            );
        }
    }
}
