# Proposal Reorganisasi Navigasi: 20 Grup → 9 Domain

*13 Juni 2026 — pesantren.online*

---

## Masalah

| Metrik | Sekarang |
|--------|----------|
| Jumlah grup navigasi | 20 |
| Total menu item | 163 |
| Item di grup "Keuangan" | 38 (terbesar) |
| Grup dengan 1-2 item | 5 (Administrasi, Kepengasuhan, Notifikasi, File Storage) |
| Duplikasi item | Buku Besar vs Keuangan |
| SDM nyasar di Keuangan | 14 item HR ada di dalam grup Keuangan |

---

## 9 Domain Baru

### 1. 🏫 Kesantrian (23 item)

**Siapa:** Bagian Kesantrian, Wali Asrama, Musyrif

**Sub-grup:**
- Data Santri: Santri, Kelas, Penempatan, Alumni, Kesiapan EMIS
- Asrama: Blok, Kamar, Penempatan, Biaya, Presensi Asrama
- Kesehatan: Riwayat, Konseling, Obat
- Disiplin: Jenis Pelanggaran, Pelanggaran Santri, Rekap
- Kegiatan & Izin: Kegiatan, Template, Recurring, Izin, Kunjungan Wali
- Layanan: Stasiun Pendaftaran, App Santri, Gerbang, Jenguk, Moderasi Pesan

**Dari grup lama:** Kesantrian + Asrama + Kepengasuhan + Kegiatan & Perizinan + Kesehatan & Konseling

---

### 2. 📝 PPDB (7 item)

**Siapa:** Panitia PPDB (musiman, tim sendiri)

**Item:** Calon Santri, Dokumen PPDB, Bank Soal (Offline), Paket Soal, Jadwal Ujian, Hasil Ujian, Laporan PPDB

**Dari grup lama:** PPDB (tidak berubah)

---

### 3. 📚 Akademik & Tahfidz (23 item)

**Siapa:** Waka Akademik, Koordinator Tahfidz, Guru

**Sub-grup:**
- Kurikulum: Lembaga, Periode, Kurikulum, Kalender, Pelajaran, Program
- KBM: Jadwal, Presensi, Jurnal KBM, Kitab
- Penilaian: Nilai, Grid Nilai, Rapor, Rapor Diniyah, Rubrik, Sidang Kenaikan
- Ujian: Bank Soal Akademik, Ujian Online
- Tahfidz: Halaqah, Hafalan, Target, Penilaian, Munaqasyah

**Dari grup lama:** Akademik + Tahfidz

---

### 4. 💰 Keuangan (18 item)

**Siapa:** Bendahara, Kasir, Petugas SPP

**Sub-grup:**
- SPP & Tagihan: Tagihan, Pembayaran, Skema Biaya, Tunggakan Alumni
- Tabungan & Kas: Tabungan, Topup, Setor Keliling, Loket Kas
- POS & Kantin: POS Profil, Kategori, Kasir Kantin, Rekon Kantin
- Dana Sosial: Donasi, Program Donasi, Beasiswa
- Administrasi: Metode Pembayaran, Rekon Bank

**Dari grup lama:** Sebagian Keuangan (transaksi saja)

---

### 5. 📊 Akuntansi & Pajak (22 item)

**Siapa:** Akuntan, Kepala Keuangan, Auditor

**Sub-grup:**
- Buku Besar: COA, Jurnal, Jurnal Manual, Buku Besar, BKU, Neraca Saldo
- Anggaran: Anggaran RKAM, Varians, Alokasi Biaya
- Referensi: Sumber Dana, Divisi/Unit, Kehalalan, Kurs, Buku Pembantu
- Pajak: Belanja & Pajak, Tarif Pajak, Buku Pembantu Pajak, Kalkulator
- Laporan & Tutup Buku: Laporan Keuangan, Periode Akuntansi, Pengaturan

**Dari grup lama:** Buku Besar + sebagian Keuangan (belanja, pajak)

---

### 6. 👥 Kepegawaian (14 item)

**Siapa:** TU, Bagian SDM, Waka Kepegawaian

**Sub-grup:**
- Data Pegawai: Pegawai, Jabatan, Penugasan, Khidmah
- Kehadiran: Presensi Staf, Cuti/Leave
- Kompensasi: Payroll, Pratinjau Bisyaroh, Generate THR, Kalkulator PPh21
- Administrasi: Kontrak & SK, Kasbon Pegawai
- Rekrutmen: Pelamar

**Dari grup lama:** Item SDM yang sebelumnya nyasar di grup Keuangan

---

### 7. 🤖 AI Khadim Digital (9 item)

**Siapa:** Semua role (per permission) — fitur unggulan platform

**Item:** Pengaturan AI, Persetujuan Pimpinan, Sumber Pengetahuan, Eskalasi Tanya Ustadz, Generator Soal, Generator RPP, Ikhtisar Kitab, Kotak Masuk WA, Usulan FAQ

**Dari grup lama:** AI Khadim Digital (tidak berubah)

---

### 8. 🏢 Kelembagaan & Komunikasi (18 item)

**Siapa:** TU, Humas, Sarpras, CRM

**Sub-grup:**
- Website: Artikel, Galeri, Builder, Profil, Identitas, Setting
- Komunikasi: CRM, Penawaran, Broadcast WA, Surat
- Inventory: Item, Lokasi, Batch
- Aset & Procurement: Aset, Mutasi, Pembiayaan, Supplier, PO
- Sarpras: Laporan Kerusakan, Area Piket, Piket Hari Ini

**Dari grup lama:** Website & CMS + Administrasi + Sarpras & Umum + Pembiayaan + File Storage + Notifikasi + sebagian Keuangan (inventory, CRM, supplier)

---

### 9. ⚙️ Pengaturan & Mutu (29 item)

**Siapa:** Admin IT, Pimpinan, Penjaminan Mutu

**Sub-grup:**
- Mutu SYAMIL: Dashboard, Sasaran, Temuan, Inspeksi, SKM, Dosir, Profil/Kelas/Lembaga
- Pelaporan Lintas: Dasbor Pimpinan, Pusat Laporan, Dana BOS/BOP, RKAM, Lap. Pokok, Dana Kebajikan, Dana Terikat, Dosir Asesmen, EMIS
- Sistem: Kebijakan, Tema, Istilah, Approval (2), Audit, Dokumen, Media (2), Notifikasi, API

**Dari grup lama:** Pengaturan + Mutu/SYAMIL + Pelaporan

---

## Mapping Lengkap: Grup Lama → Baru

| Grup Lama | Jumlah | Aksi | Grup Baru |
|-----------|--------|------|-----------|
| Kesantrian | 14 | Tetap + absorb | Kesantrian |
| PPDB | 7 | Tetap | PPDB |
| Akademik | 18 | Merge | Akademik & Tahfidz |
| Tahfidz | 5 | Merge | Akademik & Tahfidz |
| Asrama | 4 | Absorb | Kesantrian |
| Kepengasuhan | 1 | Absorb | Kesantrian |
| Kegiatan & Perizinan | 5 | Absorb | Kesantrian |
| Kesehatan & Konseling | 3 | Absorb | Kesantrian |
| Keuangan | 38 | **Pecah 4 arah** | Keuangan (18) + Kepegawaian (14) + Kelembagaan (7) + Akuntansi (4) |
| Buku Besar | 17 | Merge | Akuntansi & Pajak |
| Pelaporan | 12 | Absorb | Pengaturan & Mutu |
| Pembiayaan | 3 | Absorb | Kelembagaan & Komunikasi |
| Sarpras & Umum | 3 | Absorb | Kelembagaan & Komunikasi |
| Administrasi | 1 | Absorb | Kelembagaan & Komunikasi |
| Mutu / SYAMIL | 6 | Merge | Pengaturan & Mutu |
| File Storage | 2 | Absorb | Kelembagaan & Komunikasi |
| Notifikasi | 1 | Absorb | Kelembagaan & Komunikasi |
| Pengaturan | 8 | Merge | Pengaturan & Mutu |
| Website & CMS | 6 | Merge | Kelembagaan & Komunikasi |
| AI Khadim Digital | 9 | Tetap | AI Khadim Digital |

---

## Alasan Keputusan Kunci

### Keuangan & Akuntansi DIPISAH
- **Persona berbeda:** Kasir/petugas SPP vs Akuntan/Kepala Keuangan
- **Mental model berbeda:** Transaksi harian vs Pembukuan & pelaporan
- **Ukuran:** 55 item (38+17) → 18 + 22 = lebih manageable

### Kesantrian & PPDB DIPISAH
- **PPDB musiman** dengan tim/panitia sendiri
- **Objek berbeda:** Santri aktif vs Calon santri
- **7 item PPDB** = ukuran yang pas untuk satu grup

### AI tetap TERPISAH
- **Lintas domain:** Generator soal (akademik) + WA chatbot (komunikasi) + FAQ (knowledge)
- **9 item** = ukuran ideal
- **Fitur unggulan** yang perlu visibility tersendiri

### Kepegawaian DIPISAH dari Keuangan
- **14 item SDM** sebelumnya nyasar di grup Keuangan
- **Domain TU/SDM** bukan domain bendahara
- **Pola Dapodik:** GTK = grup terpisah

### Pelaporan TIDAK jadi grup sendiri
- **Prinsip:** Laporan embedded per domain
- Hanya laporan **lintas-domain** yang masuk Pengaturan & Mutu

---

## Dampak Implementasi

| Aspek | Berubah? |
|-------|----------|
| `navigationGroup` di ~80 file PHP | ✅ Ya |
| `NavigationGroup` di TenantPanelProvider.php | ✅ Ya |
| Route / URL | ❌ Tidak |
| Controller | ❌ Tidak |
| Model / Database | ❌ Tidak |
| Vue / Frontend | ❌ Tidak |
| Permission system | ❌ Tidak |
| PWA portals (Guru, Wali, Keuangan, dll) | ❌ Tidak |

---

*Proposal ini berdasarkan riset NNGroup (5-7 grup ideal), pola Dapodik, dan audit lengkap 163 item menu di 20 grup navigasi.*
