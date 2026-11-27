# Tata letak dan aksesibilitas

## Desktop dan ponsel

Shell menempatkan navigasi desktop di sisi kiri dan membatasi lebar konten. Pada lebar 768 px ke bawah, sidebar disembunyikan dan slot navigasi ponsel ditampilkan. Aplikasi mengisi slot itu dengan menu yang bisa dibuka lewat keyboard.

data-table menerima dua representasi data. Desktop memakai tabel HTML dengan caption dan header semantik. Tabel lebar dapat digulir lewat area yang fokusable. Ponsel memakai daftar kartu dengan pasangan label dan nilai. Tidak ada kolom yang terpotong atau diganti singkatan tersembunyi.

Sediakan filter, pagination, dan aksi pada kedua tampilan. Aksi tidak boleh bergantung pada hover. Isi sel tabel desktop juga membungkus teks panjang, termasuk domain dan nama tanpa spasi, agar kolom tidak melebar tanpa perlu.

Tampilkan hanya kolom yang diperlukan untuk mengambil keputusan pada tabel utama. Pindahkan keterangan sekunder atau riwayat lengkap ke halaman detail, disclosure yang bisa dibuka dengan keyboard, atau ringkasan sekunder. Pada tabel yang memang perlu banyak kolom, pertahankan gulir horizontal di desktop: gulir menjadi jalan terakhir ketika pembungkusan dan pemilihan kolom belum cukup, bukan memotong data.

## Pemeriksaan aksesibilitas

- Gunakan judul h1 yang menerangkan halaman.
- Beri tabel caption, header dengan scope, label landmark, dan nama tombol/tautan yang bermakna.
- Pastikan tombol/tautan dapat dijangkau dengan Tab dan memiliki fokus terlihat.
- Hubungkan label dengan id kontrol; kaitkan pesan galat lewat aria-describedby.
- Uji zoom 200%, viewport 320 px, pembesaran teks, tema, dan kontras aktual.
- Tampilkan status sebagai teks, bukan warna/ikon saja.
- Uji browser nyata dan pembaca layar sebelum rilis. Markup saja tidak membuktikan kesesuaian WCAG.

## Livewire

Komponen dapat dipakai dalam Blade/Livewire. Tambahkan wire:model, wire:submit, dan wire:key pada markup aplikasi sesuai siklus render. Paket tidak membuat event, endpoint, atau aturan izin.
