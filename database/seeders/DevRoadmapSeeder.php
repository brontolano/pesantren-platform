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
        $this->seedAdaptasiErpnext();
        $this->seedMigrasiAsySyifaa();
        $this->seedPwaGuru();
        $this->seedModulAkademikPesantren();
        $this->seedModulSdmHrd();
        $this->seedOperasionalLapangan();
        $this->seedMutuEkosistem();
        $this->seedPenyempurnaanLive();

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
     * Program "Penyempurnaan Live — Kesiapan User Nyata". Hasil audit menyeluruh
     * 17 modul + UX + pengaturan + dokumentasi (2026-06-23, via workflow 21 agen):
     * rata-rata readiness 67/100, 72 gap fungsional, 9 blocker. Goal owner: semua
     * modul lengkap & tak membingungkan untuk tenant pesantren NYATA. Idempotent.
     */
    private function seedPenyempurnaanLive(): void
    {
        $project = DevProject::updateOrCreate(
            ['slug' => 'penyempurnaan-live-kesiapan-user'],
            [
                'name' => 'Penyempurnaan Live — Kesiapan User Nyata',
                'description' => 'Roadmap hasil audit kesiapan-produksi 17 modul + UX + pengaturan + dokumentasi (2026-06-23). Rata-rata readiness 67/100, 72 gap fungsional, 9 blocker. Prioritas teratas = blocker "modul mati out-of-the-box" (seeder tak ter-wire → dropdown kosong → buntu hari pertama). Tema: A=kelengkapan modul, B=UX & kejelasan, C=pengaturan sistem, D=dokumentasi.',
                'status' => 'in_progress',
                'color' => 'warning',
                'icon' => 'heroicon-o-sparkles',
                'sort' => 20,
            ]
        );

        // [phase, title, detail, priority, effort]
        $items = [
            // A. Kelengkapan modul
            ['A. Kelengkapan modul', 'Perbaiki bug channel Notifikasi whatsapp→wa + wire seeder', 'Ubah 23 entri TemplateNotifikasiSeeder dari channel "whatsapp" ke "wa" + wire ke TenantProvisioner; tanpa ini semua template tak muncul & dropdown Broadcast kosong (modul mati out-of-the-box).', 'critical', 'S'],
            ['A. Kelengkapan modul', 'Wire seeder tarif Pajak ke TenantProvisioner', 'Daftarkan PajakSeeder + TerPph21Seeder ke pipeline provisioning (idempoten, --force, jalankan via tenant:reprovision); tanpa ini MesinPajak selalu 0 & Tarif Pajak kosong-buntu (canCreate=false).', 'critical', 'M'],
            ['A. Kelengkapan modul', 'Seed kategori POS untuk tenant baru', 'Tambah PosKategoriSeeder ke TenantProvisioner + tenant:reprovision + aksi "Pulihkan kategori bawaan"; agar Loket Kas/Kasir Kantin tak buntu hari pertama (dropdown kategori kosong).', 'critical', 'S'],
            ['A. Kelengkapan modul', 'Satukan kosakata status izin santri (panel↔PWA)', 'Pilih satu nilai ("diajukan" atau "pending") lintas migration, IzinSantriResource, KegiatanService, IzinSantriService, PwaGuruIzinController; agar izin panel admin masuk antrean persetujuan PWA & sebaliknya. Blocker fungsional.', 'critical', 'M'],
            ['A. Kelengkapan modul', 'Buat UI panel admin Kunjungan UKS', 'Filament Resource/Page yang membungkus KesehatanService (daftar istirahat, catat kunjungan, tutup, rujukan); kini operasi UKS tersering hanya bisa via API PWA Guru — petugas UKS di panel tak bisa kerja.', 'high', 'M'],
            ['A. Kelengkapan modul', 'Perbaiki RBAC modul Mutu', 'Beri manage_mutu & approve_mutu (terpisah) ke peran relevan atau tambah peran penjamin_mutu; agar 5 resource SPMI + Dashboard muncul saat onboarding, bukan tersembunyi.', 'high', 'M'],
            ['A. Kelengkapan modul', 'Putuskan pintu rilis AI: development→released + seed GATE-3', 'Promosikan status registry "ai" + aktifkan tenant_modules DAN tuntaskan seed GATE-3 (whitelist_kitab/blacklist_topik hasil tinjauan ustadz); dua prasyarat mutlak agar AI dipakai tenant nyata.', 'high', 'M'],
            ['A. Kelengkapan modul', 'Samakan kosakata STATUS hafalan jadi satu sumber', 'Enum/config tunggal untuk direncanakan/proses/selesai vs "berjalan" agar form, importer, ringkasan & Portal Wali konsisten; hilangkan bug data impor tak terhitung & gagal re-import.', 'high', 'M'],
            ['A. Kelengkapan modul', 'Satukan bobot nilai akademik (KomponenNilai)', 'Buat Resource/UI KomponenNilai (komponen + bobot per pelajaran) & samakan/hapus default ganda akademik_grade_weights (40/30/30 vs 30/30/40) agar perubahan bobot staf benar-benar berefek.', 'high', 'M'],
            ['A. Kelengkapan modul', 'Tambah UI presensi kegiatan', 'RelationManager "Kehadiran" pada KegiatanResource atau halaman presensi membungkus catatPresensi() agar staf bisa catat kehadiran lewat panel, bukan hanya API.', 'high', 'M'],
            ['A. Kelengkapan modul', 'Wire Tagihan Per Kelas ke UI Filament', 'Hubungkan TagihanService::buatTagihanPerKelas() ke aksi/halaman massal (atau koreksi doc ke Import Excel) agar bendahara bisa menagih 1 kelas tanpa Excel.', 'medium', 'M'],
            ['A. Kelengkapan modul', 'Sediakan UI panel laporan Pajak inti', 'UI untuk SPT Masa PPh21, e-Bupot (CSV Coretax) + setelan NPWP/penandatangan, SPT Badan & Sisa Lebih Yayasan; kini hanya ada di PWA/Service.', 'medium', 'L'],
            ['A. Kelengkapan modul', 'Pengelolaan anggota Halaqah + Select di form Hafalan', 'RelationManager santri di HalaqahResource (tambah/keluarkan anggota) + Select halaqah_id pada Create Hafalan agar tak harus selalu Import Excel.', 'medium', 'M'],
            ['A. Kelengkapan modul', 'Buat Resource panel Sima\'an', 'Resource Filament untuk lihat/koreksi data Sima\'an (kini hanya ditulis dari PWA Guru) agar bisa diaudit/diperbaiki staf di panel.', 'medium', 'S'],
            ['A. Kelengkapan modul', 'Lengkapi siklus status Ta\'zir di panel web', 'Tambah aksi "Sanksi Dijalankan" & "Selesai" di PelanggaranSantriResource (atau dokumentasikan transisi via PWA) agar staf panel tak mentok di "diverifikasi".', 'medium', 'S'],
            ['A. Kelengkapan modul', 'Tombol Buat + UI Log Notifikasi', 'Tambah getHeaderActions(CreateAction) di ListTemplateNotifikasi & ListBroadcastWa + Resource Log Notifikasi agar staf bisa membuat data & melihat status terkirim/gagal.', 'medium', 'M'],
            ['A. Kelengkapan modul', 'Perbaiki gateway WA "simulated" lapor sukses palsu', 'WaGatewayService kembalikan "simulated" yang dicatat "sent" tanpa konfigurasi WAHA; jadikan status jujur agar staf tak mengira pesan terkirim.', 'medium', 'S'],
            ['A. Kelengkapan modul', 'Samakan kosakata status broadcast', 'Selaraskan selesai/sebagian (NotifDispatcher) dengan sent/partial_failed/processing (form/service/badge) agar broadcast event-driven tampil status dikenali.', 'medium', 'S'],
            ['A. Kelengkapan modul', 'Edit PO draft harus bisa ubah item', 'Repeater items diset visibleOn(create) sehingga Edit PO draft kehilangan daftar item; izinkan edit + recompute total.', 'medium', 'S'],
            ['A. Kelengkapan modul', 'Guard ramah COA belum-seed sebelum aksi uang', 'Ganti RuntimeException teknis di BelanjaService/DepresiasiService/AsetMovementService dengan pesan "Aktifkan & setup modul Akuntansi dulu" + cek pra-aksi.', 'medium', 'S'],
            ['A. Kelengkapan modul', 'Putuskan nasib fitur audit GPS Pengadaan', 'LaporanJasaDanBarang (foto+GPS) diiklankan di module.json tapi tak ada UI; bangun UI atau hapus model+migration & koreksi deskripsi.', 'medium', 'M'],
            ['A. Kelengkapan modul', 'Deteksi bentrok jadwal guru/ruangan', 'JadwalPelajaranService hanya cek jam_selesai>jam_mulai; tambah cek tumpang-tindih guru/ruangan/kelas (atau koreksi doc index.html:94 yang menjanjikannya).', 'medium', 'M'],
            ['A. Kelengkapan modul', 'Bersihkan scaffold route rusak (cms/ppdbs/asramas/kegiatans/kesehatans/tahfidzs)', 'Hapus Route::resource + Controller boilerplate + view stub yang return 500 ke user login (UI asli sudah di Filament).', 'medium', 'S'],
            ['A. Kelengkapan modul', 'GRN penerimaan parsial di UI', 'Action "terima" selalu full receipt; tambah form qty diterima per item agar 3-way match parsial bisa dipakai dari panel.', 'low', 'S'],
            ['A. Kelengkapan modul', 'DSCR/EBITDA: tarik otomatis atau panduan input', 'Pembiayaan/DSCR menuntut input EBITDA manual tanpa panduan; beri penjelasan atau tarik dari Ledger agar DSCR tak keliru/0.', 'low', 'M'],
            ['A. Kelengkapan modul', 'Tampilkan jejak mutasi stok obat & okupansi asrama', 'ObatMutasi (audit) & occupancySummary asrama sudah dihitung di service tapi tak punya UI/widget; tampilkan.', 'low', 'S'],

            // B. UX & kejelasan
            ['B. UX & kejelasan', 'Konsolidasi 21 grup nav jadi 6-8 "aplikasi"', 'Gabung via penamaan+urutan: Kesantrian+Kepengasuhan+Kedisiplinan+UKS+Alumni→"Santri & Pengasuhan"; Keuangan+Kas+Kantin+Akuntansi+Pajak→"Keuangan"; Akademik+Tahfidz→"Akademik". Friksi terbesar staf awam.', 'high', 'M'],
            ['B. UX & kejelasan', 'Pindahkan menu Belanja dari grup Pajak', 'BelanjaResource.navigationGroup="Pajak" menyesatkan; pindah ke grup pembelian/keuangan bersama Supplier & PurchaseOrder.', 'high', 'S'],
            ['B. UX & kejelasan', 'Perbaiki prioritas megamenu topNav', 'Ganti $prioritas [Kesantrian,Keuangan] jadi grup jalur-adopsi (Santri & Pengasuhan, PPDB); kelompokkan "Semua Menu" per aplikasi, bukan 21 grup mentah.', 'high', 'S'],
            ['B. UX & kejelasan', 'Indonesiakan label Website Builder', 'Eyebrow→"Teks kecil di atas judul", Primary CTA Label/Link→"Teks/Tautan Tombol", sembunyikan slug section mentah, nav "Website Builder"→"Pengaturan Tampilan Website".', 'high', 'S'],
            ['B. UX & kejelasan', 'Konsolidasi 4 tempat edit identitas/profil CMS + fix key profil/profile', 'Satukan sumber kebenaran (Website Builder, Identitas & Kontak, Profil, Pengaturan Website) + perbaiki key "profil" vs "profile" agar editor tak jatuh ke JSON mentah.', 'high', 'M'],
            ['B. UX & kejelasan', 'Indonesiakan label navigasi Kepegawaian', 'Payroll Asatidz→"Slip Gaji", Pegawai (HR)→"Data Pegawai", Cuti/Leave→"Cuti", Pratinjau Bisyaroh→istilah umum agar staf gaptek paham.', 'high', 'S'],
            ['B. UX & kejelasan', 'Label awam + tooltip jargon Arab/akuntansi', 'Khidmah→"Pengabdian (Khidmah)", Munaqasyah→"Ujian Hafalan", Alokasi Biaya→"Pembagian Biaya", SKM→"Survei Kepuasan"; sembunyikan Jurnal/Kurs/Kehalalan di Mode Lanjutan.', 'medium', 'M'],
            ['B. UX & kejelasan', 'Atasi modul tersembunyi di Mode Sederhana', 'Munculkan/onboarding menu inti yang kini gated advanced: Ledger, Kesehatan (Riwayat/Konseling), Akademik (Periode/Pelajaran/Jadwal), Pajak; + banner saat prasyarat kosong (dead-end setup).', 'medium', 'M'],
            ['B. UX & kejelasan', 'Perbaiki label Page Buku Besar Ledger', 'BukuBesar.php menampilkan judul "Akuntansi dan Pajak" yang bentrok dengan nama grup nav & doc yang menyuruh klik "Buku Besar".', 'medium', 'S'],
            ['B. UX & kejelasan', 'Empty-state ramah pada resource jalur-adopsi', 'Pola heading Indonesia + deskripsi + ikon + tombol Impor/Tambah pada ~88 resource polos; prioritaskan Pembayaran, Kelas, Penempatan, Pelajaran, Jadwal, Kegiatan, Pegawai, Tabungan.', 'medium', 'M'],
            ['B. UX & kejelasan', 'Global search Tagihan & Pembayaran', 'Tambah getGloballySearchableAttributes + getGlobalSearchResultDetails di TagihanResource & PembayaranResource (cari per nama/NIS santri).', 'medium', 'S'],
            ['B. UX & kejelasan', 'Ganti TextInput ID Tagihan/Transaksi dengan picker', 'LoketKas & KasirKantin minta ketik nomor ID internal manual; ganti dengan picker dapat dicari (nama santri/tagihan) agar kasir awam tak salah.', 'medium', 'S'],
            ['B. UX & kejelasan', 'Perjelas identitas modul Pembiayaan', 'Satukan nama/deskripsi Pusat Modul ("Aset & inventaris" vs nama "Pembiayaan") & grup nav; pisahkan DSCR bank dari Aset/Inventaris + Sarpras/Piket.', 'medium', 'M'],
            ['B. UX & kejelasan', 'Bungkus error service jadi notifikasi ramah (Mutu)', 'Aksi mulai_tindakan/ajukan_verifikasi tak membungkus InvalidArgumentException; tampilkan notifikasi ramah, bukan HTTP 500.', 'low', 'S'],
            ['B. UX & kejelasan', 'Empty-state instruktif "Mutu per Kelas"', 'Sebelum pilih kelas heatmap disembunyikan tanpa instruksi; tampilkan dropdown + ajakan agar tak terkesan rusak.', 'low', 'S'],
            ['B. UX & kejelasan', 'Selaraskan label status Periode Akademik', 'Form pakai Aktif/Nonaktif sedang doc Aktif/Selesai; samakan istilah agar staf tak ragu saat tutup semester.', 'low', 'S'],
            ['B. UX & kejelasan', 'Cetak QR Area Piket dari panel', 'QR Area Piket di-generate tapi tak ada tombol cetak; staf harus salin manual & buat stiker sendiri.', 'low', 'S'],

            // C. Pengaturan sistem
            ['C. Pengaturan sistem', 'Tambah halaman indeks Pusat Pengaturan tunggal', 'Setting tersebar di 6 grup nav; buat indeks "Semua Pengaturan" agar staf awam menemukan toggle WA/pemisahan dana/dll.', 'high', 'M'],
            ['C. Pengaturan sistem', 'Pisahkan setting PPDB dari PengaturanBranding', 'ppdb_form_fields & blok admission (tahun ajaran, WA admin, bank, nominal, pesan) ada di halaman bernama Branding; buat halaman Pengaturan PPDB tersendiri.', 'high', 'M'],
            ['C. Pengaturan sistem', 'KebijakanPesantren sumber tunggal parameter keuangan', 'dscr ambang, tarif poin, pph21, jenis lembaga, pemisahan kehalalan terduplikasi di PengaturanKeuangan/KebijakanPesantren/SetupPesantren; konsolidasi & cek value-wrapper vs mentah.', 'high', 'M'],
            ['C. Pengaturan sistem', 'Seed Periode Akuntansi pertama saat provisioning', 'Periode Akuntansi wajib untuk Ledger tapi tak ter-seed default; sediakan otomatis agar posting jurnal tak mentok.', 'medium', 'S'],
            ['C. Pengaturan sistem', 'Setelan kode lembaga & format nomor surat keluar', 'SuratService hardcode pola (default PES) tak konsisten dengan format ijazah konfigurabel; tambah ke KebijakanRegistry.', 'medium', 'S'],
            ['C. Pengaturan sistem', 'Setelan identitas pemotong pajak per-tenant', 'EBupotService terima $npwpPemotong yang tak pernah diisi UI (CSV Coretax tak lengkap); tambah setelan NPWP/nama penandatangan/kode KPP.', 'medium', 'S'],
            ['C. Pengaturan sistem', 'Master gelombang PPDB terstruktur', 'Gelombang diketik bebas tanpa tanggal buka-tutup/kuota/biaya; sediakan resource gelombang di halaman Pengaturan PPDB.', 'medium', 'M'],
            ['C. Pengaturan sistem', 'admission_academic_year ambil default dari PeriodeAkademik', 'Tahun ajaran PPDB disimpan teks bebas terpisah dari PeriodeAkademik aktif → bisa berseberangan; ambil dari periode aktif.', 'medium', 'S'],
            ['C. Pengaturan sistem', 'Seragamkan visibilitas halaman setting di Mode Sederhana', 'PengaturanKeuangan & Notifikasi tersembunyi di Sederhana (toggle WA hilang) sementara Tampilan/AppSantri tampil; samakan kebijakan.', 'medium', 'S'],
            ['C. Pengaturan sistem', 'Periode biaya asrama terkontrol (bukan teks bebas)', 'Periode biaya diketik bebas ("2026-07" vs "Juli 2026"); pakai pilihan terkontrol/default tahun ajaran aktif + auto-prefill nominal dari kamar/tipe.', 'medium', 'S'],
            ['C. Pengaturan sistem', 'Pastikan kuota/H-min izin & template SK terekspos di Kebijakan', 'kesantrian.approval_h_minimum, maks_izin_bulan, payroll.template_sk & payroll_admin_whatsapp dibaca dari Setting tapi perlu dipastikan ada UI di halaman Kebijakan.', 'medium', 'S'],
            ['C. Pengaturan sistem', 'Toggle mode_uji WA AI di panel (bukan env)', 'Chatbot WA default mode_uji=true (balas template statis), hanya bisa dimatikan via env; tambah toggle di Pengaturan AI.', 'medium', 'S'],
            ['C. Pengaturan sistem', 'Provision budget AI per tenant + self-check infra di panel', 'AiTenantBudget harus diprovision; ekspos status ringkas (Ollama up, model ter-pull, queue, budget) di panel, bukan hanya CLI ai:health.', 'medium', 'M'],
            ['C. Pengaturan sistem', 'Pindahkan editor Kebijakan Privasi ke setting legal', 'Teks privasi global (PPDB consent & situs publik) kini di grup Kesantrian (App Santri); pindah ke setting legal atau tautkan dari KebijakanPesantren.', 'low', 'S'],
            ['C. Pengaturan sistem', 'Tampilkan status SMTP untuk toggle Email', 'Hanya toggle notif_email_enabled tanpa visibilitas SMTP global; tampilkan status/helper agar ON tak gagal diam-diam.', 'low', 'S'],
            ['C. Pengaturan sistem', 'Ganti favicon TextInput URL ke FileUpload', 'Logo pakai FileUpload tervalidasi tapi favicon hanya TextInput URL rawan 404; ganti ke FileUpload ico/png.', 'low', 'S'],
            ['C. Pengaturan sistem', 'Dorong pengisian Skema Biaya saat setup', 'SyahriyyahImportService pakai fallback hardcode 700rb bila Skema Biaya kosong; dorong tenant mengisi saat setup.', 'low', 'S'],

            // D. Dokumentasi
            ['D. Dokumentasi', 'Tulis Manual SANTRI (pengguna app sendiri)', 'App Santri sudah LIVE (login NIS+PIN/QR/fingerprint) tapi nol panduan untuk santri sebagai end-user: cara buka app, login, lihat tagihan/tabungan/jadwal/rapor. Populasi terbesar & paling gaptek.', 'high', 'M'],
            ['D. Dokumentasi', 'Tetapkan urutan prioritas pengisian dokumentasi', 'Fokus MENAMBAH halaman baru (akurasi existing baik): (1) Manual Santri, (2) Broadcast WA, (3) Pembiayaan/Baitul Maal & Pengadaan, (4) pisah Kesehatan/UKS & Kegiatan.', 'high', 'S'],
            ['D. Dokumentasi', 'Koreksi doc bendahara "Tagihan Per Kelas"', 'Bagian 3 mengajari tombol massal "Per Kelas" yang tak ada di UI; arahkan ke Import Excel yang nyata (atau perbarui setelah fitur di-wire).', 'high', 'S'],
            ['D. Dokumentasi', 'Tulis manual Notifikasi / Broadcast WA', 'Belum ada how-to broadcast pengumuman massal (template, target, placeholder {{...}}); operator dipakai harian tanpa rujukan.', 'medium', 'S'],
            ['D. Dokumentasi', 'Tulis manual PPDB untuk panitia', 'Alur verifikasi berkas→ujian→luluskan/cadangan/tolak→daftar ulang + cara atur tahun ajaran/bank/form fields; modul paling bercabang tanpa manual.', 'medium', 'M'],
            ['D. Dokumentasi', 'Tulis manual modul Mutu untuk admin', 'Cara isi Sasaran/KPI, kelola Temuan (terbuka→tindakan→verifikasi→tutup), Inspeksi, SKM, baca Dashboard KPI + glosarium (SPMI, Non-Conformance, Severity, SKM).', 'medium', 'M'],
            ['D. Dokumentasi', 'Tulis manual Kesehatan/UKS', 'Petugas UKS: catat pemeriksaan, kelola stok obat + import, alur kunjungan/rujukan PWA, konseling. Kini nol manual padahal 2 fitur inti tersembunyi.', 'medium', 'M'],
            ['D. Dokumentasi', 'Tulis manual Pengadaan + glosarium', 'Alur Supplier→PO→Terima→Belanja→Setujui & Cairkan→Cetak BKK + glosarium (DPP, PKP, BKK, GRN, Syubhat, Akun Beban/Kas).', 'medium', 'M'],
            ['D. Dokumentasi', 'Tulis manual Pembiayaan/Aset/Baitul Maal', 'Aset (input, penyusutan, pelepasan), Pembiayaan/DSCR, Baitul Maal (zakat/infak/beasiswa, PSAK 109); fitur uang/aset paling berisiko tanpa panduan.', 'medium', 'M'],
            ['D. Dokumentasi', 'Perbaiki dokumentasi Pajak agar akurat', 'Pisahkan "Kalkulator Pajak (belanja PPN/PPh)" vs "Kalkulator PPh21 pegawai (Kepegawaian)", luruskan deskripsi "Buku Pembantu Pajak".', 'medium', 'S'],
            ['D. Dokumentasi', 'Selaraskan dokumentasi Payroll dengan UI aktual', 'Koreksi nama tombol/status (Finalisasi & Jurnal bukan Terbitkan), jelaskan generate massal via Pratinjau Bisyaroh & Generate THR, cocokkan field Tambah Pegawai.', 'medium', 'S'],
            ['D. Dokumentasi', 'Selaraskan label doc Kegiatan dengan menu nyata', 'Manual sebut grup "Kegiatan & Perizinan"/"Asrama" padahal menu di grup "Kepengasuhan" label "Izin Santri"; tambah walkthrough Kegiatan/Kunjungan Wali.', 'medium', 'S'],
            ['D. Dokumentasi', 'Tandai menu mode "Lanjutan" di manual Akademik', 'Langkah doc mengarah ke menu (Periode/Pelajaran/Jadwal) yang hanya muncul di Mode Lanjutan; tandai agar staf awam tak bingung.', 'medium', 'S'],
            ['D. Dokumentasi', 'Dokumentasikan 3 fitur tersembunyi Asrama', 'Penempatan Otomatis (per-gender, idempoten), Import Asrama Excel (dry-run), field Gender Blok; user nyata tak tahu fitur ini ada.', 'medium', 'S'],
            ['D. Dokumentasi', 'Tulis panduan ta\'zir poin & Stasiun Pendaftaran', 'Disiplin/Ta\'zir berbasis poin (ambang SP, verifikasi anti-bully, poin prestasi) & Stasiun Pendaftaran loket (foto/TTD/NFC/PIN/cetak kartu) — fitur tercanggih paling tipis dokumentasinya.', 'medium', 'M'],
            ['D. Dokumentasi', 'Beri banner "segera hadir" di manual AI Khadim', 'ai-khadim/index.html lengkap tapi /tanya-ustadz 404 (gated GATE-3); beri banner agar staf tak salah harap modul langsung bisa dipakai.', 'low', 'S'],
            ['D. Dokumentasi', 'Catat di doc modul yang masih digerbang/butuh prasyarat', 'Tambah catatan kesiapan (Mode Lanjutan, GATE-3, infra) di manual agar staf tak salah harap fitur langsung jalan.', 'low', 'S'],
        ];

        $sort = 0;
        foreach ($items as [$phase, $title, $detail, $priority, $effort]) {
            $sort++;
            DevTask::updateOrCreate(
                ['dev_project_id' => $project->id, 'title' => $title],
                [
                    'phase' => $phase,
                    'detail' => $detail,
                    'current_state' => 'BELUM — temuan audit kesiapan 2026-06-23.',
                    'status' => 'todo',
                    'priority' => $priority,
                    'progress' => 0,
                    'effort' => $effort,
                    'sort' => $sort,
                ]
            );
        }

        // Status task yang SUDAH dikerjakan (diperbarui tiap PR fix selesai).
        $selesai = [
            'Perbaiki bug channel Notifikasi whatsapp→wa + wire seeder' => 'SELESAI 2026-06-24: 23 nilai seeder channel→wa + migrasi normalisasi tenant lama + TemplateNotifikasiSeeder ter-wire ke TenantProvisioner.',
            'Wire seeder tarif Pajak ke TenantProvisioner' => 'SELESAI 2026-06-24: PajakSeeder + TerPph21Seeder ter-wire ke TenantProvisioner (idempoten, --force).',
            'Seed kategori POS untuk tenant baru' => 'SELESAI 2026-06-24: PosKategoriSeeder ter-wire ke TenantProvisioner.',
        ];
        foreach ($selesai as $judul => $state) {
            DevTask::where('dev_project_id', $project->id)
                ->where('title', $judul)
                ->update(['status' => 'done', 'progress' => 100, 'current_state' => $state]);
        }
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
                'SELESAI & live: app/Filament/Widgets/GreetingBanner.php + blade greeting-banner terpasang di dashboard tenant.',
                'done', 'high', 100, 'S', 3],
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
                'SELESAI & live: CalonSantriResource (CRUD + monitoring status) + CalonSantriDokumenResource + PpdbAdmissionDecisionService (keputusan lulus/cadangan/ditolak) + aksi re-issue OTP/koreksi No.WA; diperkuat G3-20 (CRM funnel).',
                'done', 'high', 100, 'M', 1],
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
                'SELESAI & live: PpdbReregistrationService menetapkan kelas_id + penempatan asrama via AsramaService (pagar kapasitas + isolasi tenant + tutup penempatan lama); opsi penempatan otomatis massal via PenempatanOtomatisService (G3-22).',
                'done', 'high', 100, 'M', 6],
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
                'SELESAI & LIVE: GET /api/v1/posts(+slug), /api/public/content/gallery|profile|settings (publik) + /api/v1/profil|statistik|jadwal-ppdb (X-Api-Key). Smoke prod 2026-06-10: posts/gallery 200, profil tanpa key 401.',
                'done', 'high', 100, 'L', 5],
            ['Fase C — API Publik (headless)', 'API submit PPDB dari form website eksternal + anti-spam',
                'Endpoint terima pendaftaran dari form di situs tenant; proteksi spam/captcha.',
                'SELESAI & LIVE: POST /api/v1/spmb/register + honeypot field website + throttle:ppdb-submit (5/menit per tenant+IP). Smoke prod: honeypot terisi → 422 (data tak dibuat).',
                'done', 'medium', 100, 'M', 6],
            ['Fase C — API Publik (headless)', "Token/API key per tenant + halaman 'Integrasi & API' di dashboard tenant",
                'Tenant generate API key sendiri; kelola di dashboard tenant.',
                'SELESAI & LIVE: TenantApiKey (hash SHA-256, prefix psa_, revoke) + middleware AuthenticateTenantApiKey (timing-safe, kunci wajib milik tenant domain) + halaman Filament IntegrasiApi.',
                'done', 'high', 100, 'M', 7],
            ['Fase C — API Publik (headless)', 'CORS per domain tenant + rate-limit (jaga isolasi antar pesantren)',
                'CORS whitelist per domain tenant (bukan wildcard) + rate-limit endpoint publik.',
                'SELESAI & LIVE: config/cors.php (origin eksplisit + pola subdomain apex, tolak lookalike/suffix-spoof) + RateLimiter public-api 60/mnt per key & ppdb-submit 5/mnt per tenant+IP. Isolasi kunci antar tenant ditegakkan middleware.',
                'done', 'high', 100, 'S', 8],

            // Fase D — Distribusi & Uji Coba
            ['Fase D — Distribusi & Uji Coba', 'Dokumentasi integrasi untuk developer pesantren',
                'Panduan integrasi (3 pola) + contoh kode untuk tim website pesantren.',
                'SELESAI: docs/INTEGRASI-API.md (kontrak lengkap: auth, rate limit, CORS, semua endpoint + contoh curl/fetch) + SPEC-API-WEBSITE-ASY-SYIFAA.md + PLAN-INTEGRASI-WEBSITE.md.',
                'done', 'medium', 100, 'M', 9],
            ['Fase D — Distribusi & Uji Coba', 'Plugin WordPress resmi (embed PPDB + statistik via API)',
                'Plugin siap-pasang untuk pesantren yang pakai WordPress.',
                'Belum ada.',
                'todo', 'low', 0, 'L', 10],
            ['Fase D — Distribusi & Uji Coba', 'Uji coba dengan client pertama: Asy-Syifaa',
                'Situs PHP custom asy-syifaa.com (thin-client) diintegrasikan ke API platform via pola C.',
                'Tenant asy-syifaa LIVE + seluruh API siap & teruji di prod (read, submit, auth, CORS, rate-limit). Sisa: repoint base URL website asy-syifaa ke API platform + uji end-to-end halaman dinamis (butuh koordinasi/akses website client).',
                'in_progress', 'high', 70, 'M', 11],

            // Kontrak konkret dari studi website asy-syifaa (lihat docs/SPEC-API-WEBSITE-ASY-SYIFAA.md)
            ['Fase C — API Publik (headless)', 'API #1: POST /api/v1/spmb/register (kontrak website asy-syifaa)',
                'Endpoint pendaftaran sesuai kontrak; balas eticket_no + registration_number + username (= PPDB v2 Fase 1).',
                'SELESAI & LIVE: PpdbPublicApiController@register sesuai kontrak (201 + eticket_no + registration_number + username + tracking_token, notif WA fire-and-forget). Smoke prod: honeypot 422 (proteksi aktif).',
                'done', 'high', 100, 'M', 12],
            ['Fase D — Distribusi & Uji Coba', 'Repoint api.asy-syifaa.com → API platform + tes end-to-end',
                'Arahkan base URL website ke API platform (resolusi tenant by-domain), uji semua halaman dinamis.',
                'Endpoint platform SIAP & live. Sisa: ubah base URL di kode website asy-syifaa.com (PHP standalone, repo terpisah) + uji semua halaman dinamis — butuh akses/koordinasi website client.',
                'todo', 'high', 30, 'M', 13],
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
                'Code-ready PENUH (config, disk s3, gating tier, dokumentasi aktivasi). SATU-SATUNYA sisa = keputusan owner beli akun object storage (set AWS_* env). VPS lokal hanya muat ~8 tenant @8GB. TERBLOKIR keputusan infra/biaya — bukan pekerjaan dev.',
                'in_progress', 'high', 80, 'L', 5],
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
                'SELESAI & live. UI kasir transaksional di-ship: halaman LoketKas (Filament) — buka/tutup sesi, catat transaksi per kategori, rekonsiliasi; diperkaya G3-21 (profil loket, diskon netto, metode bayar, tutup sesi tunai).',
                'done', 'medium', 100, 'L', 4],
            ['Mutu approval', 'State-machine approval + versioning dokumen mutu',
                'MutuService (draft→diajukan→disetujui/ditolak), migrasi additive dokumen_mutu_versi + kolom, permission approve_mutu (segregasi di kepala_keuangan), aksi panel DokumenMutuResource. 9 test hijau.',
                'SELESAI & live. Aksi ajukan/setujui/tolak di DokumenMutuResource terpasang + diperkuat G3-17 (MutuKpiService, state-machine teruji); QC visual tuntas.',
                'done', 'low', 100, 'M', 5],
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

    /**
     * Program "Adaptasi ERPNext" — eksekusi BLUEPRINT-ADAPTASI-ERPNEXT-MENYELURUH §6
     * (build loop otonom 2026-06-09..10). Idempotent: updateOrCreate by slug + title.
     */
    private function seedAdaptasiErpnext(): void
    {
        $project = DevProject::updateOrCreate(
            ['slug' => 'adaptasi-erpnext'],
            [
                'name' => 'Adaptasi ERPNext — Fondasi & 3 Gelombang',
                'description' => 'Eksekusi penuh BLUEPRINT-ADAPTASI-ERPNEXT-MENYELURUH §6: 8 fondasi lintas-modul + 23 work-package dalam 3 gelombang = 31 langkah menampung 164 adaptasi konsep ERPNext ke platform pesantren. Metode: build loop otonom 1 WP per siklus, tiap WP = PR kecil + test foreground + adversarial-verify (wajib untuk WP uang) + deploy + QC produksi. Semua posting uang lewat JurnalService (balance, period-lock, kehalalan, idempoten); math teruji tak pernah diubah. SELURUH §6 TUNTAS 31/31 — LIVE per 2026-06-10.',
                'status' => 'done',
                'color' => 'success',
                'icon' => 'heroicon-o-cube-transparent',
                'sort' => 10,
            ]
        );

        // [phase, title, detail, current_state, status, priority, progress, effort, sort]
        $tasks = [
            ['Fondasi', 'F1–F8: 8 fondasi lintas-modul',
                'F1 Approval Workflow Engine (Approvable + ApprovalRule + ajukanPersetujuan), F2 Audit Trail (Auditable tahan-banting), F3 Lapisan Kehalalan + laporan + sertifikat, F4 Party Ledger (buku pembantu per pihak), F5 Document Repository (HasDocuments + versioning), F6 Reconciliation Engine (bank rekon + auto-match idempoten), F7 Notification Hooks (NotifDispatcher), F8 Report & Export Service (html/csv/pdf). Semua di Modules/Core + Modules/Ledger, additif & tenant-safe.',
                'SELESAI & LIVE 8/8 (2026-06-09).',
                'done', 'high', 100, 'XL', 1],
            ['Gelombang 1', 'Tata kelola & diferensiator syariah (8 WP)',
                'G1-01 Approval rollout ke model uang/payroll, G1-02 Audit rollout 8 model inti, G1-03 Laporan+Sertifikat Kehalalan (ekspor F8), G1-04 Tax policy halal/syubhat (Modules/Pajak), G1-05 Tax config UI + kalkulator PPh21 (gate review penasihat pajak), G1-06 Period closing + jurnal penutup, G1-07 Bank rekon (=F6), G1-08 Budget vs actual variance dashboard.',
                'SELESAI & LIVE 8/8 (2026-06-09).',
                'done', 'high', 100, 'XL', 2],
            ['Gelombang 2', 'Compliance operasional & procurement (8 WP)',
                'G2-09 Procurement chain (Supplier→PO→GRN→Belanja), G2-10 Employee master + salary structure, G2-11 Attendance + khidmah + leave, G2-12 BPJS + PPh21 tiered + SPT, G2-13 Fixed asset + depresiasi + disposal (lockForUpdate anti pelepasan ganda), G2-14 Inventory multi-lokasi + batch/FEFO + opname, G2-15 Fund-aware billing, G2-16 Pricing rule (SkemaBiaya). Adversarial-verify menangkap blocker COA-seeding & batch-expiry sebelum rilis.',
                'SELESAI & LIVE 8/8 (2026-06-10, PR #39-41).',
                'done', 'high', 100, 'XL', 3],
            ['Gelombang 3', 'Mutu, pendidikan lanjut & engagement (7 WP)',
                'G3-17 SPMI/Mutu KPI, G3-18 Daily attendance santri + rubric + Tahfidz KPI, G3-19 Kegiatan template + milestone + Gantt + recurring idempoten, G3-20 CRM ringan (funnel + churn + campaign), G3-21 POS enrichment (profil loket, diskon netto, loyalty, tutup sesi tunai), G3-22 Program formalization + transkrip via F5 + penempatan asrama otomatis (gender hard-constraint + partial unique index), G3-23 Pematangan akuntansi: alokasi biaya per divisi (Σ share = nominal persis), multi-currency donor (posting selalu IDR), COA wizard, subsidiary detail.',
                'SELESAI & LIVE 7/7 (2026-06-10, PR #42-48). Menutup §6: 31/31.',
                'done', 'high', 100, 'XL', 4],
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
     * Program "Migrasi Data Asy-Syifaa" — impor data nyata via Excel, bertahap.
     * Idempotent: updateOrCreate by slug + title.
     */
    private function seedMigrasiAsySyifaa(): void
    {
        $project = DevProject::updateOrCreate(
            ['slug' => 'migrasi-data-asy-syifaa'],
            [
                'name' => 'Migrasi Data Asy-Syifaa (Excel → Platform)',
                'description' => 'Migrasi data nyata pesantren Asy-Syifaa dari berkas Excel ke platform, bertahap per domain data. Command artisan idempoten (re-run aman), validasi baris + laporan tolak, preparasi berkas di _PESANTREN/migrasi-asy-syifaa/.',
                'status' => 'in_progress',
                'color' => 'info',
                'icon' => 'heroicon-o-arrow-down-on-square-stack',
                'sort' => 11,
            ]
        );

        // [phase, title, detail, current_state, status, priority, progress, effort, sort]
        $tasks = [
            ['Tooling', 'Command santri:import (Excel → Santri, idempoten)',
                'Impor santri dari Excel: pemetaan kolom, validasi NIS/nama/tanggal lahir, patch alamat (santri:patch-address), re-run aman (update by NIS).',
                'SELESAI (siap pakai). Berkas prep di _PESANTREN/migrasi-asy-syifaa/.',
                'done', 'high', 100, 'M', 1],
            ['Tooling', 'Command wali:import + tautan ke santri',
                'Impor wali dari Excel + penautan otomatis ke santri (relasi wali-anak), idempoten.',
                'SELESAI (siap pakai).',
                'done', 'high', 100, 'M', 2],
            ['Tooling', 'Importer domain data lain (kelas, asrama, tagihan, pembayaran)',
                'Command impor untuk domain selain santri/wali: penempatan kelas & asrama, saldo awal tagihan/pembayaran (posting via JurnalService).',
                'BELUM. Menunggu prioritas + sampel berkas dari TU Asy-Syifaa.',
                'todo', 'medium', 0, 'L', 3],
            ['Eksekusi', 'Import data nyata bertahap di produksi + QC',
                'Jalankan importer di tenant asy-syifaa produksi per gelombang data, QC jumlah baris vs sumber, laporan ke TU.',
                'BELUM dimulai (menunggu berkas final dari pesantren).',
                'todo', 'high', 0, 'M', 4],
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
     * Program "PWA Guru / Khidmat" — BLUEPRINT-PWA-GURU.md (2026-06-11).
     * Satu PWA modular untuk semua pengajar & petugas lapangan; menu digerbang ganda ModuleRegistry x permission.
     */
    private function seedPwaGuru(): void
    {
        $project = DevProject::updateOrCreate(
            ['slug' => 'pwa-guru-khidmat'],
            [
                'name' => 'PWA Guru / Khidmat',
                'description' => 'Satu PWA /guru untuk semua pengajar & petugas lapangan (ustadz mapel, muhaffizh, musyrif, mustahiq, keamanan) — BLUEPRINT-PWA-GURU.md. Pola teknis tiru PWA Wali (Inertia+Vue, manifest dinamis ber-branding tenant); endpoint tulis idempoten tahan sinyal jelek; satu service per domain dipakai PWA dan Filament. Prinsip: disusun per momen kerja, alur scan QR ID card -> aksi. E-learning penuh tetap out-of-scope.',
                'status' => 'planning',
                'color' => 'warning',
                'icon' => 'heroicon-o-device-phone-mobile',
                'sort' => 12,
            ]
        );

        // [phase, title, detail, current_state, status, priority, progress, effort, sort]
        $tasks = [
            ['Gelombang 1 — Jangkar', 'F0: Fondasi data — Halaqah, JurnalMengajar, Kitab, komponen nilai',
                'Migrasi halaqah + pivot halaqah_santri (soft-close left_at), jurnal_mengajar (jadwal XOR halaqah, unique guru+jadwal+tanggal, status terlaksana/kosong/diganti), domain kitab (kitab, kajian_kitab sorogan/bandongan/wetonan, capaian_kitab — santri NULL = posisi kelompok bandongan), komponen_nilai konfigurabel (seeder default 40/30/30, kolom Nilai lama JANGAN drop) + NilaiService satu-satunya rumus nilai akhir.',
                'SELESAI (2026-06-11): 7 tabel baru + NilaiService jalur komponen terbobot (fallback rumus lama utuh) + resource Halaqah/Kitab terdaftar di panel. 13 test hijau + smoke panel + provisioning regression 7/7. Adversarial menangkap & memperbaiki: resource tak terdaftar (BLOCKER), guard id komponen liar (MAJOR), filter select musyrif. Catatan: seed komponen firstOrCreate by nama — tandai seeded sebelum ada UI kelola komponen.',
                'done', 'critical', 100, 'M', 1],
            ['Gelombang 2 — Lapangan', 'F1: PWA Guru inti — jadwal pribadi + presensi kelas + setoran tahfidz',
                'Rute /guru (pola PortalWali), manifest+SW+ikon dinamis salin PortalWaliPwaController. Layar Hari Ini (JadwalPelajaran per guru_pengampu_id + halaqah), presensi kelas sekali-ketuk default hadir (endpoint idempoten kelas+tanggal+santri), setoran tahfidz scan QR (jenis ziyadah/muraja\'ah). PresensiService & TahfidzService dipakai juga Filament. Permission akses_pwa_guru.',
                'SELESAI (2026-06-11): /guru live — Hari Ini (jadwal per guru + halaqah), presensi sekali-ketuk (satu status/santri/hari, jendela tanggal -7..hari ini), setoran tahfidz client_ref idempoten (validasi ayat/nilai/anggota aktif), manifest+SW branding tenant scope /guru/. PresensiService delegasi ke jalur teruji G3-18; TahfidzService::setor transaksional. Adversarial fix MAJOR privasi: SW kini cache shell publik SAJA (halaman ber-auth tak tersimpan di Cache Storage HP bersama). 13 test F1 + total 26/26 hijau + vite. Pasca-deploy: tenant:reprovision utk permission akses_pwa_guru tenant lama.',
                'done', 'critical', 100, 'L', 2],
            ['Gelombang 3A — Akademik', 'F2: Grid nilai massal + jurnal KBM + capaian kitab + wali kelas',
                'Grid nilai per kelas x pelajaran x komponen autosave per-baris (dua pintu: PWA + halaman Filament, SATU NilaiService); form jurnal KBM 30-detik menempel di layar presensi + rekap bulanan; layar sorogan per santri & bandongan per kelompok; layar wali kelas (rekap anak binaan + catatan rapor + rubrik ubudiyah).',
                'Belum mulai. Gap efisiensi terbesar: input nilai sekarang harus keluar-masuk form per santri.',
                'todo', 'high', 0, 'L', 3],
            ['Gelombang 3B — SDM (uang)', 'F3: Pesan guru->wali + slip gaji sendiri',
                'Tabel pesan_wali (jenis apresiasi/perhatian/info) SEARAH dengan template per tenant + saklar moderasi pengasuh; masuk notifikasi Portal Wali + opsional WA. Guru lihat slip gaji miliknya di PWA (gate per-user, route print reuse). Guru hanya bisa kirim ke santri binaannya.',
                'Belum mulai. Jalur komunikasi guru->wali per santri belum ada (Notifikasi hanya broadcast sistem).',
                'todo', 'medium', 0, 'M', 4],
            ['Gelombang 5 — Per permintaan', 'F4: Ujian semester online (generalisasi mesin PPDB)',
                'Refactor EXTRACT PaketSoal/Soal/SesiUjian/UjianAttempt/UjianScoringService ke modul bersama TANPA mengubah perilaku PPDB (semua test PPDB tetap hijau tanpa diubah); kolom konteks akademik (pelajaran/kelas/periode, NULL = mode PPDB); permission pembuat soal per-pelajaran; mode pengawas + koreksi essay di PWA; nilai final -> komponen nilai via NilaiService; halaman ujian di App Santri.',
                'TERBLOKIR keputusan owner: jenjang mana ujian online vs kertas. Mesin ujian PPDB sudah matang produksi (timer server, randomisasi, auto-skor PG).',
                'blocked', 'medium', 0, 'L', 5],
            ['Gelombang 5 — Per permintaan', 'WP-21: Mode pengurus-santri (qism) di PWA',
                'Permission khusus pada akun santri (login NIS+PIN sudah live) — gerbang ganda santri AND pengurus; menu terbatas: catat pelanggaran (status dicatat, menunggu verifikasi ustadz) + presensi kegiatan domain qism-nya; masa jabatan ber-periode, habis -> permission dicabut otomatis.',
                'Menunggu WP-09 (modul disiplin) + F1 live.',
                'todo', 'medium', 0, 'M', 6],
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
     * Program "Modul Akademik Pesantren" — BLUEPRINT-AKADEMIK-SDM.md Bagian I (2026-06-11).
     * Jangkar Lembaga + dua kalender + kurikulum + kenaikan kelas + raport ganda + disiplin.
     */
    private function seedModulAkademikPesantren(): void
    {
        $project = DevProject::updateOrCreate(
            ['slug' => 'modul-akademik-pesantren'],
            [
                'name' => 'Modul Akademik Pesantren (Lembaga, Kurikulum, Raport Ganda)',
                'description' => 'Perincian modul Akademik untuk semua tipe pesantren — BLUEPRINT-AKADEMIK-SDM.md Bagian I. Jangkar: model Lembaga (unit ber-NSM/NSPP, tertaut DivisiUnit Ledger). Dua kalender paralel Masehi/Hijriyah; kurikulum per tingkat ber-kitab; kenaikan kelas = rekomendasi sistem + sidang musyawarah guru (bukan otomatis); raport formal vs raport diniyah terpisah; disiplin poin verifikasi 2-lapis; EMIS arsip-dulu-ekspor-nanti. Prinsip policy-first: semua ambang via Setting per tenant.',
                'status' => 'planning',
                'color' => 'info',
                'icon' => 'heroicon-o-academic-cap',
                'sort' => 13,
            ]
        );

        $tasks = [
            ['Gelombang 1 — Jangkar', 'WP-01: Model Lembaga + tautan DivisiUnit Ledger',
                'Tabel lembaga (jenis formal/diniyah/tahfidz/pondok, jenjang, NSM/NSPP, IJOP, kepala_id, divisi_unit_id FK Ledger); seeder 1 lembaga Pondok per tenant + hook TenantProvisioner; ALTER kelas + lembaga_id (backfill ke default); ALTER periode_akademik + lembaga_id + kalender masehi/hijriyah + tahun_hijriyah, unique -> tenant+lembaga+tahun+semester (periode aktif ganda beda lembaga sah).',
                'SELESAI (2026-06-11): lembaga + backfill aman-FK dalam migrasi + seeder idempoten (terbukti tak dobel dengan backfill) + resource panel + select di Kelas/Periode. Adversarial menangkap MAJOR: aktivasi periode mematikan periode lembaga lain (PeriodeAkademikService kini scoped per lembaga, NULL-safe). 11 test hijau + smoke + provisioning 8/8. Catatan: rollback down() butuh pembersihan data per-lembaga manual.',
                'done', 'critical', 100, 'M', 1],
            ['Gelombang 1 — Jangkar', 'WP-02: Kalender akademik + hari efektif',
                'Tabel kalender_akademik (lembaga NULL = semua; jenis libur_nasional/libur_pondok/ramadhan/ujian/pasaran/kegiatan); KalenderService::isHariEfektif + hariEfektifDalamRentang; seeder libur nasional; tampilan kalender bulanan. Dipakai presensi, rekap kehadiran, potongan cuti. Tanggal Hijriyah = label manual, JANGAN bangun konversi otomatis.',
                'SELESAI (2026-06-11): tabel + KalenderService (1 query overlap, anti double-count) + seeder 15 libur nasional 2026 + resource panel. Tombol kebijakan PERTAMA terbukti: akademik.hari_libur_mingguan (Jumat vs Minggu mengubah hitungan, [] = mengaji tiap hari). Adversarial fix 2 MAJOR: kunci seeder kini keterangan-saja (geser tanggal Hijriyah tak melahirkan duplikat; seeder 2027 WAJIB sertakan tahun di keterangan), parsing setting toleran string/typo (typo total fallback default, bukan diam-diam tanpa libur). 13 test hijau + smoke.',
                'done', 'high', 100, 'S', 2],
            ['Gelombang 1 — Jangkar', 'WP-05: Observer sinkron PenempatanKelas -> Santri.kelas_id',
                'Observer: is_current berubah -> penempatan lain santri di-false-kan + Santri.kelas_id sinkron; command audit satu-kali menampilkan santri yang kelas_id != penempatan aktif (JANGAN auto-fix data produksi).',
                'SELESAI (2026-06-11): hook saving/saved anti-rekursi (deaktivasi SEBELUM insert — kompatibel partial unique), partial unique (santri_id) WHERE is_current + dedupe data lama dalam satu migrasi, toggle-off manual & delete jatuhkan kelas_id ke pengganti/NULL, command kesantrian:audit-penempatan (--fix eksplisit). Adversarial fix 2 BLOCKER + 1 MAJOR + perketat test anti-lulus-palsu. 9 test hijau. Catatan: --fix tanpa jejak audit; bulk insert lewat observer termitigasi index DB + audit command.',
                'done', 'critical', 100, 'S', 3],
            ['Gelombang 3A — Akademik', 'WP-06: Kurikulum per tingkat',
                'Tabel kurikulum + kurikulum_mapel (mapel x tingkat x kitab, jam/minggu, wajib, urutan); validasi LUNAK di form jadwal (warning bukan blokir); aksi Buka Periode auto-generate baris Nilai kosong (idempoten); cetak struktur kurikulum PDF.',
                'Belum mulai. Pelajaran sekarang flat tanpa ikatan tingkat.',
                'todo', 'high', 0, 'M', 4],
            ['Gelombang 3A — Akademik', 'WP-07: Kenaikan kelas + layar sidang musyawarah',
                'KenaikanKelasService::evaluasi (nilai rata + % kehadiran hari-efektif + poin disiplin + capaian tahfidz/kitab bila disyaratkan; SEMUA ambang via Setting per lembaga) -> REKOMENDASI; halaman Sidang Kenaikan pola LoketKas (default = rekomendasi, override per santri tercatat Auditable); finalisasi batch PenempatanKelas SATU transaksi; mode mu\'adalah (tingkat bebas) via Setting.',
                'Belum mulai. Keputusan kenaikan di pesantren = musyawarah; sistem hanya menyiapkan data.',
                'todo', 'high', 0, 'L', 5],
            ['Gelombang 3A — Akademik', 'WP-08: Kelulusan, syahadah & mutasi',
                'Lulus -> tabel alumni (sudah ada) + status santri; syahadah internal PDF per lembaga (nomor seri counter race-safe pola SuratService); arsip nomor ijazah negara; mutasi keluar/masuk pondok (jalur pindahan di PPDB).',
                'Belum mulai.',
                'todo', 'medium', 0, 'M', 6],
            ['Gelombang 3A — Akademik', 'WP-10: Raport diniyah (raport kedua)',
                'Tabel rapor_diniyah + RaporDiniyahService: nilai imtihan + capaian kitab + hafalan/sima\'an + ubudiyah-akhlak (reuse RubrikKriteria/RubrikSkor) + kehadiran kegiatan + poin disiplin + catatan mustahiq/pengasuh; blok yang tampil = Setting per lembaga; kop tanggal Hijriyah; tab Raport Diniyah + unduh PDF di Portal Wali.',
                'Belum mulai. Dua raport terpisah adalah norma pesantren (formal RDM vs diniyah).',
                'todo', 'high', 0, 'L', 7],
            ['Gelombang 3A — Akademik', 'WP-11: Sima\'an, munaqasyah, wisuda tahfidz, pasaran',
                'Tabel sima_an + munaqasyah (hasil lulus/mengulang) + penanda wisuda per periode; layar di PWA Guru mode muhaffizh; kegiatan pasaran/kilatan Ramadhan via modul Kegiatan yang ada + kolom peserta_luar JSON (santri luar, tidak menyentuh tabel santri).',
                'Belum mulai.',
                'todo', 'medium', 0, 'M', 8],
            ['Gelombang 3A — Akademik', 'WP-12: Rekap kesiapan EMIS',
                'Halaman rekap % santri ber-NIK/NISN, % pegawai ber-NIK/NUPTK, lembaga tanpa NSPP/IJOP + tautan ke daftar yang kurang; audit kolom santri (nik/nisn/mukim, tambah bila belum). EMIS = prasyarat semua bantuan Kemenag (BOP, insentif ustadz).',
                'Belum mulai. Ekspor Excel EMIS menyusul terpisah (WP-22) saat ada tenant yang minta.',
                'todo', 'medium', 0, 'S', 9],
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
     * Program "Modul SDM/HRD" — BLUEPRINT-AKADEMIK-SDM.md Bagian II (2026-06-11).
     * Jangkar PenugasanPegawai multi-peran; loop bisyaroh dari sesi mengajar nyata.
     */
    private function seedModulSdmHrd(): void
    {
        $project = DevProject::updateOrCreate(
            ['slug' => 'modul-sdm-hrd'],
            [
                'name' => 'Modul SDM/HRD Pesantren (Penugasan, Bisyaroh, Kontrak)',
                'description' => 'SDM khas pesantren — BLUEPRINT-AKADEMIK-SDM.md Bagian II. Jangkar: penugasan_pegawai many-to-many (pegawai x jabatan x lembaga x periode + SK) karena rangkap jabatan adalah norma. Loop bisyaroh: JurnalMengajar/PresensiStaf -> KhidmahAggregator -> staging khidmah -> SlipGaji.poin (mesin slip & posting JurnalService TIDAK diubah; semua WP uang adversarial 3 lensa). Status kepegawaian lengkap (GTY/GTT/PNS-DPK/PPPK/pengabdian); arsip sertifikasi TPG/inpassing; alur guru pengabdian. Diferensiasi vs pesaing yang dangkal di sini.',
                'status' => 'planning',
                'color' => 'warning',
                'icon' => 'heroicon-o-identification',
                'sort' => 14,
            ]
        );

        $tasks = [
            ['Gelombang 1 — Jangkar', 'WP-03: Master jabatan + penugasan multi-peran + SK',
                'Tabel jabatan (kategori + mapping role_spatie, seeder 15 jabatan dari riset: mudir, mustahiq, musyrif, muhaffizh, lurah pondok, qism, dst) + penugasan_pegawai (employee x jabatan x lembaga x periode, nomor/file SK, is_primary); observer assign/revoke role Spatie (revoke hanya bila tak ada penugasan lain ber-role sama); Employee.jabatan string jadi deprecated read-only; cetak SK via SuratTemplate.',
                'SELESAI (2026-06-11): jabatan (15 seed, mapping HANYA ke role eksisting; lurah pondok+qism NULL by design) + penugasan_pegawai (partial unique primary aktif, auto-demote) + 3 observer role-sync (Penugasan/Jabatan/Employee — pindah pegawai/jabatan/user semua memindahkan role, tak ada orphan) + cetak SK via Setting payroll.template_sk (SuratTemplate tak ada di repo; siap WP-34) + guarded delete jabatan. Adversarial fix 5 MAJOR. 16 test hijau + payroll lama 22/22 utuh. Catatan: mass-update query builder bypass observer (didokumentasikan).',
                'done', 'critical', 100, 'L', 1],
            ['Gelombang 1 — Jangkar', 'WP-04: Perluasan data pegawai (EMIS-ready)',
                'ALTER employees: nik, nuptk, nrg, status_kepegawaian enum baru (gty/gtt_honorer/pns_dpk/pppk/pengabdian/kontrak — migrasi map dari enum lama), is_mukim, tanggal_selesai, pendidikan_terakhir, asal_alumni; tab Identitas & Kepegawaian di form; PayrollService tetap jalan semua status.',
                'SELESAI (2026-06-11) — MENUTUP GELOMBANG 1 (8/8). Kolom EMIS (NIK unik NULL-safe, NUPTK, NRG, mukim, pendidikan, asal alumni) + status 6 nilai (map data lama idempoten, nilai asing dibiarkan utuh + opsi "(nilai lama)" di form) + sinkron tanggal_selesai->aktif satu arah. Adversarial fix 3 MAJOR, terpenting: LUBANG AKSES pegawai purna — nonaktif kini auto-tutup semua penugasan aktif -> role tercabut (hormati aturan rangkap). Catatan operasional: aktifkan-ulang pegawai = buat penugasan BARU (riwayat jujur). 22 test hijau + smoke.',
                'done', 'high', 100, 'S', 2],
            ['Gelombang 3B — SDM (uang)', 'WP-13: Loop bisyaroh — tarif khidmah + aggregator + pratinjau',
                'Tabel tarif_khidmah per tenant (jabatan/jenis_sesi -> poin); KhidmahAggregatorService per periode: JurnalMengajar terlaksana + PresensiStaf + penugasan aktif -> baris khidmah per pegawai per sumber (tertelusur sumber_type/id; idempoten; baris manual tak disentuh); layar pratinjau bendahara SEBELUM slip massal (koreksi di sumber, bukan edit slip); buatSlip baca Sigma khidmah -> SlipGaji.poin.',
                'Belum mulai. UANG — adversarial 3 lensa wajib: sesi diganti (poin ke pengganti, tak dobel), guru 2 lembaga 2 tarif, agregasi ulang setelah slip final DITOLAK. Mesin slip+posting yang teruji TIDAK diubah.',
                'todo', 'critical', 0, 'L', 3],
            ['Gelombang 3B — SDM (uang)', 'WP-14: Potongan cuti tak berbayar',
                'Jenis cuti berbayar/tidak di master; hitung di terapkanPotongan(): hari x (honor_pokok / hari_kerja_periode dari KalenderService); cuti beririsan Ramadhan/libur pondok TIDAK memotong; cuti lintas dua periode terpotong proporsional.',
                'Belum mulai. UANG — snapshot test: math PPh21/BPJS lama tak berubah satu rupiah pun.',
                'todo', 'high', 0, 'S', 4],
            ['Gelombang 3B — SDM (uang)', 'WP-15: THR / bingkisan hari raya',
                'SlipGaji tipe thr (unique per pegawai per tahun); formula via Setting (nominal tetap / kali honor pokok / manual); generate massal + pratinjau; posting JurnalService idempotency_key SlipGaji:thr:{id} — jalur posting SAMA.',
                'Belum mulai. UANG.',
                'todo', 'medium', 0, 'S', 5],
            ['Gelombang 3B — SDM', 'WP-16: Kontrak/SK + alur guru pengabdian',
                'Tabel kontrak_kerja (jenis sk_gty/kontrak/sk_pengabdian/sk_dpk, arsip file, status); khusus pengabdian: periode_khidmat + lembaga_pengirim + tanggal_purna + laporan angkatan; widget + notifikasi kontrak habis <=60 hari (ambang Setting); perpanjangan = baris baru bertaut; purna -> tawaran GTY/GTT = penugasan baru.',
                'Belum mulai. Guru pengabdian (alumni khidmat 1-2 th) = kekhasan pesantren yang tak dimiliki aplikasi pesaing.',
                'todo', 'medium', 0, 'M', 6],
            ['Gelombang 3B — SDM', 'WP-17: Izin pegawai harian',
                'Jenis izin baru di tabel cuti yang ada (pulang_kampung, keperluan_keluarga, dinas — JANGAN tabel baru); approval = kepala lembaga dari penugasan is_primary (fallback admin); pengajuan & status dari PWA Guru.',
                'Belum mulai.',
                'todo', 'medium', 0, 'S', 7],
            ['Gelombang 3B — SDM', 'WP-18: Arsip sertifikasi (TPG/inpassing/GBPNS)',
                'Tabel sertifikasi_pegawai (jenis, NRG, SK, nominal, masa berlaku, file) — ARSIP untuk syarat & pelaporan SIMPATIKA, bukan mesin hitung; tab di EmployeeResource + masuk rekap kesiapan EMIS.',
                'Belum mulai.',
                'todo', 'low', 0, 'S', 8],
            ['Gelombang 5 — Per permintaan', 'WP-19: Rekrutmen ringan ala pesantren',
                'Tabel pelamar (asal, alumni?, berkas, tahap lamaran/tes_baca_kitab/microteaching/wawancara/diterima/ditolak, catatan penilai per tahap) — satu resource + aksi "jadikan pegawai" (-> Employee + penugasan). BUKAN ATS korporat.',
                'Menunggu kebutuhan nyata tenant.',
                'todo', 'low', 0, 'S', 9],
            ['Gelombang 5 — Per permintaan', 'WP-20: Rapor ustadz tahunan',
                'Rekap otomatis per pegawai (% kehadiran sesi vs jadwal, % jurnal terisi, ketepatan input nilai) + penilaian kualitatif mudir via RubrikKriteria + cetak PDF — bahan pertimbangan SK tahun berikutnya.',
                'Menunggu WP-13 berjalan minimal 1 periode.',
                'todo', 'low', 0, 'M', 10],
            ['Gelombang 5 — Per keputusan', 'WP-23: Kasbon pegawai + potong cicilan',
                'Tabel pinjaman_pegawai (approval via ApprovalRule, cicilan_bulanan, sisa_pokok); potongan cicilan otomatis di terapkanPotongan() berhenti saat lunas; pencairan = jurnal piutang pegawai; total cicilan = pokok persis tanpa selisih pembulatan.',
                'TERBLOKIR kebijakan owner: plafon & aturan potong. UANG — adversarial 3 lensa (resign sisa utang, cicilan > gaji, pembulatan akhir).',
                'blocked', 'medium', 0, 'M', 11],
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
     * Program "Operasional Lapangan per Fungsi" — BREAKDOWN-TUGAS-DEVELOPER.md (2026-06-11).
     * Kedisiplinan, kepengasuhan, kesehatan, keamanan, sarpras — panel + mode lapangan PWA + tombol kebijakan.
     */
    private function seedOperasionalLapangan(): void
    {
        $project = DevProject::updateOrCreate(
            ['slug' => 'operasional-lapangan'],
            [
                'name' => 'Operasional Lapangan per Fungsi (Disiplin, Pengasuhan, UKS, Gerbang)',
                'description' => 'Bedah per fungsi staf — BREAKDOWN-TUGAS-DEVELOPER.md. Tiap fungsi 3 lapis: ruang kerja panel + mode lapangan di PWA + tombol kebijakan via Setting (PRINSIP POLICY-FIRST: sistem ikut kebijakan pesantren, bukan sebaliknya — tak ada aturan hardcode, master di-seed tapi bebas ubah, saklar workflow, label istilah per tenant). DoD tiap tugas wajib membuktikan dua kebijakan berbeda -> perilaku berbeda.',
                'status' => 'planning',
                'color' => 'danger',
                'icon' => 'heroicon-o-shield-check',
                'sort' => 15,
            ]
        );

        $tasks = [
            ['Gelombang 1 — Jangkar', 'WP-31: Label istilah per tenant',
                'Setting label.istilah (JSON map musyrif/mustahiq/takzir/halaqah/bisyaroh -> label tenant) + helper istilah() dipakai navigasi & form (sapu bertahap mulai modul baru) + halaman edit. Pesantren beda istilah bukan beda fitur.',
                'SELESAI (2026-06-11): IstilahRegistry 11 kunci + helper istilah() (autoload.files pertama di repo) + halaman Pengaturan > Istilah (simpan hanya override) + dipakai di HalaqahResource sbg bukti pola. Parsing toleran (pelajaran WP-02), cache per-tenant + flush di TenancyInitialized/Ended (adversarial: queue worker long-running pegang cache basi — FrankenPHP web classic mode aman). XSS label & gate 403 dibuktikan test. 7 test hijau. WAJIB daftar ke WP-34.',
                'done', 'medium', 100, 'S', 1],
            ['Gelombang 1 — Jangkar', 'WP-32: Setor Keliling masuk PWA Keuangan',
                'Layar di /admin/keuangan/pwa: kamar/blok -> santri -> setoran (atau scan QR); pakai SetorKelilingService yang SUDAH ada; endpoint idempoten; antrean offline IndexedDB pola camera-gps.js + badge belum-tersinkron.',
                'SELESAI (2026-06-11): 5 endpoint API gate setor_tabungan + layar Vue antrean offline + idempotensi client_ref 2 lapis (insert-time + unique DB). Setoran PWA = item DRAF, posting saldo tetap via persetujuan bendahara (mesin uang tak disentuh). Adversarial 3 lensa: fix 500 santri invalid, fix retry-selamanya 4xx (offline-sync kini tandai gagal+panel merah), fix draf kemarin macet (ajukan kunci semua draf berisi). 11 test hijau + vite hijau. Catatan: timezone app UTC vs WIB digeser jadi keputusan terpisah.',
                'done', 'high', 100, 'S', 2],
            ['Gelombang 2 — Lapangan', 'WP-09: Modul disiplin/ta\'zir berbasis poin',
                'Master jenis_pelanggaran (kategori, poin, sanksi_default, seeder contoh); pelanggaran_santri (kronologi, foto bukti, status dicatat/diverifikasi/sanksi_dijalankan/selesai/dibatalkan — pelanggaran catatan pengurus santri WAJIB verifikasi ustadz bila saklar ON); sanksi_santri; akumulasi poin = QUERY per periode (bukan kolom); ambang eskalasi Setting -> SP otomatis + surat + notifikasi wali ber-kebijakan; poin positif/prestasi (saklar); panel Kedisiplinan (antrean verifikasi, rekap, tren).',
                'Belum mulai. Riset: poin berjenjang (50=SP1+panggil wali, 100=SP2) dengan pencatat berlapis qism->mahkamah adalah norma.',
                'todo', 'critical', 0, 'L', 3],
            ['Gelombang 2 — Lapangan', 'WP-24: Disiplin di PWA (catat-verifikasi-eksekusi)',
                'Form catat kilat scan QR -> jenis -> foto -> kirim; antrean verifikasi ustadz/pengasuhan di PWA; ceklis eksekusi sanksi + bukti; pencatat tak bisa memverifikasi catatannya sendiri.',
                'Menunggu WP-09 + F1 PWA Guru.',
                'todo', 'high', 0, 'S', 4],
            ['Gelombang 2 — Lapangan', 'WP-25: Presensi kamar & sholat per-kelompok',
                'Master slot presensi pondok via Setting (subuh/maghrib/isya/malam dst aktif per tenant); layar kamar/halaqah -> grid santri x slot default hadir; simpan ke PresensiKegiatan reuse (jenis=slot, JANGAN tabel baru); rekap per kamar/slot di panel Kepengasuhan.',
                'Menunggu F1 PWA Guru. Beda dari presensi kelas: satuan kamar & slot waktu, bukan rombel & mapel.',
                'todo', 'high', 0, 'M', 5],
            ['Gelombang 2 — Lapangan', 'WP-26: Alur izin pulang lintas-aplikasi',
                'PWA Wali ajukan -> antrean musyrif di PWA Guru (cek kuota bulanan Setting) -> surat izin PDF ber-QR (SuratTemplate + chillerlan) -> tandai sudah-kembali + alarm keterlambatan -> notifikasi WA wali saat disetujui & jatuh tempo. Kebijakan: maks izin/bulan, H-pengajuan, siapa boleh mengajukan, approval berlapis ON/OFF. IzinSantri sudah ada — yang baru alur antar-aplikasinya.',
                'Menunggu F1 PWA Guru. Query izin-valid disiapkan untuk pos gerbang (WP-29).',
                'todo', 'high', 0, 'M', 6],
            ['Gelombang 2 — Lapangan', 'WP-27: Register UKS + istirahat->presensi',
                'PWA mode kesehatan: scan QR -> kunjungan (keluhan, tindakan, obat, status pulang/istirahat/rujuk); status istirahat -> PresensiSantri otomatis sakit (observer, berhenti saat pulih); rujukan keluar: biaya + penanggung via Setting (pondok/wali/dana_kebajikan) -> draft tagihan/pengeluaran jalur Keuangan yang ada; notifikasi wali ber-kebijakan (semua/menular/rawat).',
                'Menunggu F1 PWA Guru. RiwayatKesehatan reuse.',
                'todo', 'high', 0, 'M', 7],
            ['Gelombang 4 — Gerbang & wali', 'WP-29: Pos gerbang scan + verifikasi foto',
                'Layar pos (tablet/HP): scan QR/NFC autofocus (pola keyboard-wedge Stasiun) -> FOTO SANTRI BESAR + status izin (anti kartu pinjam); log gerbang keluar/masuk (tanpa izin valid = merah + notifikasi pengasuhan); tandai kembali langsung dari pos; cache offline daftar izin aktif hari ini.',
                'Menunggu WP-26 + kartu QR/NFC dari Stasiun Pendaftaran F1/F3.',
                'todo', 'high', 0, 'M', 8],
            ['Gelombang 4 — Gerbang & wali', 'WP-30: Penjengukan terjadwal',
                'Jadwal penjengukan per tenant (hari/jam/kuota slot via Setting); PWA Wali ajukan slot (konfirmasi otomatis/manual via saklar); registrasi tamu di pos cocok dengan pengajuan + wali terdaftar; walk-in via saklar; KunjunganWali reuse + kolom slot & pengajuan_id.',
                'Belum mulai. Bisa paralel.',
                'todo', 'medium', 0, 'M', 9],
            ['Gelombang 5 — Per permintaan', 'WP-28: Stok obat UKS (modul-flag)',
                'Tabel obat + stok masuk/keluar (keluar otomatis dari register UKS); peringatan stok menipis; TANPA batch-expiry rumit (pelajaran G2-14); stok tak bisa minus.',
                'Menunggu WP-27 + tenant yang butuh.',
                'todo', 'low', 0, 'S', 10],
            ['Gelombang 5 — Per permintaan', 'WP-33: Lapor kerusakan & piket',
                'Tabel laporan_kerusakan (pelapor user/pengurus-santri, lokasi, foto /_tenant-storage, status masuk/dikerjakan/selesai, biaya -> draft pengeluaran jalur Keuangan); ceklis piket area ber-QR lokasi (master area per tenant).',
                'Prioritas rendah — setelah fungsi inti.',
                'todo', 'low', 0, 'M', 11],
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
     * Program "Dasbor Mutu & Ekosistem" — PETA-EKOSISTEM-DIGITAL-PESANTREN.md (2026-06-11).
     * Tolok ukur 3 tujuan: bisnis sehat, mutu santri, maslahat keluarga besar.
     */
    private function seedMutuEkosistem(): void
    {
        $project = DevProject::updateOrCreate(
            ['slug' => 'mutu-ekosistem'],
            [
                'name' => 'Dasbor Mutu & Ekosistem (Kebijakan, Pimpinan, Kantin, Baitul Maal)',
                'description' => 'Lapisan integrasi di atas modul inti — PETA-EKOSISTEM-DIGITAL-PESANTREN.md. Tolok ukur semua modul: (1) bisnis sehat, (2) mutu santri terjaga (penjaminan mutu UU 18/2019), (3) maslahat keluarga besar. Dasbor Mutu = agregat lintas 6 modul + lampu kuning otomatis (alat jual terkuat); halaman Pengaturan Kebijakan Pesantren = bukti janji policy-first yang TERLIHAT pembeli; POS kantin via saldo tabungan; Baitul Maal digital (dimensi SumberDana+kehalalan+wakaf SUDAH ada di Ledger sejak modul Keuangan — tinggal pintunya).',
                'status' => 'planning',
                'color' => 'success',
                'icon' => 'heroicon-o-sparkles',
                'sort' => 16,
            ]
        );

        $tasks = [
            ['Gelombang 4 — Pemoles', '2a: Dasbor Mutu Santri terpadu + lampu kuning',
                'MutuService::profil agregat: nilai rata, laju hafalan per pekan, capaian kitab, % kehadiran per jenis, poin disiplin, kunjungan UKS — semua QUERY ke tabel yang ada, tanpa tabel data baru; aturan lampu kuning via Setting (default: hafalan macet >=3 pekan, alpa >=3/bulan, poin >=75% ambang SP, nilai turun >=15); layar per-santri/per-kelas heatmap/per-lembaga; lampu -> notifikasi mustahiq + wali (saklar per jenis); job harian (queue), bukan real-time berat.',
                'Menunggu data lapangan mengalir (F1/F2 PWA Guru + WP-09/25/27). Wujud digital penjaminan mutu dewan masyayikh.',
                'todo', 'critical', 0, 'M', 1],
            ['Gelombang 4 — Pemoles', 'WP-35: Dasbor & approval pimpinan di PWA',
                'Mode pimpinan: kas hari ini (reuse dasbor PWA Keuangan), kehadiran santri/guru, lampu kuning mutu, antrean approval (ApprovalRequest + izin + SP) dengan aksi approve/tolak dari HP — baca-saja selain approval.',
                'Menunggu Dasbor Mutu 2a.',
                'todo', 'medium', 0, 'S', 2],
            ['Gelombang 4 — Pemoles', 'WP-34: Halaman Pengaturan Kebijakan Pesantren',
                'Registry kebijakan (tiap WP mendaftarkan key Setting + label awam + default + kelompok disiplin/izin/kesehatan/akademik/keuangan/istilah); SATU halaman terkelompok + pencarian + kembalikan-ke-bawaan per item & kelompok; audit trail perubahan (siapa, kapan, dari->ke); ekspor/impor profil kebijakan antar tenant (preset "salafiyah kecil" / "modern multi-lembaga" — onboarding tinggal impor).',
                'Dikerjakan TERAKHIR di gelombang 4 — mengumpulkan semua tombol kebijakan yang sudah dibuka WP-WP sebelumnya.',
                'todo', 'high', 0, 'M', 3],
            ['Gelombang 5 — Per permintaan', 'WP-22: Ekspor Excel EMIS',
                'Ekspor per kategori (lembaga/santri/pegawai) sesuai kolom EMIS 4.0 — format diverifikasi terhadap template EMIS terbaru SAAT dikerjakan (sering berubah, jangan kunci sekarang); data kurang -> daftar peringatan, bukan gagal. JANGAN bangun integrasi API EMIS (tak ada API publik stabil).',
                'Menunggu tenant yang minta + WP-12 rekap kesiapan.',
                'todo', 'low', 0, 'S', 4],
            ['Gelombang 5 — Ekosistem', '2b: POS kantin/koperasi via saldo tabungan',
                'Layar kasir pola LoketKas: scan kartu -> UID+PIN (aturan uang kartu dari blueprint Stasiun) -> potong saldo tabungan; batas belanja harian per santri (wali bisa atur dari PWA Wali); posting per unit usaha (DivisiUnit) jalur JurnalService; saldo tak bisa minus; wali lihat riwayat belanja anak.',
                'TERBLOKIR kartu NFC/QR Stasiun F3 + keputusan owner unit usaha. UANG — adversarial 3 lensa (saldo race 2 kasir, refund, rekonsiliasi harian).',
                'blocked', 'high', 0, 'L', 5],
            ['Gelombang 5 — Ekosistem', '2c: Baitul Maal digital (donasi, beasiswa, transparansi)',
                'Halaman donasi publik di website tenant (program, konfirmasi transfer manual dulu — payment gateway menunggu keputusan owner); pencatatan muzakki/munfiq + kuitansi PDF; dana masuk -> SumberDana sesuai (dimensi zakat/infaq/wakaf SUDAH ada); beasiswa santri -> potongan otomatis tagihan SPP dari dana kebajikan (jalur piutang yang ada, adversarial); laporan penyaluran publik per program; KehalalanGuard: dana wakaf tak terpakai program non-wakaf.',
                'Menunggu gelombang inti live. UANG — diferensiasi syar\'i terkuat vs pesaing.',
                'blocked', 'high', 0, 'L', 6],
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
