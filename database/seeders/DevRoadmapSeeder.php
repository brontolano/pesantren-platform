<?php

namespace Database\Seeders;

use App\Models\DevProject;
use App\Models\DevTask;
use Illuminate\Database\Seeder;

/**
 * Seed papan Manajemen Proyek (CENTRAL) dengan roadmap PPDB v2 + konteks
 * platform yang sudah selesai. Idempotent — aman dijalankan berulang.
 */
class DevRoadmapSeeder extends Seeder
{
    public function run(): void
    {
        $this->seedPpdbV2();
        $this->seedPlatform();
    }

    private function seedPpdbV2(): void
    {
        $project = DevProject::updateOrCreate(
            ['slug' => 'ppdb-portal-v2'],
            [
                'name' => 'PPDB Portal v2',
                'description' => 'Portal calon santri self-service: akun login (No. HP), e-tiket QR, notifikasi WhatsApp (n8n+WAHA), ujian online, pembayaran, ID card, dan konversi ke akun santri aktif.',
                'status' => 'in_progress',
                'color' => 'info',
                'icon' => 'heroicon-o-academic-cap',
                'sort' => 1,
            ]
        );

        // [phase, title, detail, current_state, status, priority, progress, effort, sort]
        $tasks = [
            // Fase 0 — Pondasi Akun
            ['Fase 0 — Pondasi Akun Calon Santri', 'Akun login calon santri (No. HP = username utama)',
                'Setiap pendaftar punya akun (guard/role calon_santri) dengan No. HP sebagai identitas login.',
                'Belum ada. calon_santri kini hanya baris data tanpa akun login.',
                'todo', 'critical', 0, 'M', 1],
            ['Fase 0 — Pondasi Akun Calon Santri', 'Password sementara + paksa reset saat login pertama',
                'Generate password sementara, kirim ke wali, dan paksa ganti password baru saat pertama login.',
                'Belum ada.',
                'todo', 'high', 0, 'S', 2],

            // Fase 1 — Pendaftaran & Onboarding
            ['Fase 1 — Pendaftaran & Onboarding', 'Form pendaftaran online publik (CTA wajib No. HP utama)',
                'Satu form di website tenant; No. HP wajib & menjadi nomor login utama.',
                'Form Blade dasar sudah ada di /ppdb (TenantWebsiteController), tapi belum membuat akun & belum menjadikan No. HP sebagai login.',
                'in_progress', 'high', 40, 'M', 3],
            ['Fase 1 — Pendaftaran & Onboarding', 'Generate E-Tiket QR Code Nomor Peserta (PDF + visual)',
                'E-tiket berisi Nomor Peserta, Nama, No. HP, Username & Password sementara + QR code; bisa diunduh PDF.',
                'Nomor pendaftaran (PPDB-YYYY-xxxx) + tracking_token sudah ada. QR code, e-tiket, dan kredensial belum ada. Perlu lib QR + PDF.',
                'todo', 'high', 15, 'M', 4],
            ['Fase 1 — Pendaftaran & Onboarding', 'Notifikasi WhatsApp via webhook n8n + WAHA',
                'Saat daftar/keputusan, app menembak webhook n8n yang meneruskan ke WAHA sendText.',
                'Workflow n8n+WAHA (ppdb-wa) sudah ada sebagai template. Sisi Laravel (kirim webhook + template pesan) belum dibangun.',
                'in_progress', 'high', 30, 'M', 5],
            ['Fase 1 — Pendaftaran & Onboarding', 'Pengaturan integrasi WA per tenant (URL webhook, template, toggle)',
                'Menu setting tenant untuk URL webhook n8n, template pesan, dan on/off notifikasi.',
                'Belum ada.',
                'todo', 'medium', 0, 'S', 6],

            // Fase 2 — Portal Calon Santri (frontend user)
            ['Fase 2 — Portal Calon Santri', 'Isi data lengkap: diri, orang tua, wali',
                'Form bertahap untuk biodata calon, data ayah/ibu, dan data wali.',
                'Field dasar sudah ada di tabel calon_santri. UI lengkap & terstruktur untuk user belum ada.',
                'todo', 'medium', 20, 'M', 7],
            ['Fase 2 — Portal Calon Santri', 'Upload dokumen pendukung (image / PDF)',
                'User unggah KK, akta, ijazah, pas foto, dll (image/pdf) dari portal.',
                'Model CalonSantriDokumen + PpdbDocumentUploadService sudah ada (sisi admin). UI upload untuk user belum ada.',
                'todo', 'medium', 25, 'M', 8],
            ['Fase 2 — Portal Calon Santri', 'Halaman tagihan, jadwal seleksi & info daftar ulang',
                'User melihat tagihan, jadwal/lokasi seleksi, dan instruksi daftar ulang.',
                'Billing tersedia sisi admin (PpdbBillingService → Keuangan). Tampilan untuk user belum ada.',
                'todo', 'medium', 10, 'M', 9],
            ['Fase 2 — Portal Calon Santri', 'Fitur pembayaran (gateway / konfirmasi)',
                'Pembayaran biaya pendaftaran/daftar ulang online atau konfirmasi manual.',
                'Tagihan Keuangan sudah ada. Pembayaran online (gateway) belum ada.',
                'todo', 'medium', 0, 'L', 10],

            // Fase 3 — Admin PPDB
            ['Fase 3 — Admin PPDB', 'CRUD peserta + monitoring + approval',
                'Admin kelola pendaftar, pantau status, dan setujui/tolak.',
                'Filament CalonSantriResource + PpdbAdmissionDecisionService (lulus/cadangan/ditolak) sudah ada.',
                'in_progress', 'medium', 55, 'M', 11],
            ['Fase 3 — Admin PPDB', 'Push notifikasi on-app / WhatsApp dari admin',
                'Admin kirim broadcast/notifikasi ke peserta lewat in-app & WhatsApp.',
                'Belum ada (tergantung integrasi WA fase 1).',
                'todo', 'medium', 10, 'M', 12],

            // Fase 4 — Ujian Online
            ['Fase 4 — Ujian Online', 'Bank soal + jawaban (admin membuat soal)',
                'Admin membuat bank soal (pilihan ganda/essay) beserta kunci jawaban.',
                'Saat ini hanya input nilai manual (HasilUjian). Bank soal belum ada.',
                'todo', 'medium', 5, 'L', 13],
            ['Fase 4 — Ujian Online', 'Ujian online bertimer + auto-grading + nilai manual',
                'User mengerjakan soal dengan timer; nilai otomatis untuk PG, manual untuk essay.',
                'Belum ada.',
                'todo', 'medium', 0, 'L', 14],

            // Fase 5 — Daftar Ulang & Konversi
            ['Fase 5 — Daftar Ulang & Konversi', 'Penempatan kelas & asrama saat daftar ulang',
                'Santri lulus + lunas datang ke pondok; sistem menetapkan kelas & asrama.',
                'Registrasi ulang → membuat record Santri sudah ada (PpdbReregistrationService). Integrasi penempatan kelas & asrama di alur belum ada.',
                'in_progress', 'high', 30, 'M', 15],
            ['Fase 5 — Daftar Ulang & Konversi', 'Auto-generate ID Card santri (cetak PDF + visual di akun)',
                'Sistem otomatis membuat ID card yang bisa dicetak PDF & tampil sebagai kartu visual di akun santri.',
                'Belum ada. Perlu lib PDF + template kartu.',
                'todo', 'medium', 0, 'M', 16],
            ['Fase 5 — Daftar Ulang & Konversi', 'Konversi akun calon → akun santri aktif (login app santri/wali)',
                'Akun calon dikonversi jadi akun santri aktif untuk login via aplikasi santri / orang tua.',
                'Konversi data ke Santri sudah ada. Akun login santri/wali untuk aplikasi belum ada.',
                'todo', 'high', 15, 'M', 17],
        ];

        foreach ($tasks as [$phase, $title, $detail, $current, $status, $priority, $progress, $effort, $sort]) {
            DevTask::updateOrCreate(
                ['dev_project_id' => $project->id, 'title' => $title],
                [
                    'phase' => $phase,
                    'detail' => $detail,
                    'current_state' => $current,
                    'status' => $status,
                    'priority' => $priority,
                    'progress' => $progress,
                    'effort' => $effort,
                    'sort' => $sort,
                ]
            );
        }
    }

    private function seedPlatform(): void
    {
        $project = DevProject::updateOrCreate(
            ['slug' => 'platform-infrastruktur'],
            [
                'name' => 'Platform & Infrastruktur',
                'description' => 'Fondasi SaaS: isolasi database per tenant, deploy, SSL, backup, landing, manajemen modul, billing.',
                'status' => 'in_progress',
                'color' => 'primary',
                'icon' => 'heroicon-o-server-stack',
                'sort' => 2,
            ]
        );

        $tasks = [
            ['Fondasi', 'Database-per-tenant + recovery tools', 'Isolasi fisik DB per tenant + backup/restore.', 'Selesai & terverifikasi (central ↔ tenantdemo terpisah).', 'done', 'high', 100, 'L', 1],
            ['Fondasi', 'Auto-deploy (pull-based) + on-demand SSL', 'Deploy via /opt/deploy.sh + TLS otomatis.', 'Selesai. Deploy hanya saat maintenance window.', 'done', 'high', 100, 'M', 2],
            ['Fondasi', 'Backup DB harian 00:00 WIB', 'Cron pg_dump harian.', 'Selesai (postgresql-client-16).', 'done', 'high', 100, 'S', 3],
            ['Platform SaaS', 'Landing page + halaman legal', 'Landing pesantren.online + 5 halaman legal.', 'Selesai.', 'done', 'medium', 100, 'M', 4],
            ['Platform SaaS', 'Manajemen Modul (2 mode + CRUD + sebar)', 'Released vs development, CRUD, set release, sebar ke tenant.', 'Selesai & terverifikasi.', 'done', 'high', 100, 'M', 5],
            ['Platform SaaS', 'Kalkulator Langganan', 'Kalkulator billing di panel central.', 'Selesai (gated central-only).', 'done', 'medium', 100, 'S', 6],
            ['Platform SaaS', 'Manajemen Proyek (papan progres ini)', 'CRUD proyek & task + widget progres di dashboard.', 'Selesai — papan ini.', 'done', 'medium', 100, 'M', 7],
        ];

        foreach ($tasks as [$phase, $title, $detail, $current, $status, $priority, $progress, $effort, $sort]) {
            DevTask::updateOrCreate(
                ['dev_project_id' => $project->id, 'title' => $title],
                [
                    'phase' => $phase,
                    'detail' => $detail,
                    'current_state' => $current,
                    'status' => $status,
                    'priority' => $priority,
                    'progress' => $progress,
                    'effort' => $effort,
                    'sort' => $sort,
                ]
            );
        }
    }
}
