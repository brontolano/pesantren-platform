<?php

namespace Database\Seeders;

use App\Models\ModuleRegistry;
use Illuminate\Database\Seeder;

/**
 * Sumber kebenaran katalog modul platform (CENTRAL only).
 *
 * Menggantikan seed inline di migrasi `create_module_registry_table` sebagai
 * single-source-of-truth yang IDEMPOTEN: aman dipanggil di fresh-install
 * (DatabaseSeeder) maupun backfill tenant lama, dan akan MENGONVERGENSIKAN
 * baris lama ke klasifikasi final (updateOrCreate by `key`).
 *
 * Klasifikasi (keputusan #1, terkunci Hamdan 2026-06-09):
 *   CORE (is_core=true, selalu aktif, tak bisa dimatikan tenant):
 *     core, kesantrian, keuangan, notifikasi, cms
 *   RELEASED (on/off per tenant via tabel tenant_modules):
 *     sisanya.
 *
 * Catatan anti-regresi: gating modul DIPAKSA per-resource lewat
 * ModuleGate::moduleVisible('<key>') (lihat shouldRegisterNavigation/canAccess
 * tiap Resource) — BUKAN lewat label navigationGroup. Modul keuangan-engine
 * (ledger/pajak/payroll/pembiayaan/pengadaan) & mutu terdaftar 'released' dan
 * tiap resource-nya menggerbang sendiri; super_admin tetap melihat semua.
 * Mengubah default OFF→ON massal butuh backfill tabel tenant_modules dulu.
 */
class ModuleRegistrySeeder extends Seeder
{
    /**
     * Katalog final. Urutan = urutan tampil di panel aktivasi.
     *
     * @var list<array{key:string,label:string,status:string,is_core:bool,sort:int}>
     */
    public const CATALOG = [
        // --- CORE (5) — keputusan #1 ---
        ['key' => 'core',        'label' => 'Inti Sistem',           'status' => 'released', 'is_core' => true,  'sort' => 0],
        ['key' => 'kesantrian',  'label' => 'Kesantrian',            'status' => 'released', 'is_core' => true,  'sort' => 1],
        ['key' => 'keuangan',    'label' => 'Keuangan',              'status' => 'released', 'is_core' => true,  'sort' => 2],
        ['key' => 'notifikasi',  'label' => 'Notifikasi WA',         'status' => 'released', 'is_core' => true,  'sort' => 3],
        ['key' => 'cms',         'label' => 'Website & CMS',         'status' => 'released', 'is_core' => true,  'sort' => 4],

        // --- RELEASED (on/off per tenant) — 13 ---
        ['key' => 'ppdb',        'label' => 'PPDB',                  'status' => 'released', 'is_core' => false, 'sort' => 5],
        ['key' => 'portal_wali', 'label' => 'Portal Wali',           'status' => 'released', 'is_core' => false, 'sort' => 6],
        ['key' => 'ledger',      'label' => 'Buku Besar (Akuntansi)', 'status' => 'released', 'is_core' => false, 'sort' => 7],
        ['key' => 'pajak',       'label' => 'Pajak',                 'status' => 'released', 'is_core' => false, 'sort' => 8],
        ['key' => 'payroll',     'label' => 'Payroll',               'status' => 'released', 'is_core' => false, 'sort' => 9],
        ['key' => 'pembiayaan',  'label' => 'Pembiayaan',            'status' => 'released', 'is_core' => false, 'sort' => 10],
        ['key' => 'pengadaan',   'label' => 'Pengadaan',             'status' => 'released', 'is_core' => false, 'sort' => 11],
        ['key' => 'akademik',    'label' => 'Akademik',              'status' => 'released', 'is_core' => false, 'sort' => 12],
        ['key' => 'asrama',      'label' => 'Asrama',                'status' => 'released', 'is_core' => false, 'sort' => 13],
        ['key' => 'tahfidz',     'label' => 'Tahfidz',               'status' => 'released', 'is_core' => false, 'sort' => 14],
        ['key' => 'kesehatan',   'label' => 'Kesehatan & Konseling', 'status' => 'released', 'is_core' => false, 'sort' => 15],
        ['key' => 'kegiatan',    'label' => 'Kegiatan & Perizinan',  'status' => 'released', 'is_core' => false, 'sort' => 16],
        ['key' => 'mutu',        'label' => 'Mutu / SYAMIL',         'status' => 'released', 'is_core' => false, 'sort' => 17],

        // --- PLATFORM / DEVELOPMENT (hanya tenant demo sampai fitur matang) ---
        // file_storage sengaja 'development' (storage tiering belum dirilis) —
        // mempertahankan keputusan migrasi tenant_storage, bukan mengubahnya.
        ['key' => 'file_storage', 'label' => 'File Storage',         'status' => 'development', 'is_core' => false, 'sort' => 18],
        // Modul AI "Khadim Digital" — development sampai Gate A lulus
        // (blueprint modul AI §8): hanya tampil di tenant demo.
        ['key' => 'ai',           'label' => 'AI Khadim Digital',    'status' => 'development', 'is_core' => false, 'sort' => 19],
    ];

    public function run(): void
    {
        foreach (self::CATALOG as $row) {
            ModuleRegistry::query()->updateOrCreate(
                ['key' => $row['key']],
                [
                    'label' => $row['label'],
                    'status' => $row['status'],
                    'is_core' => $row['is_core'],
                    'sort' => $row['sort'],
                ],
            );
        }
    }
}
