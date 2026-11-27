# Riset desain dasbor superadmin

Tanggal riset: 27 November 2026
Ruang lingkup: dasbor pusat `/platform`, panel operasional bagi pengelola Pesantren.online.

## Temuan codebase

Panel pusat dibangun dengan Laravel dan Filament. `AdminPanelProvider` mendaftarkan halaman dasbor dan navigasi pusat; `PlatformDashboard` menjadi halaman dasbor; widget `PlatformOverview` mengambil angka dan status dari `PlatformOperationsSnapshot`; Blade merender ringkasan; tema Filament dipasok melalui `resources/css/filament/pesantren/theme.css`. Graphify pada snapshot proyek lama menunjukkan hubungan `AdminPanelProvider`–`PlatformDashboard`–`PlatformOverview` dan tema panel. Snapshot itu berisi 1.728 node, tetapi dibuat sebelum pemindahan `PlatformDashboard` ke namespace `App\Filament\Platform\Pages`. Karena itu, snapshot dipakai untuk menemukan jalur kode, lalu seluruh kesimpulan dicocokkan lagi dengan berkas saat ini.

## Pola dari produk dan panduan desain

- Linear merapikan antarmuka Maret 2026 dengan menyamakan posisi header dan kontrol, meredupkan sidebar setelah pengguna tiba di halaman, mengurangi ikon dan garis yang tidak membantu, serta menjaga kepadatan informasi tetap terbaca. [Catatan desain Linear](https://linear.app/now/behind-the-latest-design-refresh)
- Vercel menjadikan navigasi dasbor barunya sebagai bawaan pada Februari 2026: sidebar bisa diubah ukurannya atau disembunyikan, urutan menu mengikuti alur kerja yang sering dipakai, dan navigasi ponsel diadaptasi untuk satu tangan. [Pembaruan dasbor Vercel](https://vercel.com/changelog/dashboard-navigation-redesign-rollout)
- IBM Carbon menyarankan tabel mendapatkan ruang utama yang cukup, tidak dimasukkan ke dalam kartu sempit atau tabel lain, serta menyelaraskan tinggi toolbar, baris, dan paginasi. [Panduan tabel Carbon](https://www.carbondesignsystem.com/building-blocks/core/components/data-table/guidelines)
- Pola Index Table Shopify menempatkan pencarian, sortir, seleksi massal, dan paginasi bersama kumpulan data yang memang perlu dikelola. [Pola Index Table Shopify](https://shopify.dev/docs/api/app-home/latest/patterns/compositions/index-table)
- Rilis Polaris 2.0 pada Oktober 2026 memperbarui tampilan tabel dan konsistensi tinggi kontrol. Ini mendukung pemakaian komponen native Filament yang seragam daripada menambah pustaka UI kedua. [Catatan Polaris 2.0](https://shopify.dev/changelog/posts/what-s-new-in-polaris-2-0-0-rc-1)
- Panduan adaptif Android menyarankan navigasi utama layar lebar dan layar ringkas disesuaikan menurut ukuran, dan memperingatkan bahwa pola bar navigasi tidak cocok untuk terlalu banyak tujuan. Pesantren.online memiliki banyak menu; grup sidebar tetap lebih cocok daripada memaksa semua menu masuk ke tab atas. [Panduan navigasi adaptif Android](https://developer.android.com/design/ui/mobile/guides/layout-and-content/layout-and-nav-patterns)
- Vercel mencatat bahwa instruksi desain yang konsisten perlu ditemani contoh implementasi dan evaluasi berulang; arahan verbal saja sering menghasilkan dasbor SaaS generik. [Cara Vercel menjaga desain tetap sesuai merek](https://vercel.com/blog/how-our-agents-build-on-brand-pages-with-design-md)

## Arah desain yang dipilih

Nama kerja: **Ruang kendali pesantren**. Dasbor dibuat sebagai ruang kerja operasional dengan aksen hijau hutan dan kanvas terang hangat. Sidebar diredam, area kerja menjadi fokus, kartu metrik menjawab pertanyaan superadmin, serta kesehatan layanan dan tindakan cepat ditempatkan berdekatan. Pada ponsel, kartu dan tindakan bertumpuk tanpa menggeser halaman secara horizontal.

Prinsip penerapan:

1. Ubah struktur dan hirarki halaman, bukan hanya warna dan radius komponen.
2. Gunakan angka tenant, antrean, bantuan, dan heartbeat yang sudah tersedia; jangan menambah grafik tren, skor AI, atau klaim yang datanya belum disimpan.
3. Pertahankan tabel, formulir, navigasi, tema tenant, dan perilaku akses dari Filament. Tidak menambah React, shadcn, grafik, JavaScript, atau pustaka UI baru.
4. Aksi utama di dasbor adalah menambah pesantren. Pengelolaan tenant, dukungan, aktivasi modul, dan pengumuman mendapat pintasan yang terlihat; alat pengembang tetap tersedia pada bagian sekunder.
5. Status selalu memiliki label, bukan warna saja. Gunakan target kontrol yang nyaman, fokus keyboard yang jelas, dan gerak yang tunduk pada `prefers-reduced-motion`.

## Evaluasi UI Pro Max

Pencarian desain awal terlalu umum dan mengusulkan glassmorphism serta komposisi halaman promosi. Hasil itu ditolak karena konteksnya dasbor operasi dengan banyak data. Pencarian ulang yang lebih sempit menghasilkan kategori `Data-Dense Dashboard` dan panduan aksesibilitas keyboard serta tabel responsif. Keputusan akhir tetap mengikuti data nyata dan struktur kode saat ini.
