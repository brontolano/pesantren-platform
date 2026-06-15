# Panduan: Setup, Import, Export & Cetak Data

> Untuk pengurus/TU pesantren. Bahasa sederhana, langkah demi langkah.
> Semua dilakukan di **panel admin** (`namapesantren.pesantren.online/admin`).

## 1. Mulai dari "Pusat Setup"
Menu **Pengaturan → Pusat Setup**. Di sini ada kartu tiap unit (Kesantrian, Akademik,
Keuangan, dst.) dengan **persentase progres**. Klik satu unit → Anda dipandu
langkah demi langkah: tombol **Buka** mengantar ke halaman yang tepat. Langkah yang
sudah beres otomatis tercentang ✓.

**Urutan disarankan:** Kesantrian (santri) dulu, baru unit lain.

## 2. Mengisi data: 3 cara
Di tiap halaman daftar (mis. Santri, Pelajaran, Tagihan) ada tombol:

### a. Tambah satu-satu
Tombol **Buat/Tambah** — untuk data sedikit.

### b. Import dari Excel (banyak sekaligus) — paling cepat
1. Klik **Unduh Template** → dapat file contoh berisi kolom yang benar.
2. Isi data Anda di file itu (atau pakai file Excel sendiri).
3. Klik **Import [nama] → pilih file**.
4. **Pemetaan kolom** (kalau nama kolom Excel Anda berbeda): sistem menampilkan
   dropdown — cocokkan "kolom Anda → kolom sistem". Centang **Simpan pemetaan**
   agar tak perlu ulang lain kali.
5. Biarkan **"Validasi dulu (dry-run)"** tercentang → klik kirim. Sistem mengecek
   tanpa menyimpan. Baca hasilnya.
6. Bila bersih, **hilangkan centang dry-run** → kirim lagi → data tersimpan.

> **Aman:** baris yang sudah ada **dilewati** (tak dobel), jadi boleh diulang.
> **Kalau banyak yang gagal:** sistem menyimpan **file CSV daftar semua kegagalan**
> (disebut di notifikasi) — buka, perbaiki, import ulang.

### c. Export (untuk koreksi / cadangan)
Tombol **Export** → unduh data saat ini dalam format yang sama dengan import.
Edit di Excel, lalu import kembali bila perlu.

## 3. Akun Wali
Di halaman **Santri** → tombol **Buat Akun Wali**. Sistem membuat akun untuk santri
yang punya **nomor HP wali** (login Portal Wali pakai no. HP). Kredensial sementara
tersimpan sebagai file untuk dibagikan.

## 4. Mencetak dokumen
Tombol **Cetak** tersedia di:
- **Jadwal** → pilih kelas → cetak jadwal per kelas.
- **Nilai** → pilih kelas (+periode) → daftar nilai.
- **Penempatan Asrama** → cetak per blok.
- **Obat** → cetak stok (yang menipis ditandai).

Halaman cetak terbuka di tab baru — klik tombol **Cetak** di sana (atau Ctrl+P) →
simpan sebagai PDF atau cetak ke printer.

## 5. Tips kalau bingung
- **Pakai Template** dulu supaya kolom pasti benar.
- **Selalu dry-run** sebelum import sungguhan.
- Kolom Excel boleh beda urutan/nama — gunakan **Pemetaan kolom**.
- Menu yang tak terlihat = modulnya belum aktif (hubungi admin/tim platform).
