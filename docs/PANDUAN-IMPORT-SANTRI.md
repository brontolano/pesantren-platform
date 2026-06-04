# Panduan Import Data Santri — Go-Live Asy-Syifaa

> **Untuk:** T5.2.1 — Import data santri nyata dari Excel v1 → database baru
> **Command:** `php artisan santri:import`

---

## Prasyarat

1. VPS sudah running (`docker compose up -d`)
2. Tenant `asy-syifaa` sudah dibuat (`php artisan tenant:create "Asy-Syifaa" asy-syifaa`)
3. File Excel v1 sudah tersedia di server

---

## Format File Excel yang Diterima

File `.xlsx` atau `.csv` dengan kolom-kolom berikut (nama kolom fleksibel, case-insensitive):

| Kolom Wajib | Alternatif Nama |
|---|---|
| NIS | `nis`, `no_induk`, `nomor_induk` |
| Nama | `nama`, `nama_lengkap`, `nama_santri` |
| Jenis Kelamin | `jk`, `jenis_kelamin`, `kelamin` — isi: `L`/`P` atau `Laki`/`Perempuan` |
| Tanggal Lahir | `tgl_lahir`, `tanggal_lahir` — format: `YYYY-MM-DD` atau `DD/MM/YYYY` |

| Kolom Opsional | Keterangan |
|---|---|
| Status | `aktif` / `cuti` / `lulus` / `keluar` (default: `aktif`) |
| Kelas | Nama kelas — dicocokkan ke tabel `kelas` tenant |
| No HP Wali | Untuk notifikasi WA |
| Email Wali | Untuk akun Portal Wali |
| Nama Ayah | Identitas ortu |
| Nama Ibu | Identitas ortu |
| Alamat | Alamat lengkap |
| Asal Sekolah | SMP/MTs asal |

---

## Langkah Import

### 1. Upload file ke server
```bash
scp data-santri-asy-syifaa.xlsx user@VPS_IP:/opt/pesantren/imports/
```

### 2. Dry-run dulu (validasi tanpa simpan)
```bash
docker compose exec app php artisan santri:import asy-syifaa \
    /opt/pesantren/imports/data-santri-asy-syifaa.xlsx \
    --dry-run
```

Output akan menampilkan:
- Jumlah baris valid
- Baris yang akan dilewati (duplikat NIS, data tidak lengkap)
- Preview 5 baris pertama

### 3. Cek laporan dry-run
```bash
# Laporan disimpan otomatis di storage/app/imports/santri/
docker compose exec app ls storage/app/imports/santri/
```

### 4. Jika dry-run oke, eksekusi import sesungguhnya
```bash
docker compose exec app php artisan santri:import asy-syifaa \
    /opt/pesantren/imports/data-santri-asy-syifaa.xlsx
```

### 5. Verifikasi hasil
```bash
# Cek jumlah santri yang berhasil diimport
docker compose exec app php artisan tinker --execute="
    tenancy()->initialize(App\Models\Tenant::find('asy-syifaa'));
    echo 'Total santri: ' . Modules\Kesantrian\Models\Santri::count();
    tenancy()->end();
"
```

---

## Tips & Troubleshooting

| Masalah | Solusi |
|---|---|
| `NIS duplikat` | Import menggunakan `updateOrCreate` — baris duplikat diupdate, tidak error |
| `Kelas tidak ditemukan` | Buat kelas dulu via panel admin, lalu re-import |
| `Tanggal tidak valid` | Pastikan format `YYYY-MM-DD` atau `DD/MM/YYYY` |
| `Memory limit` | Untuk file >10MB, jalankan dari dalam container dengan `--memory=512M` |
| `Import terputus` | Aman untuk dijalankan ulang — sudah idempotent (tidak duplikat) |

---

## Setelah Import Selesai

1. Login ke panel admin Asy-Syifaa: `https://sistem.asy-syifaa.sch.id/admin`
2. Cek menu **Kesantrian → Santri** — verifikasi data
3. Tempatkan santri ke kelas via **Kesantrian → Penempatan Kelas**
4. Setup tagihan awal via **Keuangan → Tagihan** (bisa bulk per kelas)
5. Aktifkan akun wali via export email dari data santri
