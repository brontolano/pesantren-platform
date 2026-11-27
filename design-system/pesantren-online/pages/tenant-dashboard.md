# Halaman: Dashboard tenant

Mengikuti [`MASTER.md`](../MASTER.md).

## Tujuan

Dashboard memberi satu saran langkah berikutnya, bukan menampilkan semua hal yang belum selesai sekaligus. Pengguna dapat membuka daftar lengkap dan melanjutkannya sesuai kesiapan.

## Hierarki dan perilaku

1. Tampilkan status progres secara tekstual.
2. Tampilkan satu tindakan berikutnya sebagai tautan utama.
3. Simpan rincian seluruh langkah dan tautan setup per unit dalam daftar native yang dapat dibuka.
4. Izinkan checklist ditutup; jangan memakai bahasa yang membuat pengguna merasa semua pekerjaan harus selesai sekaligus.

Status progres disertai `role="progressbar"` dan nilai yang terbaca pembaca layar. Tiap langkah menyatakan selesai lewat ikon dan teks aksesibel. Tautan minimum 44px untuk aksi utama, fokus keyboard terlihat, dan layout tetap satu kolom di ponsel.
