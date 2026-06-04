# Panduan Operasional — Tata Usaha (TU)
## Platform Pesantren — Asy-Syifaa

> **Untuk:** T5.2.5 — Training TU: input santri, mutasi, status
> **Target:** TU bisa operasional mandiri tanpa kembali ke Excel dalam 1 hari training

---

## A. Login ke Panel Admin

1. Buka browser → `https://sistem.asy-syifaa.sch.id/admin`
2. Masukkan email dan password yang diberikan admin
3. Ganti password di pojok kanan atas → **Edit Profile** → **Update Password**

---

## B. Manajemen Santri

### B.1 — Menambah Santri Baru

**Menu:** Kesantrian → Santri → Tambah Santri

Field wajib diisi:
- NIS (Nomor Induk Santri) — harus unik per pesantren
- Nama lengkap
- Jenis Kelamin
- Status: `aktif`

Field opsional (isi selengkap mungkin):
- Tanggal lahir
- Foto santri
- No HP Wali, Email Wali
- Nama Ayah, Nama Ibu
- Asal sekolah, Alamat

**Tips:** Gunakan fitur **Import Excel** (`php artisan santri:import`) untuk input massal.

### B.2 — Menempatkan Santri ke Kelas

**Menu:** Kesantrian → Penempatan Kelas → Tambah

1. Pilih santri
2. Pilih kelas tujuan
3. Isi semester dan tahun akademik
4. Klik Simpan

**Perhatian:** Satu santri hanya boleh punya satu penempatan aktif. Penempatan sebelumnya otomatis menjadi riwayat.

### B.3 — Mutasi Santri (Pindah Kelas)

**Menu:** Kesantrian → Penempatan Kelas → Tambah (untuk santri yang sama)

1. Pilih santri yang akan dimutasi
2. Pilih kelas baru
3. Isi alasan mutasi (misal: "Kenaikan kelas")
4. Sistem otomatis menonaktifkan penempatan lama

### B.4 — Mengubah Status Santri

**Menu:** Kesantrian → Santri → Edit santri → field Status

| Status | Kapan Digunakan |
|---|---|
| `aktif` | Santri hadir dan belajar normal |
| `cuti` | Santri izin tidak hadir sementara (isi durasi & alasan) |
| `lulus` | Santri selesai pendidikan (sistem auto-buat record alumni) |
| `keluar` | Santri pindah ke pesantren lain |
| `dikeluarkan` | Santri dikeluarkan karena pelanggaran |

### B.5 — Melihat & Mencetak Daftar Santri per Kelas

**Menu:** Kesantrian → Santri → gunakan filter Kelas

Untuk cetak/ekspor:
- Klik **Export Excel** (CSV yang bisa dibuka di Excel)
- Klik **Cetak / PDF** (tampil daftar yang bisa disimpan PDF dari browser)

---

## C. Manajemen Kelas

### C.1 — Membuat Kelas Baru

**Menu:** Kesantrian → Kelas → Tambah Kelas

- Nama kelas (misal: "Kelas 1A Putra")
- Tingkat (1, 2, 3, ...)
- Kapasitas maksimal santri
- Wali kelas (pilih dari daftar user guru)

### C.2 — Melihat Kapasitas Kelas

Di halaman daftar Kelas, kolom **Sisa Kapasitas** menampilkan berapa slot yang masih tersedia.

---

## D. PPDB (Pendaftaran Peserta Didik Baru)

### D.1 — Melihat Pendaftar

**Menu:** PPDB → Calon Santri

Filter berdasarkan status: `menunggu`, `lulus_ujian`, `diterima`, `ditolak`, `registrasi_ulang`

### D.2 — Input Hasil Ujian

**Menu:** PPDB → Calon Santri → Edit → tab Hasil Ujian

1. Pilih calon santri
2. Pilih kategori ujian (tulis, baca Al-Quran, wawancara)
3. Input nilai
4. Simpan

### D.3 — Menetapkan Keputusan (Terima/Tolak)

**Menu:** PPDB → Calon Santri → Edit → field Status

Ubah status menjadi `diterima` atau `ditolak` → sistem otomatis kirim notifikasi WA ke wali calon santri.

### D.4 — Registrasi Ulang (Calon → Santri)

**Menu:** PPDB → Calon Santri yang sudah `diterima` → Registrasi Ulang

Setelah registrasi ulang selesai:
- Record santri baru otomatis dibuat di modul Kesantrian
- Data calon santri ter-link ke santri baru

---

## E. Hal Penting yang Wajib Diingat

1. **Jangan hapus santri** — gunakan status `keluar` atau `lulus`, bukan hapus. Riwayat data tetap dibutuhkan.
2. **NIS unik** — jika NIS duplikat muncul saat import, cek data lama dulu.
3. **Logout setelah selesai** — terutama di komputer bersama.
4. **Laporkan bug** — jika ada error atau data tidak sesuai, screenshot dan kirim ke admin platform.

---

## F. Pintasan Penting

| Aksi | Lokasi |
|---|---|
| Tambah santri | Kesantrian → Santri → + Tambah |
| Mutasi kelas | Kesantrian → Penempatan Kelas → + Tambah |
| Lihat alumni | Kesantrian → Alumni |
| Laporan santri per kelas | Kesantrian → Santri → Export |
| Calon PPDB | PPDB → Calon Santri |
