# Panduan sistem desain v2

## Tata letak

Shell utama, navigasi, ringkasan, wizard, dan tabel dibangun dengan Blade/Livewire serta komponen dan design tokens dari paket UI v2. Ini memberi tim kendali pada hierarki, jarak, interaksi, dan tampilan tenant/operator tanpa ketergantungan pada Filament.

- Desktop: sidebar kiri berwarna forest, header ringkas, judul halaman jelas, dan satu tombol utama pada lokasi konsisten.
- Ponsel: drawer navigasi, kolom tunggal, jarak tekan cukup, form bertahap.
- Tabel desktop tetap responsif dan dapat dibaca. Pada ponsel setiap record menjadi kartu atau baris bertumpuk dengan label dan nilai. Jangan biarkan isi terpotong atau mewajibkan scroll horizontal untuk membaca satu record.
- Komponen bersama mengikuti token v2. Gaya komponen tidak boleh bergantung pada hover; tema sekunder tidak boleh mengubah selector global tanpa sengaja.

## Token awal

| Token | Nilai | Penggunaan |
|---|---|---|
| `brand-700` | `#123C35` | sidebar dan identitas utama |
| `brand-600` | `#2F6B5B` | tombol, tautan, dan fokus |
| `brand-50` | `#DDE9DF` | sorotan ringan |
| `accent` | `#B99A55` | aksen aktif |
| `ink-900` | `#1F2D46` | teks utama |
| `ink-600` | `#536274` | teks sekunder |
| `surface` | `#FFFEFA` | kartu/form |
| `canvas` | `#F7F5EF` | latar kerja |
| `border` | `#D7DFD8` | batas input dan tabel |
| `success-800` | `#176B47` | label keberhasilan |
| `warning-900` | `#744B08` | label peringatan |
| `danger-800` | `#9B2828` | label kesalahan |

Warna status memakai latar dan teks dengan kontras yang cukup, ikon boleh membantu tetapi bukan pengganti label. Cek kombinasi aktual pada komponen yang dirender, bukan nilai token saja.

## Batas paket

Paket Composer `brontolano/pesantren-online-design-system` tersedia di `packages/pesantren-design-system`. Paket menyediakan token CSS, komponen Blade, shell, dan tabel yang berubah menjadi kartu berlabel pada layar sempit. Aplikasi memegang model bisnis, izin, data tenant, dan integrasi Livewire. Paket tidak memuat identitas/data organisasi, proses provisioning, ketergantungan pada Filament, atau runtime AI. Repo GitHub tersendiri disiapkan agar paket dapat digunakan lintas aplikasi Laravel.

## Tipografi dan jarak

Gunakan `Inter, ui-sans-serif, system-ui, sans-serif` dengan fallback sistem agar tetap cepat pada jaringan terbatas tanpa permintaan font eksternal. Tema light memakai kanvas ivory, kartu putih hangat, sidebar forest, dan aksen jade/gold. Ukuran dasar 16 px, teks isi 14–16 px, label input minimal 14 px, judul halaman 24–32 px desktop dan 22–28 px ponsel. Ukuran besar tetap mengikuti skala heading semantik.

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
