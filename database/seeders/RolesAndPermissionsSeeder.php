<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RolesAndPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

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
            // F1-PWA — gerbang masuk PWA Guru (/guru): guru mapel, guru
            // tahfidz/musyrif, asatidz, dan kepala (pemantauan).
            'akses_pwa_guru',
            // WP-35 — gerbang Dasbor & Approval Pimpinan dari HP (/pimpinan):
            // kepala/kyai (dan super_admin). Deploy: butuh tenant:reprovision.
            'akses_pimpinan',
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
            // Modul AI (T-A042) — Ikhtisar materi kitab & kerangka kultum:
            // buat draf, edit isi, MENGESAHKAN (human-in-the-loop), cetak.
            // Kultum tetap digerbang feature flag opt-in (generator_kultum).
            'ai.generator.ikhtisar',
            // Modul AI (T-A052) — Kotak Masuk WA chatbot (admin/TU):
            // lihat percakapan (nomor tersamar), ambil alih (handoff),
            // balas manual via jalur outbound resmi, kembalikan ke bot,
            // tutup; juga penerima notifikasi handoff baru.
            'ai.chat.kelola',
            // 1-aplikasi (Fase 2b/2c) — peran publik 'jemaah' (login no-WA +
            // password): boleh berkomentar di konten Beranda publik. Tamu (tanpa
            // login) read-only tak punya izin ini. Moderasi komentar tetap di staf.
            'komentar',
            // 1-aplikasi (Fase 2c) — moderasi komentar publik (setujui/tolak
            // antrean). Hanya staf moderator (admin/TU/kepala).
            'moderasi_komentar',
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
                'manage_mutu',
                'kelola_approval',
                'lihat_audit',
                'ai.setting.kelola',
                'ai.setting.lihat',
                'ai.pengetahuan.kelola',
                'ai.eskalasi.kelola',
                'ai.generator.soal',
                'ai.generator.rpp',
                'ai.template.kelola',
                'ai.generator.ikhtisar',
                'ai.chat.kelola',
                // 1-aplikasi (Fase 2c) — moderasi komentar publik.
                'moderasi_komentar',
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
                // T-A052 — TU ikut melayani Kotak Masuk WA chatbot.
                'ai.chat.kelola',
                // 1-aplikasi (Fase 2c) — moderasi komentar publik.
                'moderasi_komentar',
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
                // T-A042 — asatidz menyusun & mengesahkan ikhtisar kitab
                // (dan kerangka kultum bila fitur opt-in diaktifkan tenant).
                'ai.generator.ikhtisar',
                // F1-PWA — asatidz juga pengajar lapangan.
                'akses_pwa_guru',
            ],
            'guru' => [
                'view_dashboard',
                'view_santri',
                'view_akademik',
                'manage_akademik',
                'akses_pwa_guru',
                // F3-PWA — Slip Saya: guru melihat/cetak slip MILIKNYA sendiri.
                'view_slip_sendiri',
            ],
            'guru_tahfidz' => [
                'view_dashboard',
                'view_santri',
                'view_akademik',
                'manage_akademik',
                'akses_pwa_guru',
                // F3-PWA — Slip Saya: guru melihat/cetak slip MILIKNYA sendiri.
                'view_slip_sendiri',
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
                // SPMI — kepala melihat & MENYETUJUI mutu (segregasi: admin_pesantren
                // hanya menyusun/manage, tak punya approve_mutu).
                'manage_mutu',
                'approve_mutu',
                // Kyai/pimpinan — menyetujui kebijakan AI (T-A010).
                'ai.setting.setujui',
                'ai.setting.lihat',
                // F1-PWA — kepala memantau lewat PWA Guru.
                'akses_pwa_guru',
                // WP-35 — dasbor & approval pimpinan dari HP.
                'akses_pimpinan',
                // 1-aplikasi (Fase 2c) — moderasi komentar publik.
                'moderasi_komentar',
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
            // 1-aplikasi (Fase 2b) — jemaah publik: login no-WA + password (registrasi
            // mandiri). TIDAK punya akses panel/dashboard staf; hanya 'komentar' di
            // Beranda publik. Tamu (tanpa login) tetap read-only tanpa izin apa pun.
            'jemaah' => [
                'komentar',
            ],
        ];

        // 1-aplikasi (Fase 2c) — izin 'komentar' untuk SEMUA peran komunitas
        // (staf internal + wali + santri + jemaah). DIKECUALIKAN: 'peserta'
        // (calon santri EKSTERNAL, di luar ruang komunitas) & 'super_admin'
        // (sudah punya semua izin). Programatik agar tak terlewat saat peran
        // baru ditambahkan.
        foreach (array_keys($rolePermissions) as $roleName) {
            if (in_array($roleName, ['peserta', 'super_admin'], true)) {
                continue;
            }
            if (! in_array('komentar', $rolePermissions[$roleName], true)) {
                $rolePermissions[$roleName][] = 'komentar';
            }
        }

        foreach ($rolePermissions as $roleName => $grants) {
            $role = Role::firstOrCreate(['name' => $roleName]);
            $role->syncPermissions($grants);
        }
    }
}
