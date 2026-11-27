# Tes, verifikasi, dan CI

CI `platform-v2.yml` hanya berjalan pada push dan pull request yang menyentuh aplikasi/dokumentasi v2. Jalur/repo paket sistem desain masih berupa usulan; CI paket belum diklaim sampai paket tersedia. Job bekerja dari `apps/platform-v2`, menyiapkan PHP 8.3 dan PostgreSQL 16 disposable, memvalidasi Composer, menjalankan Laravel Pint, migrasi dan tes aplikasi, lalu membangun aset frontend bila `package.json` ada. Izin token GitHub hanya baca. Workflow ini tidak memuat build/push/deploy ke server.

## Perintah lokal

```sh
cd apps/platform-v2
composer validate --strict
composer install
vendor/bin/pint --test
php artisan test
npm ci
npm run build
```

Jalankan tes terhadap database lokal khusus disposable; jangan memakai `.env` produksi. Di PowerShell, `Copy-Item .env.example .env` lalu isi database uji lokal sebelum menjalankan migrasi. CI memakai kredensial PostgreSQL sintetis di environment service; kredensial itu bukan secret akun.

## Skenario penerimaan

- Operator dapat masuk, melihat Ringkasan, membuat draf, memperbarui identitas/domain, dan menelusuri audit action.
- Admin tenant ditolak pada route pusat. Domain duplikat/canonical collision ditolak.
- Setup sukses, gagal pada langkah yang dipilih, dan retry pada organization yang sama tidak menggandakan database/domain/admin.
- Token undangan kedaluwarsa atau yang sudah dipakai ditolak. Token aktif mengundang admin fiktif melalui mail sink dan admin menyelesaikan login tenant.
- Tenant A tidak mengakses data tenant B melalui URL, ID, API, ekspor, atau job. Gunakan dua database uji berbeda.
- Pengelolaan status layanan tidak menghapus data. Probe teknis memiliki waktu/status dan tidak mengubah status layanan secara tersembunyi.
- Katalog modul nonaktif ditolak oleh server walau endpoint dipanggil langsung. Tidak ada data santri pada panel, audit, atau monitor pusat.
- Aksi mutasi penting dicatat. Pencarian, status kosong, validasi dan kesalahan memberi pesan bahasa Indonesia yang dapat diperbaiki.
- UI shell, Ringkasan, dan wizard memakai komponen/token v2. Tabel desktop menyesuaikan lebar; pada ponsel tiap nilai tetap berlabel dalam kartu/baris bertumpuk tanpa clipping atau horizontal scroll wajib.

## Bukti dan pengukuran

Bedakan hasil tes otomatis, tinjauan antarmuka lab, target desain, dan telemetri pengguna. Laporkan perintah/versi runtime, DB, hasil tes, viewport/perangkat, dan keterbatasan. LCP, INP, CLS, serta p95 daftar organisasi dihitung pada fixture sintetis dan lingkungan yang disebutkan. Jangan menyalin target ke kolom hasil, dan jangan menyebut data sintetis sebagai ukuran pengguna nyata.
