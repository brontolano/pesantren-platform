# Pengujian dan rilis

## Pemeriksaan lokal

~~~sh
node scripts/verify-package.mjs
node --check scripts/verify-package.mjs
php -l src/PesantrenDesignSystemServiceProvider.php
~~~

Workflow package di .github/workflows/quality.yml menjalankan pemeriksaan Node dan sintaks PHP pada push/pull request. Izin GitHub dibatasi ke contents read; workflow tidak merilis atau men-deploy.

Pada aplikasi host Laravel 13 dengan PHP 8.3 dan Composer:

~~~sh
composer validate --strict
composer install
php artisan vendor:publish --tag=pesantren-ui-assets
~~~

Buat smoke test host untuk service provider, resolusi view pesantren-ui::components.button, dan render komponen dengan CSS package. Uji framework yang didukung sebelum memperluas batas versi.

## Pemeriksaan visual

Render contoh pada desktop lebar, viewport 768 px, dan ponsel 320 px. Pastikan tabel berganti menjadi kartu dengan semua label/nilai tetap terbaca. Uji nama/domain panjang, status, pagination, dan aksi. Gunakan keyboard dan pembaca layar. Catat browser, ukuran layar, hasil, serta temuan.

## Kontribusi

Jaga awalan pu-, hindari selector CSS global, sertakan contoh dan catatan perubahan ketika API bertambah. Jalankan pemeriksaan lokal sebelum meminta tinjauan.

## Rilis

1. Tinjau perubahan API, kompatibilitas Laravel/PHP, lisensi, dan catatan perubahan.
2. Jalankan pemeriksaan statis dan smoke test host.
3. Buat tag versi semantik setelah perubahan disetujui.
4. Terbitkan source dan dokumentasi dari repositori package khusus.
5. Kunci versi rilis melalui composer.lock di aplikasi pemakai.

Package belum menyatakan versi stabil sampai CI lintas host dan pemeriksaan aksesibilitas selesai.
