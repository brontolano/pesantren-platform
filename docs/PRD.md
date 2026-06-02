# Product Requirements Document (PRD)
## Asy-Syifaa Digital Platform

| | |
|---|---|
| **Produk** | Asy-Syifaa Digital Platform — Sistem digital terpadu Pondok Pesantren Asy-Syifaa Wal Mahmuudiyyah, Sumedang |
| **Versi Dokumen** | v1.0 |
| **Tanggal** | 2026-06-02 |
| **Status** | Living Document — sebagian modul sudah berjalan di produksi |
| **Pemilik Produk** | Yayasan / Manajemen Pondok Pesantren Asy-Syifaa |
| **Domain Produksi** | `erp.asy-syifaa.com` · `api.asy-syifaa.com` · `www.asy-syifaa.com` · `app.asy-syifaa.com` |

> **Catatan altitude dokumen.** PRD ini adalah *master product document* untuk seluruh platform (4 aplikasi). Ia mendokumentasikan _apa_ yang harus dicapai produk dan _kenapa_, bukan _bagaimana_ implementasinya. Bagian "Status Saat Ini" pada tiap modul membedakan **yang sudah dibangun** dari **target yang belum tercapai**, supaya PRD bisa dipakai sekaligus sebagai baseline dan peta jalan.

---

## 1. Ringkasan Eksekutif

Asy-Syifaa Digital Platform adalah ekosistem digital terpadu untuk mendigitalkan operasional pondok pesantren — dari **penerimaan santri baru (SPMB/PPDB)**, **administrasi kepesantrenan & akademik**, **keuangan (tagihan, pembayaran, tabungan)**, hingga **keterlibatan wali santri** melalui aplikasi mobile. Platform terdiri dari empat aplikasi yang saling terhubung di atas satu basis data terpusat:

1. **ERP + Admin Panel** (Laravel 13 + Filament 5) — pusat operasional & system-of-record.
2. **DataMaster API** (Node.js + Express + Prisma) — gateway API terpusat (target jangka panjang).
3. **Website Publik** (PHP) — etalase pesantren + formulir pendaftaran online.
4. **Aplikasi Wali** (Vue 3 PWA) — jendela orang tua/wali ke kehidupan santri.

**Masalah inti yang dipecahkan:** operasional pesantren saat ini tersebar di buku besar manual, spreadsheet, dan grup WhatsApp — menyebabkan data ganda, tagihan yang sulit ditagih, dan wali yang "buta" terhadap perkembangan & keuangan anaknya. Platform ini menyatukan semuanya menjadi satu sumber data, satu alur kerja, dan satu kanal komunikasi resmi.

---

## 2. Latar Belakang & Problem Statement

### 2.1 Konteks
Pondok pesantren mengelola ratusan santri mukim dengan kebutuhan administrasi yang kompleks: pendaftaran tahunan, penempatan kamar/kelas, penilaian akademik & hafalan Al-Qur'an, tagihan SPP bulanan (kalender Masehi maupun Hijriah), uang saku/tabungan santri, perizinan keluar, kesehatan, hingga kunjungan orang tua. Mayoritas pesantren menjalankan ini secara manual.

### 2.2 Problem Statement
> Pengelola pesantren tidak memiliki satu sistem terpadu untuk mengelola data santri, keuangan, dan akademik; sementara wali santri tidak punya akses transparan & real-time atas tagihan, pembayaran, hafalan, kehadiran, dan kondisi anaknya. Akibatnya: pekerjaan administrasi berulang dan rawan salah, tunggakan SPP tinggi karena penagihan manual, dan kepercayaan wali menurun karena minimnya transparansi.

### 2.3 Siapa yang Terdampak & Dampaknya
| Pihak | Pain saat ini | Dampak bila tidak diselesaikan |
|---|---|---|
| **Admin/TU pesantren** | Input ganda, rekap manual, sulit melacak tunggakan | Jam kerja terbuang, error data, laporan terlambat |
| **Bagian Keuangan** | Penagihan via japri WA satu-satu, bukti bayar tercecer | Tunggakan tinggi, arus kas tidak terprediksi |
| **Pengasuh/Ustadz** | Catatan hafalan & nilai di buku/kertas | Progres santri tidak terukur & tidak bisa dibagikan |
| **Wali santri** | Tidak tahu tagihan, saldo tabungan, hafalan, izin anak | Telat bayar, cemas, komplain, kepercayaan menurun |
| **Calon santri/ortu** | Pendaftaran harus datang langsung | Jangkauan pendaftaran terbatas, beban panitia PPDB tinggi |

---

## 3. Tujuan (Goals)

Tujuan dinyatakan sebagai **outcome terukur**, bukan output fitur.

| # | Tujuan | Indikator Sukses (target awal) |
|---|---|---|
| **G1** | **Satu sumber data** untuk seluruh data santri & operasional | ≥ 95% data santri aktif terkelola di platform (bukan spreadsheet) dalam 1 semester |
| **G2** | **Menurunkan tunggakan SPP** lewat penagihan & pembayaran digital | Rasio tagihan tertunggak turun ≥ 30% dalam 2 siklus penagihan |
| **G3** | **Transparansi penuh ke wali** atas keuangan & perkembangan santri | ≥ 60% wali aktif login App minimal 1×/bulan dalam 3 bulan pasca-rilis |
| **G4** | **Digitalisasi PPDB end-to-end** dari daftar online → seleksi → registrasi ulang | ≥ 70% pendaftar masuk via formulir online pada PPDB berikutnya |
| **G5** | **Efisiensi administrasi** — kurangi pekerjaan manual TU & keuangan | Waktu rekap tagihan & laporan keuangan bulanan turun ≥ 50% |
| **G6** | **Komunikasi resmi terpusat** menggantikan japri WA sporadis | ≥ 80% notifikasi tagihan & pengumuman terkirim via kanal resmi (WA gateway) |

---

## 4. Non-Goals (Batasan Lingkup)

Hal-hal berikut **secara eksplisit di luar lingkup** versi saat ini, dengan alasannya:

| Non-Goal | Alasan |
|---|---|
| **Payment gateway otomatis (VA/QRIS) dengan rekonsiliasi real-time** | v1 memakai transfer manual + unggah bukti bayar; integrasi PG adalah inisiatif terpisah (lihat Roadmap P2) |
| **LMS / e-learning penuh (materi, kuis, video pembelajaran)** | Fokus platform adalah administrasi & keterlibatan wali, bukan pengajaran daring |
| **Aplikasi khusus santri (santri-facing app)** | Santri mukim; kanal utama adalah wali. Dapat ditinjau ulang di masa depan |
| **Multi-pesantren / SaaS multi-tenant** | Platform dirancang untuk satu pesantren dulu; multi-tenant adalah arahan arsitektur masa depan |
| **Akuntansi keuangan penuh (jurnal, neraca, GL)** | Lingkup keuangan v1 adalah tagihan, pembayaran, tabungan, dan laporan operasional — bukan akuntansi standar PSAK |
| **Migrasi penuh ERP ke arsitektur API-only** | Diakui sebagai target (Roadmap), bukan kebutuhan rilis fitur saat ini |

---

## 5. Persona Pengguna

| Persona | Peran | Kebutuhan utama | Aplikasi |
|---|---|---|---|
| **Super Admin / Pengurus Yayasan** | Kontrol penuh, konfigurasi, laporan strategis | Dashboard, manajemen user & hak akses, laporan | ERP |
| **Admin TU / Sekretariat** | Operasional harian data santri & PPDB | CRUD santri, kelola pendaftar, dokumen, surat | ERP |
| **Bendahara / Bagian Keuangan** | Tagihan, verifikasi pembayaran, tabungan, laporan | Generate tagihan, verifikasi bukti bayar, broadcast penagihan, laporan keuangan | ERP |
| **Panitia PPDB** | Mengelola seleksi penerimaan santri baru | Review pendaftar, tahap seleksi, ujian masuk, hasil | ERP |
| **Pengasuh / Ustadz / Wali Kelas** | Mencatat hafalan, nilai, kehadiran, perilaku | Input tahfidz, nilai, presensi, konseling, kesehatan | ERP |
| **Calon Santri / Orang Tua Pendaftar** | Mendaftar & memantau status seleksi | Formulir daftar online, unggah dokumen, cek hasil | Website + portal pendaftar |
| **Wali Santri (aktif)** | Memantau & membayar | Lihat tagihan, bayar, saldo tabungan, hafalan, kehadiran, izin, info | PWA App |
| **Publik / Pengunjung** | Mengenal pesantren | Profil, program, galeri, pengumuman, prestasi | Website |

---

## 6. Arsitektur Sistem & Tech Stack (Kondisi Saat Ini)

```
                        ┌──────────────────────────┐
   Publik / Wali  ───▶  │  www.asy-syifaa.com (PHP) │  Landing + Form PPDB + CMS
                        └────────────┬─────────────┘
                                     │ REST
   Wali Santri    ───▶  ┌────────────▼─────────────┐
   (PWA Vue 3)          │  app.asy-syifaa.com (Vue) │  Dashboard wali (offline-first)
                        └────────────┬─────────────┘
                                     │ REST
   Admin/TU/Guru  ───▶  ┌────────────▼──────────────────────────┐
                        │  erp.asy-syifaa.com                    │  ◀── SYSTEM-OF-RECORD
                        │  Laravel 13 + Filament 5 (PHP 8.4)     │      saat ini
                        │  + REST API + Spatie RBAC              │
                        └────────────┬──────────────────────────┘
                                     │
                        ┌────────────▼─────────────┐
                        │  PostgreSQL 16  +  Redis 7 │
                        └───────────────────────────┘

   (Target) api.asy-syifaa.com (Node/Express/Prisma) = "DataMaster" gateway terpusat
   WhatsApp Gateway: n8n + WAHA   |   Infra: Hostinger VPS + Traefik (auto-SSL) + Docker + GitHub Actions CI/CD
```

| Komponen | Stack | Domain | Peran | Status |
|---|---|---|---|---|
| **asy-syifaa-erp** | Laravel 13 + Filament 5 + PHP 8.4 | erp.asy-syifaa.com | Admin panel + API + **system-of-record** | ✅ Aktif, kaya fitur (40+ model, 35 migrasi, RBAC Spatie) |
| **asy-syifaa-api** | Node.js + Express + Prisma | api.asy-syifaa.com | "DataMaster" — gateway CRUD terpusat | 🟡 Minimal (baru model User, Kamar, Santri) — **target**, belum jadi sumber utama |
| **asy-syifaa-website** | PHP native (Hostinger) | www.asy-syifaa.com | Etalase publik + formulir PPDB + konten CMS | ✅ Aktif |
| **asy-syifaa-app** | Vue 3 + Vite (PWA) | app.asy-syifaa.com | Aplikasi wali (offline-first) | 🟡 Kerangka lengkap, sebagian view masih mock data |
| **Infrastruktur** | Hostinger VPS · Traefik · PostgreSQL 16 · Redis 7 · Docker Compose · GitHub Actions | — | Hosting, SSL, CI/CD | ✅ Aktif |

> **Realitas arsitektur penting.** Dokumen `DATABASE-ARCHITECTURE.md` menetapkan target "semua CRUD lewat DataMaster API, tidak ada akses DB langsung". Saat ini ERP Laravel **masih** memegang basis data domain lengkap dan menjadi sumber kebenaran, sementara DataMaster API baru menampung 3 entitas. Migrasi ke arsitektur API-only adalah **Fase 2 roadmap**, bukan kondisi sekarang. PRD ini memperlakukan ERP sebagai system-of-record untuk semua kebutuhan fungsional v1.

---

## 7. Lingkup Produk per Aplikasi

- **ERP (Filament):** SPMB/PPDB, Kepesantrenan, Keuangan, CMS, Notifikasi, Master Wilayah, User Management, Pengaturan, Wali Portal, Dashboard & Widget statistik.
- **Website Publik:** profil pesantren (abuya, kurikulum, struktur, visi-misi, fasilitas, ekskul), kegiatan, galeri, info (artikel/pengumuman/prestasi), direktori (santri/alumni/pengajar), **alur & formulir pendaftaran**, cek **hasil seleksi**, **e-raport**, Asy-Syifaa TV.
- **PWA Wali:** Beranda/Dashboard, Profil santri, **Keuangan** (tagihan, bayar, tabungan, transaksi), **Belajar** (hafalan, absensi, jadwal, prestasi, akademik), **Kegiatan** (izin, kesehatan, kunjungan, konseling, presensi), Info, Marketplace.

---

## 8. Modul & Requirements

Prioritas memakai kerangka **MoSCoW**: **P0 (Must-have)** = produk tidak layak tanpa ini · **P1 (Should/Nice-to-have)** = fast-follow · **P2 (Future)** = di luar lingkup v1 tetapi diperhitungkan dalam desain.

---

### 8.1 Modul SPMB/PPDB — Penerimaan Santri Baru

**Status saat ini:** ✅ Alur lengkap sudah ada — `PpdbRegistration`, `PpdbDocument`, `PpdbSelectionStage`, fondasi ujian, bukti bayar, field wali & profil komprehensif; halaman Pendaftar, Dokumen Saya, Profil Saya, Tagihan Saya, Hasil Seleksi, Ujian Masuk.

**User Stories**
- Sebagai **calon orang tua santri**, saya ingin mendaftarkan anak lewat formulir online dari rumah, agar tidak perlu datang ke pesantren hanya untuk mengisi berkas.
- Sebagai **pendaftar**, saya ingin mengunggah dokumen persyaratan (KK, akta, ijazah, foto) dan melihat status verifikasinya, agar tahu apa yang masih kurang.
- Sebagai **panitia PPDB**, saya ingin memproses pendaftar melalui tahapan seleksi (verifikasi berkas → ujian → pengumuman), agar penerimaan terstandar dan terlacak.
- Sebagai **calon santri/ortu**, saya ingin mengecek hasil seleksi secara mandiri lewat website, agar tidak perlu menunggu pengumuman manual.
- Sebagai **panitia**, saya ingin pendaftar yang lolos otomatis menjadi tagihan registrasi ulang & (kelak) data santri, agar tidak input ulang.

**Requirements**

| ID | Prioritas | Kebutuhan | Kriteria Penerimaan (ringkas) |
|---|---|---|---|
| PPDB-1 | **P0** | Formulir pendaftaran online dari website | Given calon ortu di halaman daftar, When mengisi data + memilih wilayah (prov→desa) + submit, Then tercipta record pendaftar berstatus "Baru" & nomor pendaftaran terbit |
| PPDB-2 | **P0** | Unggah & verifikasi dokumen persyaratan | Dokumen ter-upload tersimpan; admin bisa set status verified/ditolak dengan catatan; pendaftar melihat status |
| PPDB-3 | **P0** | Manajemen tahapan seleksi (multi-stage) | Setiap pendaftar bisa dipindah antar tahap; status & hasil tiap tahap tercatat & ter-audit |
| PPDB-4 | **P0** | Tagihan biaya pendaftaran/registrasi ulang terhubung ke pendaftar | Pendaftar lolos memunculkan invoice; pembayaran tercatat menutup tagihan |
| PPDB-5 | **P0** | Pengumuman hasil seleksi (cek mandiri) | Pendaftar memasukkan nomor/identitas → melihat status lulus/tidak; data tidak bocor ke pihak lain |
| PPDB-6 | **P1** | Ujian masuk terjadwal & penilaian | Jadwal ujian, input nilai, hasil terhubung ke keputusan seleksi |
| PPDB-7 | **P1** | Notifikasi WA otomatis di tiap perubahan status | Saat status berubah, pesan template terkirim ke nomor wali |
| PPDB-8 | **P2** | Konversi otomatis pendaftar lulus → master santri | Data pendaftar tersalin menjadi `Student` tanpa input ulang (butuh pemetaan field final) |

---

### 8.2 Modul Kepesantrenan — Data Santri & Akademik

**Status saat ini:** ✅ Model domain sangat kaya — `Student`, `Alumni`, `Kamar`, kehadiran (`StudentAttendance`), nilai (`StudentGrade`), prestasi (`StudentAchievement`), tahfidz (`TahfidzProgress`, `TahfidzRecord`, `WaqofLog`), kesehatan (`HealthRecord`), konseling (`CounselingSession`), perizinan (`StudentPermission`), kegiatan (`Activity`, `ActivityAttendance`), jadwal (`ClassSchedule`), periode akademik (`AcademicPeriod`), kunjungan ortu (`ParentVisit`), serta import/export santri.

**User Stories**
- Sebagai **admin TU**, saya ingin mengelola data master santri (identitas, kamar, status aktif/alumni) di satu tempat, agar data konsisten lintas modul.
- Sebagai **ustadz tahfidz**, saya ingin mencatat progres hafalan (juz/surah/ayat, ziyadah & muraja'ah) tiap santri, agar perkembangan terukur dan bisa dibagikan ke wali.
- Sebagai **wali kelas**, saya ingin menginput nilai & kehadiran santri per periode akademik, agar bisa menghasilkan rapor.
- Sebagai **bagian keamanan/pengasuh**, saya ingin mencatat perizinan keluar & kembalinya santri, agar terdokumentasi.
- Sebagai **admin**, saya ingin impor data santri massal dari file, agar migrasi data awal cepat.

**Requirements**

| ID | Prioritas | Kebutuhan | Kriteria Penerimaan (ringkas) |
|---|---|---|---|
| KEP-1 | **P0** | CRUD master santri + status (aktif/alumni) + penempatan kamar | Santri dapat dibuat/diubah; perpindahan ke Alumni mempertahankan riwayat; kamar punya kapasitas |
| KEP-2 | **P0** | Pencatatan hafalan/tahfidz per santri | Catatan tahfidz tersimpan dengan tanggal, capaian, & penilai; akumulasi progres terlihat |
| KEP-3 | **P0** | Kehadiran & perizinan santri | Presensi & izin tercatat dengan jenis, tanggal, status; bisa direkap per santri/periode |
| KEP-4 | **P1** | Nilai akademik & rapor per periode | Nilai per mapel/periode tersimpan; dapat dicetak menjadi rapor berkop surat |
| KEP-5 | **P1** | Catatan kesehatan & konseling | Riwayat kunjungan kesehatan & sesi konseling tercatat dengan kerahasiaan akses sesuai peran |
| KEP-6 | **P1** | Kegiatan & presensi kegiatan | Kegiatan terjadwal, peserta & kehadiran tercatat |
| KEP-7 | **P1** | Kunjungan orang tua (jadwal & log) | Kunjungan tercatat; (kelak) wali bisa mengajukan jadwal dari App |
| KEP-8 | **P0** | Import/Export data santri | File valid terimpor dengan laporan baris gagal; export menghasilkan berkas terstruktur |

---

### 8.3 Modul Keuangan — Tagihan, Pembayaran, Tabungan

**Status saat ini:** ✅ Lengkap — `BillingType`, `BillingPeriod`, **`HijriBillingPeriod`** (periode kalender Hijriah), `Invoice`/`InvoiceItem`, `Payment`, `PaymentProof` (bukti bayar), `PaymentMethod`, tabungan santri (`StudentSaving`/`SavingsTransaction` + alokasi); halaman Bayar Tagihan, **Broadcast Penagihan**, **Laporan Keuangan**, widget statistik keuangan.

**User Stories**
- Sebagai **bendahara**, saya ingin men-generate tagihan SPP massal per periode (Masehi/Hijriah) ke seluruh/segmen santri, agar tidak membuat satu per satu.
- Sebagai **bendahara**, saya ingin mem-broadcast penagihan via WhatsApp ke wali yang menunggak, agar pembayaran lebih tepat waktu.
- Sebagai **wali**, saya ingin melihat rincian tagihan & membayar dengan mengunggah bukti transfer, agar tidak perlu konfirmasi manual via chat.
- Sebagai **bendahara**, saya ingin memverifikasi bukti bayar lalu tagihan otomatis lunas, agar status keuangan akurat.
- Sebagai **wali**, saya ingin melihat saldo & mutasi tabungan/uang saku anak, agar transparan.
- Sebagai **manajemen**, saya ingin laporan keuangan (pemasukan, tunggakan, per jenis tagihan) per periode.

**Requirements**

| ID | Prioritas | Kebutuhan | Kriteria Penerimaan (ringkas) |
|---|---|---|---|
| KEU-1 | **P0** | Master jenis tagihan & periode (Masehi & Hijriah) | Jenis tagihan & periode dapat dikonfigurasi; mendukung penanggalan Hijriah |
| KEU-2 | **P0** | Generate tagihan massal & per santri | Tagihan terbit dengan item, jumlah, jatuh tempo; tidak ada duplikasi untuk periode sama |
| KEU-3 | **P0** | Pembayaran + unggah & verifikasi bukti bayar | Wali unggah bukti → bendahara verifikasi → invoice berubah lunas/parsial; saldo terupdate |
| KEU-4 | **P0** | Broadcast penagihan ke wali penunggak | Sistem menyeleksi tagihan jatuh tempo & mengirim pesan via WA gateway, hasil tercatat di log |
| KEU-5 | **P0** | Tabungan santri (setor, tarik, alokasi, mutasi) | Setiap transaksi tabungan mengubah saldo & tercatat; alokasi ke tagihan didukung |
| KEU-6 | **P1** | Laporan keuangan per periode & jenis | Laporan menampilkan pemasukan, tunggakan, breakdown; dapat diekspor/dicetak |
| KEU-7 | **P1** | Metode pembayaran terkonfigurasi (rekening tujuan) | Daftar rekening/metode tampil ke wali saat membayar |
| KEU-8 | **P2** | Integrasi payment gateway (VA/QRIS) otomatis | Pembayaran terkonfirmasi tanpa verifikasi manual (inisiatif terpisah) |

---

### 8.4 Aplikasi Wali (PWA) — Keterlibatan Orang Tua

**Status saat ini:** 🟡 Kerangka navigasi & routing lengkap (Dashboard, Profil, Keuangan, Belajar, Kegiatan, Info, Marketplace), auth store, offline-first (PWA), pemilih wilayah, waktu sholat. Sebagian view masih memakai **mock data** → kebutuhan utama: menyambungkan ke API/ERP nyata.

**User Stories**
- Sebagai **wali**, saya ingin login aman dan melihat ringkasan kondisi anak (tagihan jatuh tempo, hafalan terbaru, kehadiran) di satu dashboard.
- Sebagai **wali**, saya ingin membayar tagihan & melihat riwayat pembayaran langsung dari HP.
- Sebagai **wali**, saya ingin memantau hafalan, nilai, jadwal, prestasi, dan kehadiran anak.
- Sebagai **wali**, saya ingin mengajukan/melihat izin, info kesehatan, konseling, dan mengatur jadwal kunjungan.
- Sebagai **wali**, saya ingin menerima info & pengumuman resmi pesantren, agar tidak bergantung pada grup WA.

**Requirements**

| ID | Prioritas | Kebutuhan | Kriteria Penerimaan (ringkas) |
|---|---|---|---|
| APP-1 | **P0** | Autentikasi wali + sesi aman | Login dengan kredensial wali; rute terproteksi mengalihkan ke /login bila belum auth |
| APP-2 | **P0** | Dashboard ringkasan anak (data nyata, bukan mock) | Menampilkan tagihan, hafalan, kehadiran terkini dari API; mendukung >1 anak per wali |
| APP-3 | **P0** | Hub Keuangan: tagihan, bayar (unggah bukti), tabungan, transaksi | Data tagihan & saldo sinkron dengan ERP; unggah bukti memicu verifikasi di ERP |
| APP-4 | **P1** | Hub Belajar: hafalan, absensi, jadwal, prestasi, akademik | Data per santri tampil sesuai periode aktif |
| APP-5 | **P1** | Hub Kegiatan: izin, kesehatan, kunjungan, konseling, presensi | Wali dapat melihat & (untuk izin/kunjungan) mengajukan |
| APP-6 | **P1** | Info & pengumuman dari CMS | Konten dari ERP/CMS tampil terurut waktu |
| APP-7 | **P1** | PWA installable + offline-first (cache & sync) | App dapat dipasang ke home screen; data terakhir tampil saat offline |
| APP-8 | **P2** | Push notification & Marketplace (koperasi/kebutuhan santri) | Di luar lingkup v1; disiapkan di arsitektur |

---

### 8.5 Website Publik & CMS

**Status saat ini:** ✅ Banyak halaman live — profil (abuya, kurikulum, struktur organisasi, visi-misi, fasilitas, ekskul, guru), kegiatan (harian/mingguan), galeri, info (artikel/pengumuman/prestasi), direktori (santri/alumni/pengajar), alur pendaftaran, hasil seleksi, e-raport, pencarian, Asy-Syifaa TV. CMS di ERP: `CmsPost`, `CmsCategory`, `CmsTag`, `CmsGallery`.

**User Stories**
- Sebagai **pengunjung publik**, saya ingin mengenal profil, program, dan prestasi pesantren agar yakin mendaftarkan anak.
- Sebagai **admin konten**, saya ingin mengelola artikel, pengumuman, dan galeri dari ERP agar website selalu terbarui tanpa ngoding.
- Sebagai **wali/alumni**, saya ingin mengecek e-raport / direktori dari website.

**Requirements**

| ID | Prioritas | Kebutuhan | Kriteria Penerimaan |
|---|---|---|---|
| WEB-1 | **P0** | Halaman profil & informasi pesantren | Halaman tampil cepat, responsif, SEO-friendly |
| WEB-2 | **P0** | Formulir PPDB online terhubung ke ERP (lihat 8.1) | Submit membuat pendaftar di system-of-record |
| WEB-3 | **P0** | Konten dikelola via CMS di ERP | Post/galeri yang dipublikasikan tampil di website |
| WEB-4 | **P1** | Cek hasil seleksi & e-raport mandiri | Hanya pemilik data yang dapat melihat (kontrol akses sederhana) |
| WEB-5 | **P1** | Direktori santri/alumni/pengajar | Data tampil sesuai izin publikasi |

---

### 8.6 Notifikasi & WhatsApp Gateway

**Status saat ini:** ✅ Fondasi ada — `BroadcastJob`, `NotificationTemplate`, `NotificationLog`, `WebhookLog`, halaman Broadcast Notifikasi & Broadcast Penagihan; gateway via **n8n + WAHA**.

**Requirements**

| ID | Prioritas | Kebutuhan | Kriteria Penerimaan |
|---|---|---|---|
| NOT-1 | **P0** | Template pesan yang dapat dikelola | Template dengan variabel (nama, jumlah, jatuh tempo) tersimpan & dipakai broadcast |
| NOT-2 | **P0** | Broadcast job + log pengiriman | Job menargetkan segmen; tiap kiriman tercatat (terkirim/gagal) di log |
| NOT-3 | **P1** | Webhook status pengiriman dari gateway | Status balik (delivered/read/failed) tercatat di WebhookLog |
| NOT-4 | **P1** | Notifikasi event otomatis (tagihan terbit, status PPDB, bukti diverifikasi) | Event memicu pesan tanpa intervensi manual |

---

### 8.7 Master Wilayah (Data Referensi)

**Status saat ini:** ✅ `Province`, `City`, `District`, `Village` (standar Kepmendagri 2025) + kode pos; komponen pemilih wilayah dipakai di ERP & PWA & website.

| ID | Prioritas | Kebutuhan | Kriteria Penerimaan |
|---|---|---|---|
| WIL-1 | **P0** | Data wilayah Indonesia terstandar & dapat di-query bertingkat | Pilih provinsi → kota → kecamatan → desa berfungsi di semua aplikasi |
| WIL-2 | **P1** | Endpoint wilayah terekspos via API untuk konsumsi lintas app | Website & PWA mengambil wilayah dari sumber yang sama |

---

### 8.8 User Management, Peran & Hak Akses (RBAC)

**Status saat ini:** ✅ `User`, `ErpAccount`, **Spatie Permission** (tabel role & permission), login Filament kustom, ganti password, detail akun.

**User Stories**
- Sebagai **super admin**, saya ingin memberi peran berbeda (TU, keuangan, panitia PPDB, ustadz, wali) dengan hak akses sesuai, agar data sensitif terlindungi.
- Sebagai **wali**, saya ingin akun yang hanya bisa melihat data anak saya sendiri.

| ID | Prioritas | Kebutuhan | Kriteria Penerimaan |
|---|---|---|---|
| USR-1 | **P0** | Peran & izin granular per modul | Akses menu/aksi mengikuti peran; user tanpa izin tidak melihat modul |
| USR-2 | **P0** | Isolasi data wali (hanya anak sendiri) | Wali A tidak dapat mengakses data santri wali B |
| USR-3 | **P0** | Login aman, ganti password, kelola akun | Password ter-hash; reset & ganti password berfungsi |
| USR-4 | **P1** | Audit aktivitas pengguna penting | Aksi sensitif (verifikasi bayar, ubah nilai) tercatat |

---

### 8.9 Pengaturan & Operasional

**Status saat ini:** ✅ `AppSetting`, kop surat (`LetterHeader`), template surat (`SuratTemplate`), metode pembayaran, **Backup/Restore**, User Manual; widget dashboard (statistik SPMB & Keuangan).

| ID | Prioritas | Kebutuhan | Kriteria Penerimaan |
|---|---|---|---|
| OPS-1 | **P0** | Pengaturan aplikasi & identitas lembaga (kop surat) | Surat/rapor tercetak memakai kop & identitas terkonfigurasi |
| OPS-2 | **P0** | Backup & restore basis data | Backup dapat dibuat & dipulihkan; jadwal backup terdokumentasi |
| OPS-3 | **P1** | Dashboard statistik (SPMB, keuangan) | Widget menampilkan angka real-time akurat |
| OPS-4 | **P1** | User manual / bantuan in-app | Panduan dapat diakses dari panel |

---

## 9. Metrik Keberhasilan

### 9.1 Leading Indicators (cepat berubah — harian/mingguan)
| Metrik | Definisi | Target awal |
|---|---|---|
| Adopsi admin | % data santri aktif dikelola di platform | ≥ 95% dalam 1 semester |
| Pendaftaran online | % pendaftar via formulir online vs total | ≥ 70% pada PPDB berikutnya |
| Tagihan terkirim digital | % tagihan dengan notifikasi WA terkirim | ≥ 90% per siklus |
| Pembayaran via App | % pembayaran dengan bukti diunggah lewat App/ERP | ≥ 50% dalam 3 bulan |
| Wali aktif (WAU/MAU) | Wali login App ≥1×/bulan | ≥ 60% dalam 3 bulan |
| Tingkat verifikasi bukti bayar | % bukti diverifikasi < 24 jam | ≥ 80% |

### 9.2 Lagging Indicators (berubah perlahan — bulanan/semester)
| Metrik | Definisi | Target |
|---|---|---|
| Penurunan tunggakan | Penurunan rasio tagihan tertunggak | ≥ 30% dalam 2 siklus |
| Efisiensi administrasi | Pengurangan jam kerja rekap & laporan | ≥ 50% |
| Retensi wali aktif | Wali tetap aktif setelah 3 bulan | ≥ 50% |
| Kepuasan wali (survei) | Skor kepuasan transparansi & kemudahan | ≥ 4/5 |
| Beban support menurun | Penurunan pertanyaan tagihan via chat | ≥ 40% |

### 9.3 Metode Pengukuran
- Sumber: query basis data ERP (PostgreSQL), `NotificationLog`/`WebhookLog`, analitik App (login/aktivitas).
- Kadens evaluasi: **mingguan** (leading) saat onboarding, **bulanan & per-semester** (lagging).

---

## 10. Roadmap & Fase

| Fase | Fokus | Status |
|---|---|---|
| **Fase 0 — Fondasi** | Monorepo, infra VPS+Docker+Traefik, CI/CD, RBAC, Master Wilayah | ✅ Selesai |
| **Fase 1 — Operasional Inti** | SPMB/PPDB, Kepesantrenan, Keuangan, CMS, Notifikasi di ERP | ✅ Sebagian besar berjalan; pemantapan & data nyata |
| **Fase 2 — Keterlibatan Wali** | PWA tersambung data nyata (hapus mock), pembayaran via App, notifikasi event | 🔜 Prioritas berikutnya |
| **Fase 3 — Konsolidasi Arsitektur** | Migrasi CRUD domain ke DataMaster API (single source of truth), versioning schema | 🔜 Target |
| **Fase 4 — Otomasi & Skala** | Payment gateway (VA/QRIS), push notification, read-replica, observability | 🔭 Future |

### Pertimbangan Waktu (Timeline)
- **Kalender PPDB** mengikat: alur pendaftaran online harus stabil sebelum musim PPDB tahun ajaran berikutnya.
- **Siklus penagihan bulanan/Hijriah**: fitur broadcast penagihan & pembayaran via App sebaiknya live sebelum awal periode penagihan.
- Dependensi: PWA (Fase 2) bergantung pada kestabilan API tagihan/pembayaran ERP (Fase 1).

---

## 11. Risiko & Mitigasi

| Risiko | Dampak | Mitigasi |
|---|---|---|
| **Dualisme system-of-record** (ERP Laravel vs DataMaster API minimal) | Kebingungan sumber data, risiko data ganda saat migrasi | Tetapkan ERP sebagai sumber tunggal v1; migrasi ke API bertahap & terdokumentasi (Fase 3); registry pengecualian direct-DB |
| **PWA masih mock data** | Wali melihat data tidak akurat → hilang kepercayaan | Jangan rilis luas sebelum hub Keuangan & Dashboard tersambung data nyata (APP-2, APP-3) |
| **Ketergantungan WA gateway (WAHA non-resmi)** | Pemblokiran nomor, pesan gagal | Pantau `WebhookLog`; siapkan fallback (multi-nomor / WA Business API resmi) sebagai P2 |
| **Pembayaran manual (unggah bukti)** | Verifikasi lambat, human error | SLA verifikasi <24 jam; siapkan payment gateway (Fase 4) |
| **Keamanan data santri (anak di bawah umur)** | Kebocoran data sensitif | RBAC ketat (USR-1/2), isolasi data wali, audit (USR-4), backup terenkripsi |
| **Kapasitas VPS tunggal** | Downtime saat beban PPDB/penagihan puncak | Monitoring sumber daya, backup harian, rencana scaling (read-replica) |
| **Adopsi pengguna rendah** (admin & wali gaptek) | ROI tidak tercapai | User manual in-app, pendampingan onboarding, UX sederhana berbahasa Indonesia |

---

## 12. Open Questions

> Pertanyaan yang **harus** dijawab pemilik produk; ditandai pihak penjawab & apakah memblokir.

| # | Pertanyaan | Penjawab | Blocking? |
|---|---|---|---|
| OQ-1 | Berapa target angka konkret tiap KPI (jumlah santri, target tunggakan, target adopsi wali)? | Manajemen/Yayasan | Non-blocking |
| OQ-2 | Kapan tanggal go-live yang mengikat (musim PPDB & awal periode penagihan)? | Manajemen | **Blocking** untuk prioritas Fase 2 |
| OQ-3 | Apakah pembayaran v1 cukup transfer manual + unggah bukti, atau payment gateway diprioritaskan lebih awal? | Keuangan | Blocking untuk lingkup Keuangan |
| OQ-4 | WA gateway: tetap pakai WAHA atau pindah ke WhatsApp Business API resmi (biaya & kepatuhan)? | Manajemen/IT | Non-blocking |
| OQ-5 | Kebijakan privasi & retensi data santri/wali (anak di bawah umur) — acuan regulasi (UU PDP)? | Yayasan/Legal | **Blocking** untuk rilis publik App |
| OQ-6 | Apakah konversi otomatis pendaftar lolos → master santri (PPDB-8) masuk v1 atau Fase 3? | Manajemen | Non-blocking |
| OQ-7 | Prioritas migrasi ke DataMaster API (Fase 3) vs penambahan fitur fungsional — mana lebih dulu? | Tim Arsitektur | Non-blocking |

---

## 13. Lampiran

### 13.1 Ringkasan Entitas Data Utama (dari ERP, system-of-record)
- **Identitas/Akses:** `User`, `ErpAccount`, Roles & Permissions (Spatie)
- **SPMB:** `PpdbRegistration`, `PpdbDocument`, `PpdbSelectionStage`, tabel ujian, `PaymentProof`
- **Kepesantrenan:** `Student`, `Alumni`, `Kamar`, `StudentAttendance`, `StudentGrade`, `StudentAchievement`, `TahfidzProgress`, `TahfidzRecord`, `WaqofLog`, `HealthRecord`, `CounselingSession`, `StudentPermission`, `Activity`, `ActivityAttendance`, `ClassSchedule`, `AcademicPeriod`, `ParentVisit`
- **Keuangan:** `BillingType`, `BillingPeriod`, `HijriBillingPeriod`, `Invoice`, `InvoiceItem`, `Payment`, `PaymentProof`, `PaymentMethod`, `StudentSaving`, `SavingsTransaction`
- **CMS:** `CmsPost`, `CmsCategory`, `CmsTag`, `CmsGallery`, `CmsGalleryItem`
- **Notifikasi:** `BroadcastJob`, `NotificationTemplate`, `NotificationLog`, `WebhookLog`
- **Pengaturan:** `AppSetting`, `LetterHeader`, `SuratTemplate`
- **Master Wilayah:** `Province`, `City`, `District`, `Village`

### 13.2 Glosarium
| Istilah | Arti |
|---|---|
| **SPMB/PPDB** | Penerimaan Peserta Didik / Santri Baru |
| **Santri** | Murid/peserta didik pesantren |
| **Wali** | Orang tua/wali santri |
| **Tahfidz** | Program hafalan Al-Qur'an |
| **Ziyadah / Muraja'ah** | Hafalan baru / mengulang hafalan |
| **Waqof** | Tanda berhenti dalam bacaan Al-Qur'an |
| **Tagihan/SPP** | Iuran/biaya pendidikan |
| **Periode Hijriah** | Periode penagihan berbasis kalender Islam |
| **ERP** | Sistem perencanaan sumber daya (di sini: admin panel Filament) |
| **PWA** | Progressive Web App (aplikasi web installable) |
| **RBAC** | Role-Based Access Control |

---

*Dokumen ini disusun berdasarkan audit kode aktual repositori (ERP Laravel/Filament, DataMaster API, Website PHP, PWA Vue) dan dokumen arsitektur basis data per 2026-06-02. Perbarui seiring evolusi produk.*
