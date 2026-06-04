# Panduan Operasional — Bendahara
## Platform Pesantren — Asy-Syifaa

> **Untuk:** T5.2.6 — Training Bendahara: tagihan, pembayaran, laporan
> **Target:** Bendahara bisa operasional mandiri tanpa kembali ke Excel dalam 1 hari training

---

## A. Login ke Panel Admin

1. Buka browser → `https://sistem.asy-syifaa.sch.id/admin`
2. Masukkan email dan password yang diberikan admin
3. Ganti password di pojok kanan atas → **Edit Profile**

---

## B. Manajemen Tagihan

### B.1 — Membuat Tagihan untuk Satu Santri

**Menu:** Keuangan → Tagihan → Tambah Tagihan

Field wajib:
- Santri (pilih dari daftar)
- Jenis tagihan (SPP, Makan, Asrama, dll)
- Nominal
- Jatuh tempo
- Periode (bulan/tahun atau nama periode Hijriah)

### B.2 — Membuat Tagihan Massal (Bulk — Per Kelas)

**Menu:** Keuangan → Tagihan → Tambah Tagihan → pilih **Per Kelas**

1. Pilih kelas
2. Isi jenis tagihan dan nominal
3. Klik **Buat untuk Semua Santri Kelas Ini**

Sistem akan membuat tagihan untuk semua santri aktif di kelas tersebut sekaligus.

### B.3 — Tagihan Dual Kalender (Masehi & Hijriah)

Jika pesantren menggunakan kalender Hijriah (misal: SPP per bulan Hijriah), pilih **Periode Hijriah** saat membuat tagihan. Sistem akan otomatis menampilkan nama bulan Hijriah (Muharram, Safar, dst.).

Pengaturan kalender ada di: **Settings → Keuangan → Periode Keuangan**

---

## C. Manajemen Pembayaran

### C.1 — Mencatat Pembayaran (Transfer Manual)

**Menu:** Keuangan → Pembayaran → Tambah Pembayaran

1. Pilih tagihan yang dibayar
2. Input nominal yang dibayarkan
3. Tanggal pembayaran
4. Klik **Simpan**

**Pembayaran cicilan:** Jika santri bayar sebagian, input nominal sebagian. Status tagihan otomatis berubah ke `sebagian`. Saat lunas penuh → status otomatis `lunas`.

### C.2 — Verifikasi Pembayaran

Setelah mencatat pembayaran, klik tombol **Verifikasi** di baris pembayaran. Ini menandai pembayaran sudah dikonfirmasi bendahara.

Kolom `Diverifikasi` akan berubah dari ❌ menjadi ✅.

### C.3 — Melihat Status Tagihan

**Menu:** Keuangan → Tagihan → gunakan filter **Status**

| Status | Artinya |
|---|---|
| `belum` | Belum ada pembayaran sama sekali |
| `sebagian` | Ada pembayaran tapi belum lunas |
| `lunas` | Pembayaran penuh sudah masuk |

---

## D. Tabungan Santri

### D.1 — Setor Tabungan

**Menu:** Keuangan → Tabungan → pilih santri → Setor

Input nominal setoran → Simpan. Saldo otomatis bertambah.

### D.2 — Tarik Tabungan

**Menu:** Keuangan → Tabungan → pilih santri → Tarik

Input nominal penarikan → Simpan. Sistem mencegah penarikan jika saldo tidak cukup.

---

## E. Laporan Keuangan

### E.1 — Rekap Tagihan & Pembayaran

**Menu:** Keuangan → Laporan Keuangan

Filter tersedia:
- Periode (bulan/tahun)
- Status (belum/sebagian/lunas)
- Santri tertentu
- Kelas

### E.2 — Export Laporan ke Excel

Di halaman Laporan Keuangan → klik **Export CSV** → file bisa dibuka langsung di Excel.

### E.3 — Cetak Laporan (PDF)

Klik **Cetak** → browser menampilkan versi cetak → gunakan Ctrl+P → **Save as PDF**.

### E.4 — Rekonsiliasi

**Menu:** Keuangan → Rekonsiliasi

Menampilkan perbandingan total tagihan vs total terkumpul per periode. Berguna untuk laporan bulanan ke kepala pesantren.

---

## F. Dashboard Keuangan

Di halaman utama panel admin, widget **Ringkasan Keuangan** menampilkan:
- Total tagihan hari ini
- Total pembayaran hari ini
- Jumlah tagihan yang jatuh tempo belum bayar
- Trend mingguan pembayaran

---

## G. Hal Penting yang Wajib Diingat

1. **Selalu verifikasi pembayaran** setelah mencatat — ini memastikan data bisa diaudit.
2. **Jangan hapus pembayaran** — jika ada kesalahan input, buat koreksi manual atau hubungi admin.
3. **Backup mental:** semua transaksi sudah otomatis tersimpan dan di-backup harian oleh sistem.
4. **Notifikasi WA:** sistem bisa kirim otomatis notifikasi ke wali saat tagihan dibuat atau pembayaran diterima. Pastikan template WA sudah disetup di **Notifikasi → Template**.

---

## H. Pintasan Penting

| Aksi | Lokasi |
|---|---|
| Buat tagihan | Keuangan → Tagihan → + Tambah |
| Catat pembayaran | Keuangan → Pembayaran → + Tambah |
| Lihat tunggakan | Keuangan → Tagihan → filter Status: belum |
| Rekap bulanan | Keuangan → Laporan Keuangan |
| Tabungan santri | Keuangan → Tabungan |
| Template notifikasi | Notifikasi → Template Notifikasi |
