# Pesantren.online Design System

Paket Composer ini menyediakan komponen Blade, token CSS, dan pola tata letak untuk antarmuka Pesantren.online. Paket ditujukan untuk Laravel 13 dan aplikasi yang memakai Livewire 4. Model bisnis, izin, konteks tenant, dan alur tetap menjadi tanggung jawab aplikasi host.

Paket tidak bergantung pada Filament dan tidak menambahkan JavaScript, font jarak jauh, runtime AI, atau kredensial provider.

## Instalasi

Aplikasi membutuhkan PHP 8.3 atau lebih baru dan Laravel 13. Untuk pengembangan dalam monorepo, tambahkan konfigurasi ini ke composer.json aplikasi:

~~~json
{
  "repositories": [
    {
      "type": "path",
      "url": "../../packages/pesantren-design-system",
      "options": { "symlink": true }
    }
  ],
  "require": {
    "brontolano/pesantren-online-design-system": "@dev"
  }
}
~~~

Sesuaikan jalur dengan lokasi aplikasi. Paket juga dapat dipasang langsung dari repositori GitHub sebelum ada rilis Packagist dengan menambahkan repositori VCS berikut ke composer.json aplikasi:

~~~json
{
  "repositories": [
    { "type": "vcs", "url": "https://github.com/brontolano/pesantren-online-design-system" }
  ],
  "require": {
    "brontolano/pesantren-online-design-system": "dev-main"
  }
}
~~~

Setelah rilis bertag dipublikasikan ke Packagist, gunakan:

~~~sh
composer require brontolano/pesantren-online-design-system
php artisan vendor:publish --tag=pesantren-ui-assets
~~~

Muat stylesheet hasil terbitan dari layout Blade utama:

~~~css
<link rel="stylesheet" href="{{ asset('vendor/pesantren-ui/pesantren-ui.css') }}">
~~~

## Contoh

~~~blade
<x-pesantren-ui::app-shell title="Pesantren.online">
    <x-slot:sidebar><nav aria-label="Navigasi desktop">...</nav></x-slot:sidebar>
    <x-slot:mobileNav><nav aria-label="Navigasi ponsel">...</nav></x-slot:mobileNav>
    <x-pesantren-ui::page-header title="Organisasi" description="Kelola draf dan status penyiapan." />
    <x-pesantren-ui::status title="Penyiapan selesai" tone="success" />
</x-pesantren-ui::app-shell>
~~~

Baca docs/components.md dan docs/accessibility-and-layout.md untuk pola komponen dan tata letak.

## Pemeriksaan

~~~sh
node scripts/verify-package.mjs
~~~

GitHub Actions menjalankan pemeriksaan tersebut dan php -l pada setiap push serta pull request. Workflow hanya memerlukan izin baca isi repositori dan tidak menerbitkan paket.

Sebelum rilis, validasi Composer dan lakukan smoke test pada host Laravel 13 dengan versi PHP yang didukung. Lihat docs/testing-and-release.md.
