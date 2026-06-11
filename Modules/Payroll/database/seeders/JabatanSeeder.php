<?php

namespace Modules\Payroll\Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\Payroll\Models\Jabatan;

/**
 * WP-03 — Seeder ±15 jabatan khas pesantren (dari riset Blueprint SDM S-0).
 *
 * role_spatie HANYA dipetakan ke role yang SUDAH ada di
 * RolesAndPermissionsSeeder — yang belum punya padanan dibiarkan NULL
 * (qism/lurah pondok adalah pengurus SANTRI: login via akun santri, bukan
 * akun pegawai — lihat Blueprint Bagian III).
 *
 * Idempoten (firstOrCreate by nama; mapping role hanya diisi saat create —
 * perubahan tenant TIDAK ditimpa). Dipanggil TenantProvisioner di dalam
 * $tenant->run() (pola LembagaSeeder).
 */
class JabatanSeeder extends Seeder
{
    /** @var array<string, array{kategori: string, role: ?string}> */
    public const JABATAN = [
        'Mudir' => ['kategori' => 'struktural', 'role' => 'kepala'],
        'Kepala Madrasah' => ['kategori' => 'struktural', 'role' => 'kepala'],
        'Waka Kurikulum' => ['kategori' => 'struktural', 'role' => 'guru'],
        'Waka Kesiswaan' => ['kategori' => 'struktural', 'role' => 'guru'],
        'Mustahiq / Wali Kelas Diniyah' => ['kategori' => 'pengajar', 'role' => 'guru'],
        'Ustadz Pengabdian' => ['kategori' => 'pengajar', 'role' => 'asatidz'],
        'Muhaffizh' => ['kategori' => 'pengajar', 'role' => 'guru_tahfidz'],
        'Musyrif' => ['kategori' => 'kepengasuhan', 'role' => 'pengelola_asrama'],
        'Lurah Pondok' => ['kategori' => 'pengurus_santri', 'role' => null],
        'Qism Keamanan' => ['kategori' => 'pengurus_santri', 'role' => null],
        'Qism Ibadah' => ['kategori' => 'pengurus_santri', 'role' => null],
        'Qism Bahasa' => ['kategori' => 'pengurus_santri', 'role' => null],
        'Tata Usaha' => ['kategori' => 'staf', 'role' => 'tu'],
        'Bendahara Unit' => ['kategori' => 'staf', 'role' => 'bendahara'],
        'Petugas Kesehatan' => ['kategori' => 'staf', 'role' => 'pengelola_uks'],
    ];

    public function run(): void
    {
        foreach (self::JABATAN as $nama => $def) {
            Jabatan::query()->firstOrCreate(
                ['nama' => $nama],
                ['kategori' => $def['kategori'], 'role_spatie' => $def['role'], 'aktif' => true],
            );
        }
    }
}
