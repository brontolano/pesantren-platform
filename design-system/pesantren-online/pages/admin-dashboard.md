# Dasbor admin pusat

Mengikuti [`MASTER.md`](../MASTER.md). Halaman ini berlaku untuk dasbor superadmin `/platform`.

## Komposisi

- Susun halaman sebagai ruang kendali untuk pekerjaan superadmin: status layanan, angka tenant, dukungan, dan pintasan inti.
- Sidebar redup dibanding area kerja. Menu tetap berkelompok dan dapat diciutkan; jangan menjejalkan semua tujuan ke navigasi atas.
- Header memuat orientasi singkat, tanggal pembaruan, dan satu tindakan utama.
- Kartu ringkasan hanya menampilkan angka yang benar-benar tersedia. Jangan membuat tren, target, skor kesehatan, atau rekomendasi AI tanpa sumber data.
- Kesehatan layanan menampilkan nama layanan, label status, uraian singkat, dan waktu pemeriksaan. Warna hanya memperkuat label.
- Tabel mendapat lebar yang cukup. Pencarian, sortir, seleksi massal, dan paginasi berada di area kontrol yang sama bila digunakan.
- Ponsel memakai susunan satu kolom, menu yang bisa dibuka, target aksi minimum 44px, dan tanpa scroll horizontal halaman.

## Visual

- Kanvas: abu-hijau hangat; permukaan putih; aksen hijau hutan dan mint.
- Sidebar: hijau hutan gelap dengan label grup yang redup dan item aktif yang jelas.
- Hierarki lebih ditentukan oleh ukuran, ruang, dan kontras daripada garis atau bayangan tebal.
- Ikon SVG hanya membantu mengenali kelompok atau tindakan. Jangan mengulang ikon pada setiap baris dekoratif.
- Dark mode memakai lapisan hijau arang yang konsisten. Tetap pertahankan preferensi tema yang dipilih pengguna.

## Batas implementasi

- Pertahankan komponen dan akses Filament. Jangan memasang framework UI kedua hanya untuk membedakan tampilan.
- Perubahan tema tidak mengubah izin, data tenant, alur aksi, atau status layanan.
- Periksa desktop dan ponsel, terang dan gelap, serta state fokus keyboard.
