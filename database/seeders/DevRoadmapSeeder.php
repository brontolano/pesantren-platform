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
        $this->seedPpdbAdmin();
        $this->seedAppSantri();
        $this->seedPwaRoadmap();
        $this->seedIntegrasiWebsite();
        $this->seedFileStorage();
        $this->seedPlatform();
    }

    /**
     * Roadmap PWA Peserta SPMB + PWA Wali — di-seed dari hasil ekstraksi PRD
     * (database/seeders/data/pwa-roadmap.json, lihat PRD-PWA-PESERTA-SPMB.md & PRD-PWA-WALI.md).
     * Idempotent: updateOrCreate by project.slug + task.title.
     */
    private function seedPwaRoadmap(): void
    {
        $path = database_path('seeders/data/pwa-roadmap.json');
        if (! is_file($path)) {
            return;
        }

        $projects = json_decode((string) file_get_contents($path), true) ?: [];
        $dec = static fn ($s): string => html_entity_decode((string) $s, ENT_QUOTES | ENT_HTML5, 'UTF-8');

        foreach ($projects as $i => $pj) {
            $project = DevProject::updateOrCreate(
                ['slug' => $pj['project']['slug']],
                [
                    'name' => $dec($pj['project']['name']),
                    'description' => $dec($pj['project']['description']),
                    'status' => $pj['project']['status'],
                    'color' => $pj['project']['color'],
                    'icon' => $pj['project']['icon'],
                    'sort' => 5 + $i,
                ]
            );

            foreach ($pj['tasks'] as $t) {
                DevTask::updateOrCreate(
                    ['dev_project_id' => $project->id, 'title' => $dec($t['title'])],
                    [
                        'phase' => $dec($t['phase']),
                        'detail' => $dec($t['detail']),
                        'current_state' => $dec($t['current_state']),
                        'status' => $t['status'],
                        'priority' => $t['priority'],
                        'progress' => (int) $t['progress'],
                        'effort' => $t['effort'],
                        'sort' => (int) $t['sort'],
                    ]
                );
            }
        }
    }

    private function seedPpdbAdmin(): void
    {
        // Proyek lama "PPDB Portal v2" kini dipecah: PWA Peserta SPMB (frontend
        // pendaftar), PWA Wali, App Santri, dan proyek admin/ERP ini. Bersihkan
        // yang lama supaya papan tidak tumpang-tindih (cascade hapus task-nya).
        DevProject::where('slug', 'ppdb-portal-v2')->delete();

        $project = DevProject::updateOrCreate(
            ['slug' => 'ppdb-admin-ujian'],
            [
                'name' => 'PPDB Admin & Ujian (ERP)',
                'description' => 'Sisi ADMIN/panitia PPDB di ERP (Filament) + mesin ujian online — DI LUAR PWA peserta: CRUD/monitoring/approval pendaftar, pengaturan notifikasi WA per tenant, bank soal & ujian bertimer auto-grade, penempatan kelas/asrama saat daftar ulang, dan generator ID Card santri.',
                'status' => 'in_progress',
                'color' => 'primary',
                'icon' => 'heroicon-o-clipboard-document-check',
                'sort' => 1,
            ]
        );

        // [phase, title, detail, current_state, status, priority, progress, effort, sort]
        $tasks = [
            ['Admin & Notifikasi', 'CRUD peserta + monitoring + approval (Filament)',
                'Panitia kelola pendaftar, pantau status, setujui/tolak (lulus/cadangan/ditolak).',
                'Sebagian besar SUDAH ADA: CalonSantriResource + PpdbAdmissionDecisionService + aksi re-issue OTP/koreksi No.WA dari ERP.',
                'in_progress', 'high', 60, 'M', 1],
            ['Admin & Notifikasi', 'Pengaturan integrasi WA per tenant (webhook n8n, template, toggle)',
                'Menu setting tenant: URL webhook n8n, template pesan per-event, on/off notifikasi.',
                'Pipa n8n+WAHA jalan global; pengaturan per-tenant (template/toggle) belum.',
                'todo', 'medium', 10, 'S', 2],
            ['Admin & Notifikasi', 'Push notifikasi on-app / WhatsApp dari admin (broadcast)',
                'Panitia kirim broadcast/notifikasi massal ke peserta via in-app + WhatsApp.',
                'Modul Notifikasi (BroadcastWa/TemplateNotifikasi) tersedia; wiring event PPDB belum lengkap.',
                'todo', 'medium', 15, 'M', 3],
            ['Ujian Online', 'Bank soal + kunci jawaban (admin authoring)',
                'Admin membuat bank soal (pilihan ganda/essay) beserta kunci jawaban.',
                'Belum ada. Saat ini hanya input nilai manual (HasilUjian).',
                'todo', 'medium', 0, 'L', 4],
            ['Ujian Online', 'Ujian online bertimer + auto-grading PG + nilai manual essay',
                'Peserta mengerjakan soal dengan timer server-side; nilai otomatis untuk PG, manual untuk essay; hasil ke HasilUjian.',
                'Belum ada (tabel ujian_sesi/jawaban_peserta belum dibuat).',
                'todo', 'medium', 0, 'L', 5],
            ['Penempatan & Identitas', 'Penempatan kelas & asrama saat daftar ulang',
                'Santri lulus + lunas → sistem menetapkan kelas & asrama saat daftar ulang.',
                'Konversi daftar-ulang → Santri sudah ada (PpdbReregistrationService); integrasi penempatan kelas/asrama di alur belum.',
                'in_progress', 'high', 30, 'M', 6],
            ['Penempatan & Identitas', 'Auto-generate ID Card santri (PDF + visual)',
                'Sistem otomatis membuat ID card (cetak PDF + kartu visual di akun santri).',
                'Belum ada. Perlu lib PDF + template kartu.',
                'todo', 'medium', 0, 'M', 7],
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

    private function seedAppSantri(): void
    {
        $project = DevProject::updateOrCreate(
            ['slug' => 'app-santri'],
            [
                'name' => 'App Santri',
                'description' => 'Aplikasi & akun SANTRI aktif (pasca-lulus): konversi calon→santri, onboarding santri lama tanpa akun, dan identitas login santri (NIS + fingerprint → smart card NFC). Pembuatan akun WALI ditangani PWA Wali; daftar-ulang→Santri ditangani PWA Peserta SPMB.',
                'status' => 'planning',
                'color' => 'warning',
                'icon' => 'heroicon-o-identification',
                'sort' => 7,
            ]
        );

        // [phase, title, detail, current_state, status, priority, progress, effort, sort]
        $tasks = [
            ['Konversi & Akun', 'Konversi LULUS → akun santri aktif (tautan ke wali)',
                'Saat daftar ulang membentuk Santri: terbitkan akun login santri (role santri) + tautkan ke akun wali via access_wali. Sisi data calon→Santri & akun wali sudah dicakup PWA Peserta F3 + PWA Wali W1.',
                'Pondasi (PpdbReregistrationService, access_wali, role wali) ada. Penerbitan akun ROLE santri + identitas loginnya belum; eksekusi diselaraskan dgn sistem akun SPMB.',
                'todo', 'high', 5, 'M', 1],
            ['Onboarding', 'Onboarding santri aktif lama tanpa akun: kredensial sementara + install app',
                'Santri lama (non-PPDB) tanpa akun/No.HP terdaftar: admin kirim akun + password sementara sekali-pakai via WA + tautan install app; paksa ganti saat login pertama. Per-santri & massal.',
                'Belum ada jalur akun untuk santri lama. Mekanisme kredensial+WA bisa reuse PpdbWhatsappNotifier.',
                'todo', 'high', 0, 'M', 2],
            ['Identitas', 'Login santri: NIS + fingerprint (mode dev) → smart card NFC',
                'Identitas login santri = NIS + verifikasi fingerprint (mode pengembangan); arah lanjut integrasi kartu pintar NFC (absensi/akses/identitas).',
                'Ide tahap awal (keputusan Hamdan). Belum dibangun; fase lanjutan.',
                'todo', 'low', 0, 'L', 3],
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
                'Config disk + disk s3 (config/filesystems) + gating tier siap. Aktivasi: composer require league/flysystem-aws-s3-v3 + set AWS_* + MEDIA_DISK=s3 + MEDIA_OBJECT_STORAGE=true.',
                'Code-ready (config, disk s3, gating, dokumentasi). Aktivasi butuh AKUN object storage (keputusan infra/biaya Anda). VPS lokal hanya muat ~8 tenant @8GB.',
                'in_progress', 'high', 50, 'L', 5],
            ['Fase 4 — Penyempurnaan', 'Thumbnail, folder, trash/restore, filter, bulk',
                'Pratinjau gambar, organisasi folder, soft-delete + restore + kosongkan sampah, filter tipe/folder, aksi massal.',
                'Selesai — semua diimplementasikan di MediaFileResource.',
                'done', 'medium', 100, 'M', 6],
            ['Fase 4 — Penyempurnaan', 'Kompresi gambar + audit log',
                'Resize/kompres gambar saat upload (GD native, no dependency) + jejak audit (Riwayat Media: upload/hapus/pulihkan/kosongkan).',
                'Selesai — MediaImageService (GD) + tabel media_audit + MediaAuditResource read-only.',
                'done', 'low', 100, 'M', 7],
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
