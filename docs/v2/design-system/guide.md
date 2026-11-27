# Panduan sistem desain v2

## Tata letak

Shell utama, navigasi, ringkasan, wizard, dan alur tabel dibangun dengan Blade/Livewire serta komponen dan design tokens v2. Ini memberi tim kendali pada hierarki, jarak, interaksi, dan tampilan tenant/operator. Filament adalah pilihan pendukung untuk alat admin internal yang cocok dengan resource standar; jangan jadikan Filament sebagai shell atau tabel utama.

- Desktop: sidebar kiri yang dapat diciutkan; breadcrumb dan judul halaman; satu tombol utama di lokasi konsisten; konten ringkasan dan daftar mengikuti konteks/status.
- Ponsel: drawer navigasi, kolom tunggal, jarak tekan cukup, form bertahap.
- Tabel desktop tetap responsif dan dapat dibaca. Pada ponsel setiap record menjadi kartu atau baris bertumpuk dengan label dan nilai. Jangan biarkan isi terpotong atau mewajibkan scroll horizontal untuk membaca satu record.
- Komponen bersama mengikuti token v2. Gaya komponen tidak boleh bergantung pada hover; tema sekunder tidak boleh mengubah selector global tanpa sengaja.

## Token awal

| Token | Nilai | Penggunaan |
|---|---|---|
| `brand-700` | `#1D4ED8` | tombol utama, tautan aktif |
| `brand-50` | `#EFF6FF` | sorotan ringan |
| `ink-900` | `#111827` | teks utama |
| `ink-600` | `#4B5563` | teks sekunder |
| `surface` | `#FFFFFF` | kartu/form |
| `canvas` | `#F8FAFC` | latar kerja |
| `border` | `#6B7280` | batas input dan tabel |
| `success-800` | `#166534` | label keberhasilan |
| `warning-900` | `#78350F` | label peringatan |
| `danger-800` | `#991B1B` | label kesalahan |

Warna status memakai latar dan teks dengan kontras yang cukup, ikon boleh membantu tetapi bukan pengganti label. Cek kombinasi aktual pada komponen yang dirender, bukan nilai token saja.

## Batas paket

Sistem desain v2 dirancang sebagai paket Laravel-compatible yang terpisah dari aplikasi produk. Paket ini direncanakan memiliki token CSS, primitive Blade components, pola responsif, shell, dan komponen tabel berbentuk data responsif. Aplikasi memegang model bisnis, izin, data tenant, dan integrasi Livewire. Paket tidak memuat identitas/data organisasi, proses provisioning, atau runtime AI.

Calon lokasi dalam monorepo adalah `packages/pesantren-design-system`, dengan target publikasi sebagai repo tersendiri setelah repository dibuat dan kontrak API paket ditinjau. Path dan repository tersebut belum ada; dokumen ini hanya mencatat batas desain yang diinginkan. CI aplikasi tetap menguji integrasi yang benar-benar tersedia, bukan menganggap paket telah dibuat.

## Tipografi dan jarak

Gunakan `Inter, ui-sans-serif, system-ui, sans-serif` dengan fallback sistem agar tetap cepat pada jaringan terbatas. Ukuran dasar 16 px, teks isi 14–16 px, label input minimal 14 px, judul halaman 24–32 px desktop dan 22–28 px ponsel. Ukuran besar tetap mengikuti skala heading semantik.

Skala jarak 4, 8, 12, 16, 20, 24, 32, dan 48 px. Pakai radius 8 px untuk input/tombol, 12 px untuk kartu. Batas konten desktop sekitar 1200 px; pada layar kecil gunakan padding 16 px.

## Formulir

- Setiap kontrol punya label yang selalu terlihat, bukan placeholder sebagai label.
- Tandai wajib/opsional; jelaskan format domain dan email sebelum validasi.
- Kaitkan pesan gagal ke kontrol; terangkan tindakan koreksi dan pertahankan nilai yang sudah benar.
- Setelah kirim, tampilkan status berhasil/gagal di dekat judul atau ringkasan formulir serta fokuskan pesan bila ada kegagalan.
- Tindakan berisiko memerlukan ringkasan dampak; draf tidak menjalankan provisioning.

## Tabel dan status

Kolom ringkas dan dapat dipilah/filter dengan label yang jelas. Di ponsel, tampilkan setiap field sebagai label dan nilai dalam kartu/baris bertumpuk; jangan potong isi atau mewajibkan scroll horizontal untuk membaca record. Jangan hilangkan status/tindakan ke menu yang hanya tersedia dengan hover. Aksi utama ada di lokasi konsisten.

Status menggunakan teks Indonesia seperti “Penyiapan gagal” atau “Layanan ditangguhkan”. Gunakan ikon dan warna sebagai petunjuk tambahan. Status teknis harus menyertakan waktu cek; waktu lama ditandai “Kedaluwarsa”.

## Aksesibilitas: target WCAG 2.2 AA

Sebelum staging, periksa:

- Seluruh alur dapat dijalankan dengan keyboard; urutan Tab wajar; semua menu dapat dibuka/tutup; Escape mengembalikan fokus.
- Fokus terlihat dan tidak tertutup header/sidebar/modal; status error dan notifikasi diumumkan dengan tepat.
- Rasio kontras minimal 4.5:1 untuk teks normal dan 3:1 untuk teks besar serta komponen/indikator penting.
- Kontrol interaktif memenuhi ukuran minimum WCAG 2.2 AA 24×24 CSS px atau pengecualian yang sah; target produk 44×44 px untuk tugas utama.
- Label dan instruksi dapat dipahami screen reader; error menjelaskan masalah dan perbaikannya; jangan menyampaikan status melalui warna saja.
- Zoom 200%, reflow ponsel 320 CSS px, orientasi layar, autocomplete yang sesuai, dan konfirmasi data sebelum perubahan penting.
- Uji ponsel dengan sentuh dan keyboard eksternal. Tidak ada tindakan hanya hover, drag, atau gesture.

Rekam kombinasi warna yang diuji, browser, viewport, perangkat bantu, hasil keyboard, masalah, serta perbaikan. Klaim kepatuhan hanya setelah pemeriksaan dijalankan.



\n