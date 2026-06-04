<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Bersihkan data tenant yang ter-orphan di DATABASE CENTRAL setelah pindah ke
 * database-per-tenant. Central seharusnya HANYA berisi data SaaS:
 * tenants, domains, billing, wilayah, user platform (tenant_id NULL),
 * role platform (super_admin), + tabel framework.
 *
 * AMAN: ada guard agar TIDAK PERNAH berjalan dalam konteks tenant
 * (tidak akan menghapus data DB tenant manapun).
 */
class PurgeCentralOrphans extends Command
{
    protected $signature = 'central:purge-orphans {--force : Jalankan tanpa konfirmasi}';

    protected $description = 'Hapus data operasional/role/user tenant yang ter-orphan di DB central';

    /** Role yang DIPERTAHANKAN di central (role platform). */
    protected array $keepRoles = ['super_admin'];

    /** Tabel operasional milik tenant — dikosongkan di central. */
    protected array $tenantTables = [
        'santri', 'kelas', 'penempatan_kelas', 'status_santri', 'alumni',
        'tagihan', 'pembayaran', 'tabungan', 'tabungan_transaksi',
        'calon_santri', 'calon_santri_dokumen', 'tagihan_ppdb', 'hasil_ujian',
        'periode_akademik', 'pelajaran', 'jadwal_pelajaran', 'nilai', 'rapor',
        'blok_asrama', 'kamar_asrama', 'penempatan_asrama', 'biaya_asrama',
        'tahfidz_hafalan', 'tahfidz_penilaian',
        'riwayat_kesehatan', 'sesi_konseling',
        'kegiatan', 'presensi_kegiatan', 'izin_santri', 'kunjungan_wali',
        'articles', 'galleries', 'gallery_images', 'website_sections', 'website_settings',
        'broadcast_wa', 'log_notifikasi', 'template_notifikasi',
        'settings', 'audit_logs', 'access_wali', 'portal_wali_izin',
    ];

    public function handle(): int
    {
        // GUARD: jangan pernah jalan di konteks tenant
        if (tenancy()->initialized) {
            $this->error('Perintah ini hanya boleh dijalankan di konteks CENTRAL. Dibatalkan.');

            return self::FAILURE;
        }

        if (! $this->option('force') && ! $this->confirm('Hapus data tenant yang ter-orphan di DB central?')) {
            return self::FAILURE;
        }

        // 1. User tenant (platform = tenant_id NULL)
        $users = DB::table('users')->whereNotNull('tenant_id')->delete();
        $this->info("User tenant dihapus: {$users}");

        // 2. Role operasional pesantren (sisakan role platform)
        $tenantRoleIds = DB::table('roles')->whereNotIn('name', $this->keepRoles)->pluck('id')->all();
        if ($tenantRoleIds) {
            DB::table('model_has_roles')->whereIn('role_id', $tenantRoleIds)->delete();
            DB::table('role_has_permissions')->whereIn('role_id', $tenantRoleIds)->delete();
            $roles = DB::table('roles')->whereIn('id', $tenantRoleIds)->delete();
            $this->info("Role tenant dihapus: {$roles}");
        }

        // 3. Kosongkan tabel operasional tenant (CASCADE untuk FK)
        $existing = array_values(array_filter($this->tenantTables, fn ($t) => Schema::hasTable($t)));
        if ($existing) {
            $list = implode(', ', array_map(fn ($t) => '"' . $t . '"', $existing));
            DB::statement("TRUNCATE TABLE {$list} RESTART IDENTITY CASCADE");
            $this->info('Tabel operasional dikosongkan: ' . count($existing));
        }

        $this->info('Selesai — DB central kini hanya berisi data SaaS.');

        return self::SUCCESS;
    }
}
