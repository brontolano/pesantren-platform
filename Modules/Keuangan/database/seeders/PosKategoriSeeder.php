<?php

namespace Modules\Keuangan\Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\Keuangan\Models\PosKategori;

/**
 * Kategori POS default — dijalankan DALAM konteks tenant (pos_kategori tenant-scoped).
 * Idempotent (updateOrCreate by kode). akun_id sengaja null: tiap pondok memetakan
 * ke akun COA-nya sendiri lewat panel (Configuration over Customization).
 *
 * Pondok bebas menambah/menonaktifkan kategori tanpa ubah kode.
 */
class PosKategoriSeeder extends Seeder
{
    /** @var list<array{kode:string,nama:string,jenis:string,handler:string,sort:int}> */
    public const DEFAULTS = [
        // --- Diminta (4 POS inti) ---
        ['kode' => 'TAB_SETOR',    'nama' => 'Setor Tabungan',              'jenis' => 'pemasukan',   'handler' => 'tabungan_setor', 'sort' => 1],
        ['kode' => 'TAB_TARIK',    'nama' => 'Tarik Tabungan',              'jenis' => 'pengeluaran', 'handler' => 'tabungan_tarik', 'sort' => 2],
        ['kode' => 'DAFTAR_ULANG', 'nama' => 'Daftar Ulang Santri',         'jenis' => 'pemasukan',   'handler' => 'daftar_ulang',   'sort' => 3],
        ['kode' => 'PENGELUARAN',  'nama' => 'Pengeluaran Kebutuhan Pondok', 'jenis' => 'pengeluaran', 'handler' => 'pengeluaran',    'sort' => 4],
        ['kode' => 'KOPERASI',     'nama' => 'Koperasi / Kantin',           'jenis' => 'pemasukan',   'handler' => 'koperasi',       'sort' => 5],

        // --- Rekomendasi (di-seed, configurable) ---
        ['kode' => 'SPP',          'nama' => 'SPP / Syahriyah',             'jenis' => 'pemasukan',   'handler' => 'spp',            'sort' => 6],
        ['kode' => 'INFAQ',        'nama' => 'Infaq / Sedekah / ZISWAF',    'jenis' => 'pemasukan',   'handler' => 'infaq',          'sort' => 7],
        ['kode' => 'ATRIBUT',      'nama' => 'Penjualan Atribut (Seragam/Kitab)', 'jenis' => 'pemasukan', 'handler' => 'umum',      'sort' => 8],
        ['kode' => 'KLINIK',       'nama' => 'Kesehatan / Klinik',          'jenis' => 'pemasukan',   'handler' => 'umum',           'sort' => 9],
        ['kode' => 'CATERING',     'nama' => 'Catering / Laundry',          'jenis' => 'pemasukan',   'handler' => 'umum',           'sort' => 10],
        ['kode' => 'LAINNYA',      'nama' => 'Penerimaan Lain-lain',        'jenis' => 'pemasukan',   'handler' => 'umum',           'sort' => 11],
    ];

    public function run(): void
    {
        foreach (self::DEFAULTS as $row) {
            PosKategori::query()->updateOrCreate(
                ['kode' => $row['kode']],
                [
                    'nama' => $row['nama'],
                    'jenis' => $row['jenis'],
                    'handler' => $row['handler'],
                    'sort' => $row['sort'],
                    'aktif' => true,
                ],
            );
        }
    }
}
