<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class RolesAndPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        $permissions = [
            'view_dashboard',
            'manage_users',
            'manage_roles',
            'manage_tenant_settings',
            'manage_modules',
            'view_santri',
            'manage_santri',
            'view_keuangan',
            'manage_keuangan',
            // Keuangan/Baitul Maal — granular (modul Ledger & turunannya)
            'view_ledger',
            'manage_ledger',
            'close_period',
            'manage_dana_kebajikan',
            'input_belanja',
            'approve_pencairan',
            'manage_pajak',
            'run_payroll',
            'view_slip_sendiri',
            'setor_tabungan',
            'operate_pos',
            'view_laporan_pimpinan',
            'manage_pembiayaan',
            'manage_mutu',
            'approve_mutu',
            'view_notifikasi',
            'manage_notifikasi',
            'view_akademik',
            'manage_akademik',
            'view_own_children',
            // PPDB — portal peserta (akun calon santri ber-role `peserta`)
            'view_own_ppdb_status',
            'complete_own_ppdb',
            'upload_own_ppdb_docs',
            // F1 — Approval Workflow (lintas-modul)
            'kelola_approval',
            // F2 — Audit Trail
            'lihat_audit',
            // F5 — Repositori Dokumen
            'kelola_dokumen',
            // Modul AI (T-A010) — pengaturan Khadim Digital:
            // kelola = admin mengisi/mengajukan Wizard; setujui = kyai/pimpinan;
            // lihat = baca pengaturan & agregat (operator/pembina).
            'ai.setting.kelola',
            'ai.setting.setujui',
            'ai.setting.lihat',
            // Modul AI (T-A030) — kelola sumber pengetahuan RAG (upload,
            // re-ingest, hapus tuntas) Khadim Digital.
            'ai.pengetahuan.kelola',
            // Modul AI (T-A023) — kelola antrean eskalasi "Tanya Ustadz"
            // (tugaskan, jawab, tutup). Laporan kyai = ai.setting.setujui.
            'ai.eskalasi.kelola',
            // Modul AI (T-A040) — Generator Soal & Kisi-kisi: buat draf,
            // edit butir, MENGESAHKAN paket (human-in-the-loop), cetak.
            'ai.generator.soal',
            // Modul AI (T-A041) — Generator RPP / Modul Ajar: buat draf,
            // edit isi seksi, MENGESAHKAN dokumen (human-in-the-loop),
            // cetak/unduh. Kelola template = ai.template.kelola.
            'ai.generator.rpp',
            // Modul AI (T-A041) — kelola template RPP (Wakakurikulum/admin):
            // buat/ubah/nonaktifkan struktur seksi dokumen milik tenant.
            'ai.template.kelola',
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission]);
        }

        $rolePermissions = [
            'super_admin' => Permission::query()->pluck('name')->all(),
            'admin_pesantren' => [
                'view_dashboard',
                'manage_users',
                'manage_roles',
                'manage_tenant_settings',
                'manage_modules',
                'view_santri',
                'manage_santri',
                'view_keuangan',
                'manage_keuangan',
                'view_notifikasi',
                'manage_notifikasi',
                'view_akademik',
                'manage_akademik',
                'kelola_approval',
                'lihat_audit',
                'ai.setting.kelola',
                'ai.setting.lihat',
                'ai.pengetahuan.kelola',
                'ai.eskalasi.kelola',
                'ai.generator.soal',
                'ai.generator.rpp',
                'ai.template.kelola',
            ],
            'santri' => [
                'view_dashboard',
            ],
            'tu' => [
                'view_dashboard',
                'manage_users',
                'view_santri',
                'manage_santri',
                'view_akademik',
            ],
            'keuangan' => [
                'view_dashboard',
                'view_keuangan',
                'manage_keuangan',
                'view_ledger',
                'manage_ledger',
                'manage_dana_kebajikan',
                'input_belanja',
                'manage_pajak',
                'setor_tabungan',
                'operate_pos',
                'view_notifikasi',
                'view_santri',
            ],
            // Alias bendahara — sama persis dengan 'keuangan' (demo seeder memakai role ini).
            'bendahara' => [
                'view_dashboard',
                'view_keuangan',
                'manage_keuangan',
                'view_ledger',
                'manage_ledger',
                'manage_dana_kebajikan',
                'input_belanja',
                'manage_pajak',
                'setor_tabungan',
                'operate_pos',
                'view_notifikasi',
                'view_santri',
            ],
            // --- Peran Baitul Maal (modul Keuangan Digital) ---
            'bendahara_pusat' => [
                'view_dashboard', 'view_keuangan', 'manage_keuangan',
                'view_ledger', 'manage_ledger', 'close_period', 'manage_dana_kebajikan',
                'input_belanja', 'approve_pencairan', 'manage_pajak', 'run_payroll',
                'setor_tabungan', 'operate_pos', 'view_laporan_pimpinan', 'manage_pembiayaan',
                'view_notifikasi', 'view_santri',
            ],
            'kepala_keuangan' => [
                'view_dashboard', 'view_keuangan', 'manage_keuangan',
                'view_ledger', 'manage_ledger', 'close_period', 'manage_dana_kebajikan',
                'approve_pencairan', 'manage_pajak', 'run_payroll',
                'view_laporan_pimpinan', 'manage_pembiayaan', 'manage_mutu', 'approve_mutu',
                'view_notifikasi', 'view_santri',
            ],
            'operator_divisi' => [
                'view_dashboard', 'view_keuangan', 'view_ledger',
                'input_belanja', 'view_santri',
            ],
            'kasir' => [
                'view_dashboard', 'view_keuangan', 'setor_tabungan', 'operate_pos', 'view_santri',
            ],
            'dewan_pembina' => [
                'view_dashboard', 'view_keuangan', 'view_ledger',
                'view_laporan_pimpinan', 'view_santri',
            ],
            'asatidz' => [
                'view_dashboard', 'view_slip_sendiri',
                // T-A023 — ustadz menjawab antrean eskalasi Tanya Ustadz.
                'ai.eskalasi.kelola',
                // T-A040 — asatidz menyusun & mengesahkan paket draf soal.
                'ai.generator.soal',
                // T-A041 — asatidz menyusun & mengesahkan draf RPP/modul ajar
                // (kelola template TIDAK ikut — itu ai.template.kelola).
                'ai.generator.rpp',
            ],
            'guru' => [
                'view_dashboard',
                'view_santri',
                'view_akademik',
                'manage_akademik',
            ],
            'guru_tahfidz' => [
                'view_dashboard',
                'view_santri',
                'view_akademik',
                'manage_akademik',
            ],
            'konselor' => [
                'view_dashboard',
                'view_santri',
            ],
            'pengelola_uks' => [
                'view_dashboard',
                'view_santri',
            ],
            'pengelola_asrama' => [
                'view_dashboard',
                'view_santri',
                'manage_santri',
            ],
            'kepala' => [
                'view_dashboard',
                'view_santri',
                'view_keuangan',
                'view_notifikasi',
                'view_akademik',
                'manage_tenant_settings',
                // Kyai/pimpinan — menyetujui kebijakan AI (T-A010).
                'ai.setting.setujui',
                'ai.setting.lihat',
            ],
            'wali' => [
                'view_own_children',
            ],
            // Peserta PPDB — akun calon santri (portal /portal). Hanya kelola pendaftarannya sendiri.
            'peserta' => [
                'view_own_ppdb_status',
                'complete_own_ppdb',
                'upload_own_ppdb_docs',
            ],
        ];

        foreach ($rolePermissions as $roleName => $grants) {
            $role = Role::firstOrCreate(['name' => $roleName]);
            $role->syncPermissions($grants);
        }
    }
}
