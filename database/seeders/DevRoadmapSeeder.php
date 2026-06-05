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
        $this->seedIntegrasiWebsite();
        $this->seedFileStorage();
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

    private function seedIntegrasiWebsite(): void
    {
        $project = DevProject::updateOrCreate(
            ['slug' => 'integrasi-website-api'],
            [
                'name' => 'Integrasi Website / API',
                'description' => 'Integrasi website pesantren (WordPress/custom, dikelola Hamdan atau pesantren) dengan modul platform via 3 pola: subdomain delegation, embed (iframe/widget), dan API (REST+Sanctum). Tenant-scoped & terisolasi. Kontrak API website Asy-Syifaa terdokumentasi di docs/SPEC-API-WEBSITE-ASY-SYIFAA.md.',
                'status' => 'planning',
                'color' => 'warning',
                'icon' => 'heroicon-o-globe-alt',
                'sort' => 3,
            ]
        );

        // [phase, title, detail, current_state, status, priority, progress, effort, sort]
        $tasks = [
            // Fase A — Subdomain Delegation (tercepat)
            ['Fase A — Subdomain Delegation', 'SOP & flow subdomain delegation (ppdb./portal. → platform)',
                'Dokumentasikan cara arahkan subdomain tenant ke platform + dapat auto-SSL.',
                'Custom domain + on-demand TLS sudah jalan. Tinggal SOP + registrasi domain tenant.',
                'todo', 'high', 20, 'S', 1],
            ['Fase A — Subdomain Delegation', 'Registrasi & verifikasi kepemilikan domain/subdomain tenant',
                'Tenant daftarkan domain; sistem verifikasi kepemilikan sebelum delegasi.',
                'Model Domain + InitializeTenancyByDomain ada. UI registrasi/verifikasi belum.',
                'todo', 'medium', 15, 'M', 2],

            // Fase B — Embed
            ['Fase B — Embed (iframe/widget)', 'Halaman PPDB iframe-friendly (X-Frame-Options/CSP per domain)',
                'Siapkan form PPDB agar bisa di-embed ke situs WP/custom dengan aman.',
                'Form PPDB ada, tapi belum disiapkan untuk embed lintas-domain.',
                'todo', 'medium', 10, 'M', 3],
            ['Fase B — Embed (iframe/widget)', 'Widget <script> embed (statistik / berita / tombol PPDB)',
                'Snippet JS yang bisa ditempel di halaman mana pun untuk menampilkan data dinamis.',
                'Belum ada.',
                'todo', 'low', 0, 'M', 4],

            // Fase C — API Publik
            ['Fase C — API Publik (headless)', 'API publik read: profil tenant, statistik, artikel/berita, jadwal PPDB',
                'Endpoint baca data tenant untuk dikonsumsi situs eksternal (headless).',
                'API baru ada data wilayah + skeleton PPDB v1 (Sanctum). API konten/statistik belum.',
                'todo', 'high', 10, 'L', 5],
            ['Fase C — API Publik (headless)', 'API submit PPDB dari form website eksternal + anti-spam',
                'Endpoint terima pendaftaran dari form di situs tenant; proteksi spam/captcha.',
                'Belum ada endpoint publik submit.',
                'todo', 'medium', 5, 'M', 6],
            ['Fase C — API Publik (headless)', "Token/API key per tenant + halaman 'Integrasi & API' di dashboard tenant",
                'Tenant generate API key sendiri; kelola di dashboard tenant.',
                'Sanctum terpasang. Manajemen token per tenant belum ada.',
                'todo', 'high', 5, 'M', 7],
            ['Fase C — API Publik (headless)', 'CORS per domain tenant + rate-limit (jaga isolasi antar pesantren)',
                'CORS whitelist per domain tenant (bukan wildcard) + rate-limit endpoint publik.',
                'Belum dikonfigurasi. WAJIB: token tenant A tak boleh akses data tenant B.',
                'todo', 'high', 0, 'S', 8],

            // Fase D — Distribusi & Uji Coba
            ['Fase D — Distribusi & Uji Coba', 'Dokumentasi integrasi untuk developer pesantren',
                'Panduan integrasi (3 pola) + contoh kode untuk tim website pesantren.',
                'Belum ada.',
                'todo', 'medium', 0, 'M', 9],
            ['Fase D — Distribusi & Uji Coba', 'Plugin WordPress resmi (embed PPDB + statistik via API)',
                'Plugin siap-pasang untuk pesantren yang pakai WordPress.',
                'Belum ada.',
                'todo', 'low', 0, 'L', 10],
            ['Fase D — Distribusi & Uji Coba', 'Uji coba dengan client pertama: Asy-Syifaa',
                'Situs PHP custom asy-syifaa.com (thin-client) diintegrasikan ke API platform via pola C.',
                'Sistem tenant asy-syifaa SUDAH LIVE (pesantren.asy-syifaa.com + fallback). Tinggal bangun API + repoint website.',
                'in_progress', 'high', 20, 'M', 11],

            // Kontrak konkret dari studi website asy-syifaa (lihat docs/SPEC-API-WEBSITE-ASY-SYIFAA.md)
            ['Fase C — API Publik (headless)', 'API #1: POST /api/v1/spmb/register (kontrak website asy-syifaa)',
                'Endpoint pendaftaran sesuai kontrak; balas eticket_no + registration_number + username (= PPDB v2 Fase 1).',
                'Website sudah memanggil endpoint ini & mengharapkan e-tiket+username. Belum ada di platform.',
                'todo', 'high', 0, 'M', 12],
            ['Fase D — Distribusi & Uji Coba', 'Repoint api.asy-syifaa.com → API platform + tes end-to-end',
                'Arahkan base URL website ke API platform (resolusi tenant by-domain), uji semua halaman dinamis.',
                'DNS api.asy-syifaa.com sudah ke VPS. Menunggu endpoint platform siap.',
                'todo', 'high', 0, 'M', 13],
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

    private function seedFileStorage(): void
    {
        $project = DevProject::updateOrCreate(
            ['slug' => 'file-storage'],
            [
                'name' => 'File Storage',
                'description' => 'Penyimpanan media per tenant berkuota (model "disk OS"): default 8 GB auto saat tenant dibuat, upgrade manual/billing. Tier 8/16/32 GB di disk lokal; 64/128/256 GB menyusul (butuh object storage S3). VPS 96 GB.',
                'status' => 'in_progress',
                'color' => 'info',
                'icon' => 'heroicon-o-circle-stack',
                'sort' => 4,
            ]
        );

        $tasks = [
            ['Fase 1 — MVP (disk lokal)', 'Module + media_files + tenant_storage + StorageQuotaService',
                'Skema kuota (central) + file (tenant DB) + service hitung pakai/sisa.',
                'Selesai dibangun — TenantStorage, MediaFile, StorageQuotaService.',
                'done', 'high', 100, 'M', 1],
            ['Fase 1 — MVP (disk lokal)', 'UI upload media + enforcement kuota + widget pemakaian',
                'MediaFileResource (panel tenant): upload, daftar, hapus, blokir saat kuota penuh, widget X/Y GB.',
                'Selesai — resource + CreateMediaFile (cek kuota) + StorageQuotaWidget.',
                'done', 'high', 100, 'M', 2],
            ['Fase 1 — MVP (disk lokal)', 'Kuota default 8 GB auto (TenantProvisioner) + demo 2 GB',
                'Tiap tenant baru otomatis 8 GB; demo di-set 2 GB.',
                'Selesai — provisioner set default; demo 2 GB.',
                'done', 'high', 100, 'S', 3],
            ['Fase 2 — Manajemen & Billing', 'Menu kelola kuota per tenant (super_admin)',
                'Central: super_admin atur tier kuota per tenant (Radio 8/16/32; 64/128/256 disable bila belum ada object storage).',
                'Selesai — menu Kuota Penyimpanan. Tie-in paket langganan = opsional nanti.',
                'done', 'medium', 100, 'M', 4],
            ['Fase 3 — Object Storage', 'Driver S3-compatible → unlock tier 64/128/256 GB',
                'Config disk (MEDIA_DISK/MEDIA_OBJECT_STORAGE) + gating tier sudah siap; tinggal sambung akun object storage + migrasi file.',
                'Readiness selesai (config + gating). Aktivasi butuh akun S3 (Hostinger/Wasabi/B2). VPS lokal hanya muat ~8 tenant @8GB.',
                'in_progress', 'high', 40, 'L', 5],
            ['Fase 4 — Penyempurnaan', 'Thumbnail, folder, trash/restore, filter, bulk',
                'Pratinjau gambar, organisasi folder, soft-delete + restore + kosongkan sampah, filter tipe/folder, aksi massal.',
                'Selesai — semua diimplementasikan di MediaFileResource.',
                'done', 'medium', 100, 'M', 6],
            ['Fase 4 — Penyempurnaan', 'Kompresi gambar + audit log (lanjutan)',
                'Optimasi/resize gambar saat upload (butuh intervention/image) + jejak audit upload/hapus.',
                'Belum — pelengkap opsional.',
                'todo', 'low', 0, 'M', 7],
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
