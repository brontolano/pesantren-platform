# Peta Ekosistem Digital Pesantren — Integrasi Menyeluruh (2026-06-11)

Sintesis dari: modul yang sudah LIVE, 3 blueprint (PWA Guru, Akademik+SDM, Stasiun Pendaftaran), riset organisasi pesantren Indonesia, dan dokumen Baitul Maal ALBA. Dokumen ini BUKAN backlog — ini peta agar setiap modul baru tahu posisinya, dan agar tidak membangun yang tidak menjawab tiga tujuan pondok.

## Tiga tujuan yang jadi tolok ukur SEMUA modul

1. **Bisnis sehat** — kas pondok lancar, tagihan tertagih, unit usaha untung, bantuan negara cair, biaya terkendali → pondok mandiri.
2. **Mutu santri terjaga** — standar dewan masyayikh terukur (UU 18/2019 mewajibkan penjaminan mutu internal): hafalan, kitab, akhlak, ubudiyah, akademik — terlihat dini saat menurun.
3. **Maslahat keluarga besar** — santri, wali, ustadz & keluarganya, alumni, masyarakat sekitar merasakan manfaat: transparansi, kesejahteraan, silaturahmi, layanan.

Aturan praktis: modul yang tidak mengena minimal satu tujuan dengan jelas = TIDAK dibangun.

## Lapisan ekosistem

### Lapisan 0 — Fondasi (LIVE, jangan diutak-atik)
Multi-tenant DB-per-tenant · Ledger 5-pagar + dimensi Kehalalan & SumberDana (negara/komersial/sosial, wakaf, dana kebajikan — INI fondasi Baitul Maal yang sering dilupakan sudah ada) · ModuleRegistry on/off per tenant · RBAC Spatie · Notifikasi WA · AI Khadim (Fase A tuntas) · Tema panel · Dokumentasi publik.

### Lapisan 1 — Inti operasional (LIVE / ter-blueprint)
Kesantrian · PPDB+Ujian · Akademik+Tahfidz (→ blueprint Akademik) · Keuangan (SPP, tabungan, kas, pajak, payroll → blueprint SDM) · Kegiatan/Izin/Kunjungan · Kesehatan · Website+Galeri · 5 aplikasi: panel ERP, PWA Guru (blueprint), PWA Keuangan (live), PWA Wali (live), PWA SPMB (live), App Santri (kerangka).

### Lapisan 2 — Integrasi yang MELENGKAPI (kandidat berikutnya, urut nilai)

**2a. Dasbor Mutu Santri terpadu** — tujuan #2, nilai tertinggi, effort kecil-sedang.
Semua datanya SUDAH/AKAN ada (nilai, hafalan, capaian kitab, poin disiplin, presensi sholat/kelas, kesehatan) tapi tercerai di 6 modul. Satu layar per santri + per kelas + per lembaga: tren naik/turun, lampu kuning otomatis (hafalan macet 3 pekan, alpa naik, poin pelanggaran mendekat ambang) → notifikasi mustahiq & wali. Ini WUJUD digital "dewan masyayikh menjaga mutu" dan alat jual terkuat ke pesantren lain. Teknis: view agregat + widget, bukan modul data baru.

**2b. Unit usaha & koperasi (POS terpadu)** — tujuan #1 dan #3.
LoketKas + tabungan santri + kartu NFC/QR (blueprint Stasiun) sudah menyediakan tulangnya. Lengkapi jadi: kantin/koperasi bayar via saldo tabungan (UID+PIN — aturan uang yang sudah baku), laundry, dapur. Wali top-up dari PWA Wali (sudah live) → uang saku tanpa uang tunai = bocor hilang, riba kantin bon hilang, wali tenang. Posting otomatis ke Ledger per unit (DivisiUnit sudah ada). Stok/inventori sederhana menyusul, JANGAN mulai dari inventori.

**2c. Baitul Maal digital: zakat, infaq, wakaf, dana kebajikan** — tujuan #1 dan #3, diferensiasi syar'i terkuat.
SumberDana ber-dimensi kehalalan + is_wakaf + dana_kebajikan SUDAH ada di Ledger — yang belum ada pintunya: halaman donasi publik di website tenant (transfer + konfirmasi, kelak payment gateway), pencatatan muzakki/munfiq, laporan penyaluran transparan (publik per program), beasiswa santri yatim/dhuafa dari dana kebajikan → potong otomatis tagihan SPP santri penerima (kaitan langsung ke piutang yang sudah ada). Maslahat paling terasa keluarga besar.

**2d. Sarpras & aset** — tujuan #1. Modul Aset sudah ada di Keuangan; lengkapi: peminjaman barang, jadwal pemeliharaan, kerusakan-lapor-via-foto (PWA), kamar/gedung tertaut asrama. Kecil, jangan over-engineer.

**2e. Perpustakaan & kitab digital** — tujuan #2. Sirkulasi sederhana (master kitab sudah lahir di blueprint PWA Guru — pakai ulang!), katalog, peminjaman via QR kartu santri. Nilai tambah khas: tautan kitab→kurikulum→capaian sorogan.

**2f. Dapur & konsumsi** — tujuan #1 di pesantren besar (biaya terbesar setelah gaji): perencanaan menu × jumlah santri mukim (data sudah ada) → belanja terencana (PWA Keuangan sudah bisa catat belanja) → biaya makan per santri per bulan terlihat di Ledger. Mulai dari LAPORAN, bukan modul stok bahan.

### Lapisan 3 — Jaringan & pertumbuhan (setelah Lapisan 2 matang)

**3a. Alumni & ikatan keluarga** — tujuan #3 jangka panjang. Tabel alumni sudah ada; lengkapi: portal alumni (data diri, profesi, domisili), donasi alumni (→ Baitul Maal), bursa kerja/jodoh jejaring pondok (banyak pesantren menjalankan ini manual), guru pengabdian direkrut dari sini (tautan ke SDM S-2). Alumni = donor + perekrut santri baru + penjaga nama baik.

**3b. Humas & dakwah digital** — tujuan #3 + corong PPDB: website sudah ada; tambah arsip kajian (audio/video tautan YouTube), jadwal kajian umum untuk masyarakat, pendaftaran kajian pasaran Ramadhan untuk santri luar (modul Kegiatan sudah mendukung peserta luar — blueprint Akademik A-5).

**3c. AI Khadim Fase B+** — lintas semua: tanya-jawab data internal ("siapa santri yang hafalannya macet bulan ini?"), draft surat/pengumuman, analisis tren mutu — SETELAH Gate A→B owner.

**3d. Antar-pesantren (jangka jauh)** — multi-tenant jadi jejaring: muqayadah/lomba antar pondok, benchmark mutu anonim, marketplace unit usaha antar pesantren. Jangan sentuh sebelum ≥5 tenant aktif.

### Yang sengaja TIDAK dibangun (dan kenapa)
- LMS/e-learning penuh — pesantren = tatap muka; PRD sudah menolak, tetap.
- Integrasi API EMIS/SIMPATIKA — tak ada API publik stabil; cukup ekspor Excel + rekap kesiapan.
- CCTV/IoT gerbang — hardware-heavy; QR/NFC kartu santri sudah menjawab 90% kebutuhan presensi-keamanan.
- Akuntansi konsolidasi multi-yayasan — sampai ada klien grup yayasan nyata.
- Mobile native — PWA+TWA sudah cukup; jangan pelihara 2 codebase.

## Peta integrasi (siapa memberi data ke siapa)

```
PPDB/Stasiun ──santri+foto+QR/NFC──▶ Kesantrian ──▶ semuanya
PWA Guru ──jurnal+presensi+setoran──▶ Akademik/Tahfidz ──▶ DASBOR MUTU (2a)
                └──sesi terlaksana──▶ Khidmah ──▶ Payroll/SDM ──▶ Ledger
Disiplin+Kesehatan+Presensi ────────────────────▶ DASBOR MUTU ──▶ PWA Wali (lampu kuning)
Tabungan ◀──top-up PWA Wali        Tabungan ──saldo──▶ POS kantin/laundry (2b) ──▶ Ledger per unit
Baitul Maal (2c) ──beasiswa──▶ potong tagihan SPP      Donasi publik/alumni ──▶ Baitul Maal
Alumni (3a) ──▶ guru pengabdian (SDM) + donasi + PPDB referral
Semua transaksi uang ──SATU PINTU──▶ JurnalService (idempoten, kehalalan, divisi, sumber dana)
Semua modul ──▶ Laporan pimpinan + (kelak) AI Khadim tanya-jawab
```

## Urutan strategis yang disarankan

1. **Selesaikan yang ter-blueprint dulu** (PWA Guru F0-F1 + Akademik/SDM G1) — tanpa data lapangan mengalir, Lapisan 2 tidak punya bahan bakar.
2. **2a Dasbor Mutu** segera setelah data mengalir — murah, dampak jual & mutu terbesar.
3. **2b POS-tabungan kantin** — perputaran uang harian = nilai bisnis paling cepat terasa + alasan wali rajin top-up.
4. **2c Baitul Maal** — pembeda syar'i, fondasi Ledger-nya sudah menunggu sejak modul Keuangan.
5. Sisanya mengikuti permintaan tenant nyata — jangan membangun di depan kebutuhan.
