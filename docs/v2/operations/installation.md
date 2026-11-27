# Instalasi dan konfigurasi lokal

V2 berjalan terpisah dari aplikasi lama. Gunakan PHP 8.3, Composer 2, Node.js 24 LTS, sama dengan versi workflow, dan PostgreSQL 16. `composer.lock` belum tersedia karena lingkungan authoring tidak memiliki PHP/Composer. Workflow CI menghasilkan lockfile melalui resolusi Composer; tinjau dan commit hasil tersebut agar instalasi selanjutnya memakai versi dependensi yang sudah ditetapkan. Database lokal dapat berupa layanan PostgreSQL tersendiri atau container sekali pakai yang tidak menyentuh database produksi.

## Windows PowerShell

```powershell
Set-Location apps/platform-v2
Copy-Item .env.example .env
composer install
php artisan key:generate
npm ci
npm run build
```

Isi konfigurasi database pusat lokal dengan database uji khusus. Jalankan migrasi pusat sesuai langkah yang ada di README aplikasi v2. Buat database tenant lewat wizard/command v2 yang telah diuji, bukan dengan menyalin konfigurasi legacy. Bila app menyediakan seeder, gunakan hanya seeder demo fiktif.

## Linux/macOS shell

```sh
cd apps/platform-v2
cp .env.example .env
composer install
php artisan key:generate
npm ci
npm run build
```

Setelah konfigurasi basis data pusat lokal siap, jalankan migrasi pusat sesuai README aplikasi. Gunakan kredensial lokal yang hanya memiliki izin atas database pengembangan. Jangan gunakan `--force` untuk database selain pipeline CI yang disposable.

## Alat bantu pengembangan

Laravel Boost (`laravel/boost`) merupakan opsi bantuan pengembangan yang dipasang sebagai dependency `require-dev` untuk IDE/agent yang didukung. Gunakan hanya integrasi IDE lokal; jangan membuka MCP Boost ke jaringan atau staging. Halaman AI-assisted development Laravel menjelaskan Boost, bukan SDK runtime. V2 tidak memasang `laravel/ai`, tidak mengaktifkan fitur provider AI, dan tidak meminta API key provider untuk aplikasi dasar. Lihat https://laravel.com/framework/docs/13.x/boost.

## Konfigurasi

`.env.example` mendokumentasikan nama variabel, tanpa nilai rahasia. Isi hanya file `.env` yang telah dikecualikan Git. Buat APP_KEY secara lokal. Atur nama DB pusat khusus v2, prefix cookie/sesi v2, alamat mail sink lokal, lokasi storage privat, serta enkripsi metadata koneksi tenant. Jangan menaruh kunci, kata sandi, token atau data email nyata dalam repository, issue, log, screenshot, maupun fixture.

Email undangan demo diarahkan ke mail sink lokal. Gunakan `demo.invalid` atau domain uji lokal, dan alamat `example.invalid`. Jangan kirim undangan demo ke SMTP publik. Token undangan disimpan dalam bentuk hash, sekali pakai, dan berumur terbatas; jangan catat token mentah.

## Menyalakan aplikasi

Ikuti skrip/command `dev` yang benar-benar tersedia pada `apps/platform-v2/README.md`. Jangan jalankan seluruh stack aplikasi lama dari root repo. Pastikan `APP_ENV=local`, host DB lokal, nama DB khusus v2, dan mail sink lokal sebelum melakukan migrasi. Perintah start lama tidak boleh digunakan untuk aplikasi v2.

## Penyiapan tenant lokal

Gunakan wizard penyiapan v2 dan buat organisasi bernama “Demo Pesantren Cahaya” pada domain uji seperti `cahaya.test`; email admin menggunakan `admin@example.invalid`. Verifikasi alur token undangan dari mail sink dan autentikasi melalui host uji yang sudah dipetakan ke tenant itu. Dua tenant demo harus memakai dua database terpisah dan tidak berisi data orang nyata.





\n
