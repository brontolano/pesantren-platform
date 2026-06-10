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
        $this->seedTemaModulPesantren();
        $this->seedPolishUiSesi();
        $this->seedModulKeuangan();
        $this->seedStabilisasiHardening();

        $this->normalizeProjectStatus();
    }

    /**
     * Konsistensi: proyek yang SELURUH task-nya selesai (progres 100%) otomatis
     * berstatus 'done'. Menjaga papan tetap sinkron tanpa edit manual.
     */
    private function normalizeProjectStatus(): void
    {
        DevProject::with('tasks')->get()->each(function (DevProject $project): void {
            if ($project->tasks->isNotEmpty()
                && $project->progressPercent() === 100
                && $project->status !== 'done') {
                $project->update(['status' => 'done']);
            }
        });
    }

    /**
     * Program "Modul Keuangan Baitul Maal" — akuntansi double-entry + pemisahan
     * dana halal/syubhat + pajak BOS/BOP + payroll + DSCR + pelaporan L1–L7 + PWA.
     * Dibangun 2026-06-08 (eksekusi otonom). Idempotent: updateOrCreate by slug + title.
     */
    private function seedModulKeuangan(): void
    {
        $project = DevProject::updateOrCreate(
            ['slug' => 'modul-keuangan-baitul-maal'],
            [
                'name' => 'Modul Keuangan Baitul Maal',
                'description' => 'Sistem keuangan pesantren lengkap: buku besar double-entry immutable (3 dimensi + KEHALALAN halal/syubhat), pajak BOS/BOP (PMK 59/2022), pengadaan+audit GPS, wallet/tabungan, payroll poin-KPI+PPh21, segregation of funds (PSAK 112/109), pembiayaan/DSCR 1,25×, mutu/SYAMIL, pelaporan L1–L7, EMIS export, Admin Filament + PWA Vue. 41 feature test hijau + vite build hijau. Gate kepatuhan: mesin pajak & DSCR wajib review penasihat sebelum rilis.',
                'status' => 'in_progress',
                'color' => 'success',
                'icon' => 'heroicon-o-banknotes',
                'sort' => 8,
            ]
        );

        // [phase, title, detail, current_state, status, priority, progress, effort, sort]
        $tasks = [
            ['Fondasi (Ledger+Kehalalan)', 'Buku besar double-entry + dimensi Kehalalan + Dana Kebajikan',
                'Modules/Ledger: COA hierarkis, jurnal immutable+pembalik, posting idempoten, kunci periode, 3 dimensi (akun·divisi·sumber_dana) + dimensi ke-4 kehalalan (halal/syubhat/non_halal). KehalalanGuard di JurnalService (toggle per tenant), DanaKebajikanService, auto-jurnal dari pembayaran.',
                'SELESAI. 18 test hijau (balance, idempoten, periode, pembalik, BKU, isolasi, kehalalan: no-mix/no-income/blacklist masjid/whitelist).',
                'done', 'critical', 100, 'L', 1],
            ['Admin Filament', 'Panel Buku Besar + Pengaturan + Jurnal Manual',
                'AkunResource (COA), SumberDana, Divisi, Periode+Tutup Buku, Jurnal read-only+pembalik, Jurnal Manual (Repeater→postJurnal), Pages Buku Besar/BKU/Neraca, Pengaturan (toggle halal/syubhat + jenis lembaga). RBAC 6 peran keuangan.',
                'SELESAI. 19 test hijau (akses per peran).',
                'done', 'high', 100, 'L', 2],
            ['Pajak & Pengadaan', 'Mesin pajak BOS/BOP otomatis + belanja auto-jurnal',
                'Modules/Pajak (tarif_pajak configurable PMK 59/2022) + Modules/Pengadaan (belanja → MesinPajakService PPN/PPh22/23/Final, barang BOS dikecualikan → BelanjaService auto-jurnal idempoten bendahara pemungut). BelanjaResource + cetak BKK + Buku Pembantu Pajak.',
                'SELESAI. 6 test hijau (tiap aturan pajak + jurnal seimbang). GATE: review penasihat pajak sebelum rilis.',
                'done', 'critical', 100, 'L', 3],
            ['Pelaporan', 'L1–L7: Neraca, L/R, Arus Kas, LPJ BOS/BOP, RKAM, Dana Kebajikan, Dasbor',
                'LaporanService (proyeksi jurnal): neraca seimbang, labaRugi, arusKas, danaBosBop per rezim, danaKebajikan terpisah surplus, danaTerikat PSAK112/109, realisasi RKAM. Pages L6/L2/L5/L1/L4/L7 + Pusat Laporan + EMIS export CSV.',
                'SELESAI. 5 test hijau (neraca seimbang, surplus, dana kebajikan/terikat terpisah, BOS/BOP, EMIS).',
                'done', 'high', 100, 'L', 4],
            ['Payroll/Dana/Pembiayaan', 'Slip gaji poin/KPI+PPh21, DSCR 1,25×, agunan non-wakaf',
                'Modules/Payroll (PayrollService poin×tarif configurable, PPh21, jurnal idempoten, slip), Modules/Pembiayaan (DscrService ambang 1,25× configurable; aset wakaf DITOLAK agunan UU41/2004; persetujuan Pembina+e-signature UU Yayasan Ps37), Modules/Mutu (dokumen SYAMIL). Resource + L3 paket kredit + Dosir L4.',
                'SELESAI. 6 test hijau (slip neto+jurnal, DSCR 1,1×→waspada bukan reject, wakaf ditolak, Pembina e-signature).',
                'done', 'high', 100, 'L', 5],
            ['PWA Lapangan', 'API + shell Vue (Beranda/CatatBelanja+GPS/Pemasukan/Dasbor/Slip)',
                'API api/keuangan/* (belanja idempoten client_ref / pemasukan auto-jurnal / saldo / dashboard / slip-saya) + offline-sync.js (IndexedDB) + camera-gps.js. KeuanganPwaController + 5 halaman Vue/Inertia role-based.',
                'SELESAI (compile). 3 test API hijau + npm run build sukses. Runtime browser belum diuji manual (keterbatasan env).',
                'done', 'medium', 100, 'L', 6],
            ['Sisa / Polish', 'Layar PWA setor-keliling/lapor-progres/approval + notifikasi WA',
                'KEU-W03 setor tabungan keliling, KEU-G03 lapor progres foto+GPS, KEU-B05 approval Pembina di PWA — backend/helper/API sudah ada, tinggal layar Vue. KEU-X02 wiring event → WaNotificationService.',
                'BELUM. Fondasi siap; pekerjaan UI/wiring.',
                'todo', 'low', 0, 'M', 7],
            ['Ditunda (sadar)', 'QRIS otomatis & AI Keuangan',
                'KEU-X04 QRIS+rekonsiliasi (ditunda sampai >10 klien; MVP transfer manual). KEU-X05 AI (OCR nota/anomali, Fase 3).',
                'DITUNDA sesuai konstitusi (anti over-engineering).',
                'todo', 'low', 0, 'L', 8],
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

    /**
     * Program "Polish UI — Branding, Dashboard & CMS" — pekerjaan penyempurnaan
     * tampilan & integrasi storage yang sudah shipped (Jun 2026). Idempotent:
     * updateOrCreate by slug + task.title.
     */
    private function seedPolishUiSesi(): void
    {
        $project = DevProject::updateOrCreate(
            ['slug' => 'polish-ui-branding-cms'],
            [
                'name' => 'Polish UI — Branding, Dashboard & CMS',
                'description' => 'Penyempurnaan tampilan & integrasi: logo brand dinamis di semua surface, palet tema WhatsApp/TikTok, dashboard + widget jadwal sholat (GPS), perbaikan layout/CMS (artikel & galeri), serta perbaikan storage File Media (/_tenant-storage).',
                'status' => 'done',
                'color' => 'success',
                'icon' => 'heroicon-o-sparkles',
                'sort' => 7,
            ]
        );

        // [phase, title, detail, current_state, status, priority, progress, effort, sort]
        $tasks = [
            ['Branding', 'Logo brand dinamis tampil di semua surface',
                'Logo terunggah (branding_logo) tampil di panel admin, halaman login, launcher portal, dan Portal Wali — menggantikan ikon hardcoded, fallback otomatis bila belum ada logo. Helper App\\Support\\TenantBranding + resolve via /_tenant-storage.',
                'Selesai & live. Diverifikasi: keempat surface memakai logo (HTTP 200).',
                'done', 'high', 100, 'M', 1],
            ['Branding', 'Palet tema WhatsApp (terang) + TikTok (gelap), desain compact',
                'theme.css custom: light = WA (#008069 header, #F0F2F5 bg, sidebar putih); dark = TikTok (#121212/#161823/#1C1C24). Komponen lebih ringkas.',
                'Selesai & live.',
                'done', 'medium', 100, 'M', 2],
            ['Branding', 'Fix loop "Menunggu ukuran berkas" upload logo + integrasi media',
                'Disk livewire-tmp di luar tenancy.disks (atasi loop), FileUpload tak pre-fill path (atasi hang preview Docker), logo terindeks ke media_files.',
                'Selesai & live.',
                'done', 'high', 100, 'M', 3],

            ['Dashboard', 'Widget jadwal sholat (hitung lokal PHP, metode Kemenag)',
                'PrayerTimesService: port PrayTimes.org (Subuh 20deg, Isya 18deg, Ashar Syafii, ihtiyati). Menggantikan ilustrasi masjid di banner. Tanpa API eksternal.',
                'Selesai & live. Akurasi diverifikasi vs jadwal Kemenag Bandung (+-1-2 mnt).',
                'done', 'medium', 100, 'M', 4],
            ['Dashboard', 'Jadwal sholat interaktif: jam live, hitung mundur, lokasi GPS',
                'Alpine.js: jam saat ini per detik, hitung mundur ke sholat berikutnya, waktu sholat dihitung dari GPS perangkat (fallback koordinat tenant). Lokasi bisa diatur per-tenant (lintang/bujur).',
                'Selesai & live.',
                'done', 'medium', 100, 'M', 5],
            ['Dashboard', 'Layout pixel-perfect: kartu statistik, grafik, quick access',
                'Baris 5 kartu statistik, grafik area keuangan (warna WA), quick access full-width, banner sambutan WA-gradient.',
                'Selesai & live.',
                'done', 'high', 100, 'M', 6],

            ['CMS', 'Fix layout form artikel (responsif, full-width)',
                'Grid columnSpanFull + responsif (menumpuk di layar sempit, 2:1 di lg+). Atasi sidebar gepeng & badge kategori pecah per-huruf.',
                'Selesai & live.',
                'done', 'high', 100, 'S', 7],
            ['CMS', 'Redesign form galeri: layout artikel, thumbnail, SEO',
                'Layout main+sidebar responsif, thumbnail cover/foto via /_tenant-storage, kolom SEO meta_title/description (+migration tenant), resolveMediaUrl diperbaiki.',
                'Selesai & live. Migrasi SEO sudah jalan di DB central + tenant (demo, asy-syifaa).',
                'done', 'high', 100, 'M', 8],
            ['CMS', 'Foto Galeri: satu dropzone multi-upload + grid thumbnail + folder per slug',
                'Satu FileUpload multiple (panel grid) untuk unggah banyak foto; foto existing tampil grid thumbnail (caption/foto utama/urutan/hapus). File disimpan per folder cms/galleries/{slug}. Trait SyncsGalleryPhotos.',
                'Selesai & live.',
                'done', 'high', 100, 'L', 9],

            ['Media & Infra', 'Fix thumbnail & unduh File Media (/_tenant-storage)',
                'ImageColumn + aksi Unduh sebelumnya pakai Storage::url (404 di Docker). MediaFile::url()/existsOnDisk() resolve via /_tenant-storage -> preview & unduh benar.',
                'Selesai & live.',
                'done', 'medium', 100, 'S', 10],
            ['Media & Infra', 'Guard migrasi wilayah tenant (Schema::hasTable)',
                'Migrasi wilayah modul Kesantrian dijalankan di central & tenant; di-guard hasTable agar no-op di central (tabel sudah ada) -> atasi blocker deploy duplicate table.',
                'Selesai & live.',
                'done', 'medium', 100, 'S', 11],
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

    /**
     * Program "Tema & Modul TU Pesantren (UI Mockup)" — penyesuaian tampilan
     * panel admin agar sesuai mockup TU Pesantren + modul administrasi baru.
     * Idempotent: updateOrCreate by slug + task.title.
     */
    private function seedTemaModulPesantren(): void
    {
        $project = DevProject::updateOrCreate(
            ['slug' => 'tema-modul-tu-pesantren'],
            [
                'name' => 'Tema & Modul TU Pesantren (UI Mockup)',
                'description' => 'Re-tema panel admin Filament (tenant & central) agar sesuai mockup TU Pesantren: nuansa Islami emerald, sidebar hijau gelap, dashboard kaya (banner sambutan, kartu statistik, grafik, panel kanan), plus modul administrasi baru (Surat, Pengajuan, Inventaris, Arsip, Kategori Pengeluaran) dan opsi tema yang dapat dipilih tenant.',
                'status' => 'in_progress',
                'color' => 'success',
                'icon' => 'heroicon-o-paint-brush',
                'sort' => 6,
            ]
        );

        // [phase, title, detail, current_state, status, priority, progress, effort, sort]
        $tasks = [
            ['Tema (UI)', 'Fondasi tema emerald di kedua panel (sidebar gelap, brand, kartu rounded)',
                'Theme CSS custom (Tailwind v4 + Filament v5): sidebar hijau gelap, latar hangat, kartu rounded + bayangan halus, brand logo (mark masjid + wordmark adaptif). Diterapkan ke AdminPanelProvider & TenantPanelProvider.',
                'Selesai (branch feat/theme-pesantren): theme.css + viteTheme + brandLogo; warna disatukan Emerald. Build hijau → assets/theme-*.css.',
                'done', 'high', 100, 'M', 1],
            ['Tema (UI)', 'Opsi tema dapat dipilih tenant (palet/skin via setting tenant)',
                'Tenant memilih tema/palet dari pengaturan; warna panel di-resolve dinamis per tenant.',
                'Belum. Fondasi tema tunggal sudah ada; perlu mekanisme switch + penyimpanan pilihan per tenant.',
                'todo', 'medium', 0, 'M', 2],

            ['Dashboard (visual mockup)', "Banner sambutan 'Assalamu'alaikum' + ilustrasi masjid",
                'Widget full-width di atas dashboard: salam + nama user + subjudul, latar gradient emerald + ilustrasi masjid.',
                'Sedang dibangun (GreetingBanner widget).',
                'in_progress', 'high', 50, 'S', 3],
            ['Dashboard (visual mockup)', 'Kartu statistik (santri, ustadz/pengurus, kas masuk/keluar bulan ini)',
                'StatsOverviewWidget bergaya kartu: ikon chip, nilai, delta vs bulan lalu — dari data nyata (Santri, User, Pembayaran/Tagihan).',
                'Belum. Sumber data tersedia (Santri, Pembayaran, Tagihan).',
                'todo', 'high', 0, 'M', 4],
            ['Dashboard (visual mockup)', 'Grafik area Ringkasan Keuangan (pemasukan vs pengeluaran)',
                'ChartWidget area dua seri (pemasukan/pengeluaran) per hari/bulan.',
                'Belum. Data dari Pembayaran/Tagihan + kas keluar.',
                'todo', 'medium', 0, 'M', 5],
            ['Dashboard (visual mockup)', 'Grafik donat Kategori Pengeluaran',
                'ChartWidget doughnut per kategori pengeluaran.',
                'Belum — bergantung modul Kategori Pengeluaran.',
                'todo', 'medium', 0, 'S', 6],
            ['Dashboard (visual mockup)', 'Tabel Transaksi Terbaru + Quick Access shortcut',
                'Table widget transaksi terakhir + baris shortcut aksi cepat (tambah santri, kas, surat, dll).',
                'Belum. Transaksi dari Pembayaran/Tagihan.',
                'todo', 'medium', 0, 'M', 7],
            ['Dashboard (visual mockup)', 'Panel kanan: Pengumuman & Agenda Mendatang',
                'Daftar pengumuman terbaru + agenda mendatang (dari Kegiatan).',
                'Sebagian: Kegiatan ada; Pengumuman terpisah belum.',
                'todo', 'medium', 0, 'M', 8],

            ['Modul baru', 'Surat & Administrasi (surat masuk/keluar + penomoran + arsip)',
                'CRUD surat masuk/keluar, nomor surat otomatis, lampiran, status disposisi.',
                'Belum ada. Modul baru.',
                'todo', 'medium', 0, 'L', 9],
            ['Modul baru', 'Pengajuan (workflow ajukan → proses → setujui/tolak)',
                'Pengurus mengajukan (dana/kegiatan/perbaikan); alur persetujuan + status + riwayat.',
                'Belum ada. Modul baru (tampil di mockup: Pengajuan Aktif/Terbaru).',
                'todo', 'medium', 0, 'L', 10],
            ['Modul baru', 'Inventaris (aset/barang + stok + kondisi + lokasi)',
                'Pendataan aset pesantren: kategori, jumlah, kondisi, lokasi, mutasi.',
                'Belum ada. Modul baru.',
                'todo', 'medium', 0, 'L', 11],
            ['Modul baru', 'Arsip Dokumen (repositori dokumen + kategori + unduh)',
                'Penyimpanan dokumen administrasi berkategori; integrasi File Storage/MediaFile.',
                'Belum ada (bisa memanfaatkan modul File Storage yang sudah ada).',
                'todo', 'low', 0, 'M', 12],
            ['Modul baru', 'Kategori Pengeluaran (master + dipakai di kas keluar & grafik donat)',
                'Master kategori pengeluaran; dipakai transaksi kas keluar + sumber grafik donat dashboard.',
                'Belum ada sebagai master tersendiri.',
                'todo', 'medium', 0, 'S', 13],
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

    /**
     * Program "Stabilisasi, QA & Hardening Produksi" — sprint pemantapan
     * zero-error: provisioning idempotent, QA invarian I-1..I-7, modul loket
     * POS Kas tunai, Mutu approval+versioning, landing AlbaKids v2, audit error
     * log panel (perbaikan 3 sumber HTTP 500) + penanganan host non-tenant
     * (www→301 / scanner→404). Jun 2026. Idempotent: updateOrCreate by slug + title.
     */
    private function seedStabilisasiHardening(): void
    {
        $project = DevProject::updateOrCreate(
            ['slug' => 'stabilisasi-qa-hardening'],
            [
                'name' => 'Stabilisasi, QA & Hardening Produksi',
                'description' => 'Sprint pemantapan zero-error: provisioning idempotent (ModuleRegistry + tenant:reprovision), QA invarian I-1..I-7 (integritas uang, isolasi tenant, RBAC, rekonsiliasi terjadwal), modul loket POS Kas tunai, Mutu approval+versioning dokumen, landing AlbaKids v2, audit menyeluruh error log panel + perbaikan 3 sumber HTTP 500 (Textarea/ArticleDetail/nama_wali), dan penanganan host non-tenant (www→301 apex / IP & scanner→404 ringan). ~64 test baru hijau + QC produksi pasca tiap deploy.',
                'status' => 'in_progress',
                'color' => 'warning',
                'icon' => 'heroicon-o-shield-check',
                'sort' => 9,
            ]
        );

        // [phase, title, detail, current_state, status, priority, progress, effort, sort]
        $tasks = [
            ['Provisioning', 'ModuleRegistry + seedBranding/seedModules + tenant:reprovision',
                'database/seeders/ModuleRegistrySeeder (katalog 19 key, 5 core, idempotent updateOrCreate). TenantProvisioner::seedBranding (Setting JSON non-destruktif) + seedModules (aktifkan released non-core ke tenant_modules). Command tenant:reprovision backfill semua tenant lama.',
                'SELESAI. 6 test regresi provisioning end-to-end hijau.',
                'done', 'high', 100, 'M', 1],
            ['QA Invarian', 'I-1..I-7: integritas uang, isolasi, RBAC, rekonsiliasi',
                'I-1 integritas uang (zero orphan/ganda), I-2 isolasi tenant lintas-modul, I-3 guard state-machine status, I-4 idempotensi operasi uang, I-5 segregasi tugas RBAC per peran, I-7 rekonsiliasi harian terjadwal 01:30 + alert. EmisExportService contract-validation. I-6 (rekonsiliasi gateway) menunggu Xendit.',
                'SELESAI 6/7 (I-6 terblokir Xendit).',
                'done', 'high', 100, 'L', 2],
            ['Command Operasional', 'tenant:migrate-all + keuangan:rekonsiliasi-harian',
                'Command tenant:migrate-all (migrasi semua DB fisik tenant, idempotent) + keuangan:rekonsiliasi-harian (terjadwal). Alur pasca-deploy: migrate-all lalu reprovision.',
                'SELESAI. Terpakai di alur deploy produksi.',
                'done', 'medium', 100, 'S', 3],
            ['POS Kas (loket tunai)', 'Sesi kasir + rekonsiliasi + kategori configurable + posting Ledger',
                'Modules/Keuangan: pos_sesi/pos_kategori/pos_transaksi + 3 model; PosKasService (buka/tutup sesi, rekonsiliasi selisih, catat() routing → Tabungan/Pembayaran/Jurnal, idempoten ref). PosKategoriResource (Filament, gated permission operate_pos). 18 test hijau.',
                'BACKEND SELESAI & live (PR #4). UI kasir transaksional belum di-ship (spec di BLUEPRINT-MODUL-POS-KAS).',
                'in_progress', 'medium', 80, 'L', 4],
            ['Mutu approval', 'State-machine approval + versioning dokumen mutu',
                'MutuService (draft→diajukan→disetujui/ditolak), migrasi additive dokumen_mutu_versi + kolom, permission approve_mutu (segregasi di kepala_keuangan), aksi panel DokumenMutuResource. 9 test hijau.',
                'BACKEND SELESAI & live (PR #4). QC visual UI di VPS.',
                'in_progress', 'low', 80, 'M', 5],
            ['AlbaKids Landing v2', 'Komponen Vue on-brand + route /v2 + kontrak form',
                'resources/js/Pages/CMS/AlbaKidsLandingV2.vue (desain playful via judge-panel) + route GET /v2 (abort 404 non-AlbaKids) + reuse kontrak POST /daftar + notif admin+ayah+ibu+wali. PR #5.',
                'SELESAI & live. albakids/v2=200, demo/v2=404 (gated benar).',
                'done', 'medium', 100, 'M', 6],
            ['Audit Error Log Panel', 'Periksa menyeluruh + perbaiki 3 sumber HTTP 500',
                'Analisis log produksi 06-07..06-09. Fix: PengaturanBranding import Textarea; ArticleDetailController hapus param route $tenant spurious; LandingRegistration guard field nullable (nama_wali) → cegah 500 saat form AlbaKids submit. Scan 263 file panel + audit kelas 85 komponen = 0 sisa. PR #6.',
                'SELESAI & live. /artikel/{slug} dummy → 404 (bukan 500); 0 error aplikasi pasca-deploy.',
                'done', 'critical', 100, 'M', 7],
            ['Hardening Host', 'www→301 apex + host scanner/IP→404 (hapus noise 500)',
                'InitializeTenancyForWebRequests redirect www.<apex> 301 ke apex (pertahankan path+query) + bootstrap/app.php withExceptions dontReport+render 404 untuk TenantCouldNotBeIdentifiedOnDomain (IP mentah/subdomain scanner). 4 feature test (HostResolutionTest) hijau. PR #7.',
                'SELESAI & live. www→301, IP→404, 0 error TenantCouldNot ter-log pasca-deploy.',
                'done', 'low', 100, 'S', 8],
            ['Hardening Idempotensi', 'Fix double-post Jurnal Manual + guard JurnalService',
                'Bug PRE-EXISTING: JurnalManual posting tanpa ref → idempotency_key NULL → unique(tenant_id,idempotency_key) tak men-dedup NULL (di SQL NULL!=NULL) → submit ulang (double-click/refresh/retry) MENGGANDAKAN uang. Fix: gerbang akhir idempotensi di JurnalService (tolak posting valid-tanpa-kunci kecuali allow_duplicate eksplisit; math/balance teruji TAK diubah) + token UUID per-render form JurnalManual (dedup double-submit, regen setelah sukses, +tombol disabled saat proses) + DanaKebajikan optional refId. JurnalManualIdempotencyTest 6/6, regresi uang/ledger 175 hijau, adversarial-verify GO, smoke prod OK.',
                'SELESAI & live (PR #49). Klik-ganda/refresh/retry tak menggandakan jurnal lagi.',
                'done', 'critical', 100, 'M', 9],
            ['Amankan Aset AlbaKids', 'Commit landing.blade.php + galeri AlbaKids ke git',
                'Kustomisasi AlbaKids (resources/views/albakids/landing.blade.php + public/albakids/g1-6.jpg) hidup di VPS lewat baked image tapi BELUM ada di origin/main → risiko hilang bila VPS di-rebuild (git reset --hard origin/main). Diverifikasi checksum lokal IDENTIK dengan live, lalu di-commit ke main agar deploy berikutnya membawanya. Branch snapshot redundan (vps-snapshot, fix/albakids-landing-assets) dibersihkan.',
                'SELESAI & live. AlbaKids kini aman di git; rebuild tak lagi menghilangkan kustomisasi.',
                'done', 'high', 100, 'S', 10],
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
