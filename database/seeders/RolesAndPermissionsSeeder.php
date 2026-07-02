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
            // Keuangan granular per-posisi (2026-06-28) — pecah view/manage_keuangan
            // yg kasar agar tiap posisi keuangan lihat fungsinya saja (kasir≠akuntan
            // ≠bendahara). Lihat breakdown posisi keuangan.
            'view_tagihan',
            'manage_tagihan',
            'view_tabungan',
            'manage_tabungan',
            'view_donasi',
            'manage_donasi',
            'view_laporan_keuangan',
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
            // Granular per-modul spesialis (2026-06-28) — agar visibilitas modul
            // selaras FUNGSI peran, bukan menumpang view_santri/view_akademik yang
            // membuat petugas UKS/konselor/asrama melihat modul di luar fungsinya.
            'view_kesehatan',
            'manage_kesehatan',
            'view_konseling',
            'manage_konseling',
            'view_asrama',
            'manage_asrama',
            'view_kegiatan',
            'manage_kegiatan',
            'view_tahfidz',
            'manage_tahfidz',
            // Kedisiplinan & Alumni — dipisah dari view_santri agar peran keuangan
            // (yang punya view_santri utk referensi roster) TIDAK ikut melihat menu
            // pelanggaran/alumni di luar fungsinya.
            'view_kedisiplinan',
            'manage_kedisiplinan',
            'view_alumni',
            'manage_alumni',
            // PPDB/Pendaftaran (panitia) & Sarpras/Aset (petugas) — dipisah dari
            // view_santri agar tak bocor ke peran lain (mis. bendahara/guru).
            'view_ppdb',
            'manage_ppdb',
            'view_sarpras',
            'manage_sarpras',
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

        // Grant kas/keuangan operasional — dipakai bersama oleh 'keuangan' &
        // aliasnya 'bendahara' (satu sumber, mencegah dua daftar drift).
        // BENDAHARA (unit) = operasional kas: tagihan + tabungan + donasi + laporan
        // + lihat pembukuan. Pembukuan PENUH (manage_ledger) = akuntan/kepala_keuangan.
        $kasKeuanganGrants = [
            'view_dashboard',
            'view_santri',
            'view_tagihan',
            'manage_tagihan',
            'view_tabungan',
            'manage_tabungan',
            'setor_tabungan',
            'operate_pos',
            'view_donasi',
            'manage_donasi',
            'view_laporan_keuangan',
            'view_ledger',
            'manage_dana_kebajikan',
            'input_belanja',
            'manage_pajak',
            'view_notifikasi',
        ];

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
                'view_tagihan',
                'manage_tagihan',
                'view_tabungan',
                'manage_tabungan',
                'view_donasi',
                'manage_donasi',
                'view_laporan_keuangan',
                'view_ledger',
                'manage_ledger',
                'view_notifikasi',
                'manage_notifikasi',
                'view_akademik',
                'manage_akademik',
                'view_kesehatan',
                'manage_kesehatan',
                'view_konseling',
                'manage_konseling',
                'view_asrama',
                'manage_asrama',
                'view_kegiatan',
                'manage_kegiatan',
                'view_tahfidz',
                'manage_tahfidz',
                'view_kedisiplinan',
                'manage_kedisiplinan',
                'view_alumni',
                'manage_alumni',
                'view_ppdb',
                'manage_ppdb',
                'view_sarpras',
                'manage_sarpras',
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
                'view_asrama',
                'manage_asrama',
                'view_kegiatan',
                'manage_kegiatan',
                'view_kedisiplinan',
                'manage_kedisiplinan',
                'view_alumni',
                'manage_alumni',
                'view_ppdb',
                'manage_ppdb',
                // T-A052 — TU ikut melayani Kotak Masuk WA chatbot.
                'ai.chat.kelola',
                // 1-aplikasi (Fase 2c) — moderasi komentar publik.
                'moderasi_komentar',
            ],
            'keuangan' => $kasKeuanganGrants,
            // Alias bendahara — sama persis dengan 'keuangan' (demo seeder memakai role ini).
            'bendahara' => $kasKeuanganGrants,
            // --- Peran Baitul Maal (modul Keuangan Digital) ---
            // Bendahara PUSAT/Yayasan = pimpinan keuangan menyeluruh (semua + approval
            // pencairan, tutup buku, payroll, pembiayaan).
            'bendahara_pusat' => [
                'view_dashboard', 'view_santri',
                'view_tagihan', 'manage_tagihan', 'view_tabungan', 'manage_tabungan',
                'setor_tabungan', 'operate_pos', 'view_donasi', 'manage_donasi',
                'view_laporan_keuangan', 'view_ledger', 'manage_ledger', 'close_period',
                'manage_dana_kebajikan', 'input_belanja', 'approve_pencairan', 'manage_pajak',
                'run_payroll', 'view_laporan_pimpinan', 'manage_pembiayaan', 'view_notifikasi',
            ],
            // Kepala Keuangan = pimpinan departemen keuangan (semua kecuali operasional
            // kasir harian; fokus approval, pembukuan, laporan, mutu).
            'kepala_keuangan' => [
                'view_dashboard', 'view_santri',
                'view_tagihan', 'manage_tagihan', 'view_tabungan', 'manage_tabungan',
                'view_donasi', 'manage_donasi', 'view_laporan_keuangan',
                'view_ledger', 'manage_ledger', 'close_period', 'manage_dana_kebajikan',
                'approve_pencairan', 'manage_pajak', 'run_payroll', 'view_laporan_pimpinan',
                'manage_pembiayaan', 'manage_mutu', 'approve_mutu', 'view_notifikasi',
            ],
            // Akuntan / Tata Buku = pembukuan double-entry (jurnal, buku besar, neraca,
            // COA, laporan keuangan). TANPA operasional kas/tagihan/payroll.
            'akuntan' => [
                'view_dashboard', 'view_santri',
                'view_ledger', 'manage_ledger', 'view_laporan_keuangan',
            ],
            // Petugas Payroll / Bisyaroh = penggajian & kepegawaian (run_payroll
            // menggerbang semua menu Penggajian + Kepegawaian).
            'petugas_payroll' => [
                'view_dashboard', 'view_santri', 'run_payroll',
            ],
            // Operator Divisi/Unit = input belanja unitnya + lihat pembukuan.
            'operator_divisi' => [
                'view_dashboard', 'view_santri', 'input_belanja', 'view_ledger',
            ],
            // Kasir = kasir kantin/loket + setor tabungan. TANPA tagihan/laporan/pembukuan.
            'kasir' => [
                'view_dashboard', 'view_santri', 'operate_pos', 'setor_tabungan', 'view_tabungan',
            ],
            // Dewan Pembina = pengawas: lihat laporan keuangan & pimpinan (read-only).
            'dewan_pembina' => [
                'view_dashboard', 'view_santri',
                'view_laporan_keuangan', 'view_ledger', 'view_laporan_pimpinan',
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
                // Pengajar tahfidz/kitab — modul Tahfidz.
                'view_tahfidz',
                'manage_tahfidz',
            ],
            'guru' => [
                'view_dashboard',
                'view_santri',
                'view_akademik',
                'manage_akademik',
                'akses_pwa_guru',
                // Wali kelas/Waka Kesiswaan merujuk catatan kedisiplinan (read).
                'view_kedisiplinan',
                // F3-PWA — Slip Saya: guru melihat/cetak slip MILIKNYA sendiri.
                'view_slip_sendiri',
            ],
            'guru_tahfidz' => [
                'view_dashboard',
                'view_santri',
                'view_akademik',
                'manage_akademik',
                // Modul Tahfidz — musyrif/guru tahfidz.
                'view_tahfidz',
                'manage_tahfidz',
                'akses_pwa_guru',
                // Musyrif tahfidz merujuk catatan kedisiplinan (read).
                'view_kedisiplinan',
                // F3-PWA — Slip Saya: guru melihat/cetak slip MILIKNYA sendiri.
                'view_slip_sendiri',
            ],
            'konselor' => [
                'view_dashboard',
                'view_santri',
                // Modul Konseling (sesi konseling) — terpisah dari UKS medis.
                'view_konseling',
                'manage_konseling',
                // Konteks kedisiplinan untuk pendampingan (read).
                'view_kedisiplinan',
            ],
            'pengelola_uks' => [
                'view_dashboard',
                'view_santri',
                // Modul Kesehatan/UKS — kunjungan, obat, riwayat kesehatan.
                'view_kesehatan',
                'manage_kesehatan',
            ],
            'pengelola_asrama' => [
                'view_dashboard',
                'view_santri',
                // Modul Asrama — blok/kamar/penempatan + biaya asrama.
                'view_asrama',
                'manage_asrama',
                // Musyrif menegakkan & mencatat kedisiplinan santri asrama.
                'view_kedisiplinan',
                'manage_kedisiplinan',
            ],
            'kepala' => [
                'view_dashboard',
                'view_santri',
                'view_keuangan',
                'view_notifikasi',
                'view_akademik',
                // Pengawasan pimpinan (view-only) atas modul operasional.
                'view_kesehatan',
                'view_konseling',
                'view_asrama',
                'view_kegiatan',
                'view_tahfidz',
                'view_kedisiplinan',
                'view_alumni',
                'view_ppdb',
                'view_sarpras',
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
            // IT — admin teknis/sistem (sebelumnya ada di DB tanpa definisi seeder).
            // Fokus infrastruktur: modul, user/role, pengaturan, audit, dokumen.
            // TANPA akses data santri/keuangan secara default (bukan fungsinya).
            'IT' => [
                'view_dashboard',
                'manage_modules',
                'manage_users',
                'manage_roles',
                'manage_tenant_settings',
                'lihat_audit',
                'kelola_dokumen',
                'view_notifikasi',
                'manage_notifikasi',
            ],
            // Panitia PPDB — kelola pendaftaran (calon santri, gelombang, dokumen,
            // laporan). Lihat roster santri utk rujukan. TANPA akses keuangan/akademik.
            'panitia_ppdb' => [
                'view_dashboard',
                'view_santri',
                'view_ppdb',
                'manage_ppdb',
            ],
            // Petugas Sarpras — kelola sarana/prasarana, area piket, laporan kerusakan.
            'pengelola_sarpras' => [
                'view_dashboard',
                'view_sarpras',
                'manage_sarpras',
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
