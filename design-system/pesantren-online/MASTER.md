# Design System — Pesantren.online

Status: keputusan kerja untuk implementasi awal, 27 November 2026.

## Dasbor operasi superadmin

Dasbor pusat memakai bahasa visual **Ruang kendali pesantren**, dengan sidebar yang tenang, kanvas abu-hijau hangat, dan susunan yang mengutamakan kesehatan layanan serta pekerjaan superadmin. Riset dan alasan desain tercatat di [`ADMIN-DASHBOARD-DESIGN-RESEARCH.md`](../../docs/ADMIN-DASHBOARD-DESIGN-RESEARCH.md); aturan halaman ada di [`pages/admin-dashboard.md`](pages/admin-dashboard.md). Rujukan utamanya adalah pembaruan navigasi Linear dan Vercel 2026 serta panduan tabel Carbon dan Shopify. Hasil generator UI yang mengusulkan glassmorphism tidak dipakai.

## Arah visual

- **Gaya:** Minimalism & Swiss Style; tenang, rapi, berwibawa, mudah dipindai.
- **Pola halaman publik:** Trust & Authority + Conversion: satu janji yang jelas, cara kerja, kemampuan inti, lalu ajakan konsultasi/demo.
- **Gerak:** minim. Hindari carousel, parallax, animasi masuk yang menutupi konten, dan elemen yang bergerak terus. Hormati `prefers-reduced-motion`.
- **Kepadatan:** sedang-renggang. Beri satu tugas atau gagasan utama per bagian; gunakan progressive disclosure untuk pengaturan lanjutan.
- UI Pro Max memilih gaya minimal dan pola trust/conversion. Ia menyodorkan indigo sebagai warna utama, tetapi rekomendasi palet itu tidak cocok dengan brand emerald–navy–gold yang sudah dipakai repo. Karena itu, warna berikut mempertahankan identitas kode yang ada. Palet indigo hasil generator tidak diterapkan.

## Token warna

| Token | Nilai | Penggunaan |
|---|---|---|
| `--po-brand` | `#047857` | Tombol utama, tautan, status aktif; teks putih memiliki kontras yang memadai |
| `--po-brand-hover` | `#065f46` | Hover/pressed tombol utama |
| `--po-brand-soft` | `#ecfdf5` | Latar sorotan lembut |
| `--po-ink` | `#0f172a` | Judul dan teks penting |
| `--po-text` | `#334155` | Teks isi |
| `--po-muted` | `#475569` | Keterangan; jangan gunakan untuk teks kecil di atas latar gelap tanpa ukur kontras |
| `--po-border` | `#e2e8f0` | Garis dan pemisah |
| `--po-surface` | `#ffffff` | Kartu dan kontrol |
| `--po-canvas` | `#f8fafc` | Latar utama |
| `--po-cream` | `#fffaf3` | Bidang hangat untuk aksen editorial |
| `--po-gold` | `#c9a24a` | Aksen dekoratif kecil saja; bukan teks kecil di atas putih |
| `--po-danger` | `#b42318` | Kesalahan dan tindakan berbahaya, disertai label teks |

Jangan gunakan warna sebagai satu-satunya pembawa status. Pasangkan status dengan label. Pastikan teks normal mencapai kontras 4.5:1, indikator kontrol non-teks 3:1, dan fokus keyboard terlihat.

## Tipografi dan tata letak

- Keluarga: **Plus Jakarta Sans** untuk isi dan judul, mengikuti font yang sudah dimuat halaman saat ini. Jangan menambah font kedua tanpa kebutuhan visual yang jelas.
- Teks isi minimal 16px; line-height sekitar 1.55–1.65. Judul tetap responsif dan tidak mengandalkan satu pemenggalan baris paksa.
- Lebar isi panjang sekitar 65–75 karakter per baris; halaman terpusat dengan gutter responsif 20–28px.
- Ritme jarak berbasis 4px: 8 / 12 / 16 / 24 / 32 / 48 / 64.
- Radius kartu 12–16px; bayangan tipis. Garis dan jarak menjadi pemisah utama, bukan tumpukan bayangan.

## Komponen dan perilaku

- **Primary CTA:** satu per konteks, berwarna `--po-brand`, label berupa tindakan nyata. Secondary CTA berupa outline/link.
- **Kartu fitur:** judul singkat + hasil kerja yang nyata; jangan menampilkan angka dampak, testimoni, sertifikat, atau klaim tanpa sumber.
- **Formulir:** label terlihat, petunjuk dekat input, kesalahan spesifik di dekat field, status kirim sukses/gagal, dan jalur pemulihan.
- **Komunikasi platform:** penerima, jenis, prioritas, waktu tayang, dan status baca harus jelas; catatan internal dukungan tidak pernah muncul di ruang tenant.
- **Monitoring:** tampilkan sumber dan batas makna sinyal. Pemeriksaan atas permintaan tidak boleh dilabeli sebagai monitoring kontinu.
- **Website tenant:** identitas dan tugas konten rutin berada di Mode Sederhana; pengaturan susunan section dan SEO tetap pada Mode Lengkap.
- **Navigasi:** kelompokkan berdasarkan pekerjaan pengguna. Di layar kecil sediakan menu yang dapat dibuka lewat keyboard/tap; jangan mengandalkan hover.
- **Ikon:** gunakan SVG/Heroicons dari keluarga yang sudah dipakai. Emoji tidak digunakan sebagai ikon antarmuka.
- **Kontrol pointer:** sediakan target minimum 24×24 CSS px sesuai WCAG 2.2 AA; gunakan 44×44 px atau lebih pada aksi utama ponsel jika ruang memungkinkan.
- **Responsif:** tinjau pada 375, 768, 1024, dan 1440px; tidak ada scroll horizontal. Pastikan fokus tidak tertutup sticky header.
- **Kinerja:** gambar berukuran sesuai, lazy-load untuk konten di bawah lipatan, sediakan ruang sebelum aset dimuat, minimalkan JavaScript pihak ketiga.

## Konteks dan sumber keputusan

- Dasar brand yang diperiksa: token pada `resources/views/landing.blade.php` dan warna panel pada `app/Providers/Filament/AdminPanelProvider.php`.
- Pencarian UI Pro Max: `multi tenant pesantren SaaS platform calm institutional welcoming simple` dan `pesantren education SaaS institutional green cream trust simple onboarding`; hasil gaya minimal/trust dinilai sesuai, warna indigo hasil generator ditolak karena bertentangan dengan warna brand yang ada.
- Panduan form UI Pro Max: label terlihat, umpan balik pengiriman, serta tampilkan kompleksitas bertahap.
- Panduan alur editor tenant: [website-tenant.md](pages/website-tenant.md).
- Panduan komunikasi, kotak masuk tenant, dan support: [platform-communications.md](pages/platform-communications.md).
- Pemetaan stack mengonfirmasi Laravel 13, Filament, Vue 3, dan Tailwind 4 tersedia. Halaman pusat saat ini memakai Blade dengan CSS inline; implementasi ini mempertahankan jalur tersebut agar tidak menambah dependensi.

## Prinsip konten produk

- Hindari klaim “gratis”, “siap dalam sehari”, harga, uptime, skala pengguna, testimoni, dan otomasi yang belum dibuktikan oleh pemilik produk.
- Jelaskan bahwa tenant dapat memulai dari profil/situs, lalu menentukan modul setelah kebutuhan dan kesiapan dipahami.
- Bedakan situs publik pesantren dari ruang staf yang memerlukan login.
- Jangan menyamakan invoice layanan dengan kontribusi sukarela.
