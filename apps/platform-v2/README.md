# Superadmin dan onboarding tenant v2

Aplikasi Laravel 13 mandiri untuk operator platform dan admin organisasi. UI Blade memakai Livewire 4 sebagai opsi interaksi lanjutan; shell dan komponen dasar berasal dari `brontolano/pesantren-online-design-system`, tanpa Filament. Registri pusat menggunakan PostgreSQL 16. Setiap organisasi mendapat database tenant tersendiri. Identitas operator dan admin tenant memakai guard serta cookie sesi berbeda.

## Kebutuhan

- PHP 8.3+, Composer 2, Node.js 24 LTS, PostgreSQL 16.
- Ekstensi PHP `ctype`, `json`, `mbstring`, `openssl`, `pdo`, dan `pdo_pgsql`.
- Mail sink lokal, misalnya Mailpit, pada `127.0.0.1:1025`.
- Provisioning role PostgreSQL terpisah dengan izin `CREATEDB` dan `CREATEROLE`; kredensialnya hanya diisi lewat lingkungan lokal/deployment.

## Menjalankan lokal

Jalankan perintah dari direktori `apps/platform-v2`:

```sh
cp .env.example .env
composer install
php artisan key:generate
```

Isi koneksi PostgreSQL registri, kredensial provisioning, dan mail sink di `.env`. Database registri harus sudah dibuat. Selanjutnya:

```sh
composer dump-autoload
php artisan vendor:publish --tag=pesantren-ui-assets --force
php artisan migrate --database=central --force
php artisan db:seed --database=central
npm install
npm run build
php artisan serve
```

Untuk mengisi ruang platform dengan data contoh fiktif, setel `DEMO_OPERATOR_EMAIL` dan `DEMO_OPERATOR_PASSWORD` lokal (minimal 16 karakter), lalu jalankan:

```sh
php artisan db:seed --class=DemoDataSeeder --database=central
```

Seeder menolak environment selain `local`. Draf contoh memakai domain `demo-pesantren-v2.test`; arahkan nama tersebut ke host lokal untuk mencoba alur admin tenant. Saat operator menyiapkan draf, database tenant dibuat, skema tenant dimigrasikan, dan undangan dikirim ke mail sink. Kode undangan berumur pendek, hanya tersimpan sebagai hash di registri, dan hanya dapat dipakai sekali.

## Pemeriksaan

Feature test memakai registri PostgreSQL terpisah sesuai `phpunit.xml`, sedangkan fixture provisioning tenant menggunakan database SQLite sementara. Jangan arahkan test ke registri produksi.

```sh
php artisan test
node scripts/verify-foundation.mjs
node scripts/verify-onboarding.mjs
node scripts/verify-tenant-boundary.mjs
node scripts/verify-operations.mjs
```

Setiap verifier menjalankan feature test terkait dan hanya mencetak marker sukses bila proses test berakhir sukses. Operator perlu mengaktifkan MFA sebelum aplikasi dibuka ke staging publik; implementasi MFA belum termasuk slice ini.

## Sistem desain lokal

Composer memakai path repository `../../packages/pesantren-design-system`. Instalasi produksi harus membawa paket itu di lokasi relatif yang sama, atau mengganti path repository dengan versi paket rilis. Setelah aset diterbitkan, layout memuat `public/vendor/pesantren-ui/pesantren-ui.css`. Komponen tabel menyediakan tabel semantik desktop dan kartu `<dl>` dengan label pada layar sempit.

`composer.lock` belum tersedia karena lingkungan authoring tidak memiliki PHP/Composer untuk menyelesaikan dependency dengan benar. Jangan membuat lockfile manual; hasilkan dan tinjau melalui instalasi Composer pada toolchain PHP 8.3 sebelum rilis.
