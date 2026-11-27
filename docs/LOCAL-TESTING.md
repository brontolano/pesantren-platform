# Menjalankan pemeriksaan lokal

## Prasyarat

- Dependensi PHP dan Node sudah dipasang dari `composer.lock` dan `package-lock.json`.
- PHP 8.3 dan PostgreSQL 16 tersedia. Pada workspace pengembangan ini, runtime portable berada di folder `.runtime` yang satu tingkat di atas repository.
- Database khusus pengujian bernama `pesantren_test_asrama_solo` tersedia di `127.0.0.1:5432`.

## Perintah

```powershell
.\scripts\test-local.ps1
```

Pilih suite atau tes tertentu dengan meneruskan argumen PHPUnit/Laravel:

```powershell
.\scripts\test-local.ps1 tests/Unit
.\scripts\test-local.ps1 tests/Feature/PlatformCommunicationPostgresTest.php
```

Skrip memakai kredensial pengujian yang sama dengan `phpunit.xml` dan memastikan hanya database `pesantren_test_asrama_solo` yang dipilih. Tes dapat menjalankan migrasi dan menghapus data pada database itu; jangan arahkan konfigurasi ini ke database produksi.

Di Windows sandbox, PostgreSQL portable mungkin tidak dapat memulai proses server karena pembatasan restricted token. Bila database sudah berjalan, skrip cukup memeriksa koneksi dan meneruskan tes. Jika perlu menyalakan server, jalankan skrip dari PowerShell lokal.

## Menjalankan panel lokal untuk dicoba

Jalankan tes terlebih dahulu agar database uji siap, lalu buka server lokal:

```powershell
.\scripts\test-local.ps1
.\scripts\start-local.ps1 -Port 8088 -CreateSuperAdmin
```

Perintah kedua meminta sandi secara tersembunyi, membuat akun `superadmin@pesantren.test` hanya di database uji, menjalankan migrasi yang belum diterapkan, lalu membuka server di `http://127.0.0.1:8088/platform`. Port 8088 dipakai untuk sesi preview ini karena port 8000 sudah tertahan. Jangan gunakan alamat email/sandi ini sebagai akun produksi. Jika akun uji sudah dibuat, `-CreateSuperAdmin` mereset sandinya; jalankan tanpa opsi itu untuk sekadar membuka server.

MFA email dikirim melalui mailer log. Untuk memperoleh kode, periksa entri terbaru di `storage/logs/laravel.log` pada mesin lokal. Hentikan server dengan `Ctrl+C`. Tes berikutnya dapat mengosongkan sebagian tabel di database uji, jadi jalankan ulang tes hanya setelah selesai menjelajahi preview.

## Akses superadmin platform

Di server yang sudah memakai `.env` dan database pusat yang benar, buat akun pemilik platform dengan perintah interaktif berikut. Perintah meminta sandi secara tersembunyi, tidak memiliki sandi bawaan, dan hanya membuat akun pusat (`tenant_id = null`):

```bash
php artisan platform:super-admin pemilik@contoh.id --name="Nama Pemilik"
```

Login ke `/platform/login`. Login superadmin mewajibkan MFA email; pastikan mailer server dapat mengirim email. Untuk mereset sandi akun superadmin pusat yang sudah ada, tambahkan `--reset-password`. Perintah menolak email yang terikat ke akun tenant atau akun pusat biasa.

Dasbor platform menampilkan waktu pemeriksaan, konektivitas database dan antrean, pekerjaan gagal, beban kasus dukungan, tenant, serta heartbeat scheduler dan tiga worker. Migrasi tabel heartbeat termasuk dalam rilis aplikasi. Konfigurasi produksi menjalankan service `scheduler`; worker harus memakai antrean default/notifikasi, broadcast, dan AI sesuai `docker-compose.production.yml`. Sinyal heartbeat berumur lebih dari tiga menit ditandai perlu ditinjau.
