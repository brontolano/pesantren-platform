# Model izin dan tenancy

| Tindakan | PlatformOperator | Admin tenant | Pengunjung |
|---|---:|---:|---:|
| Ringkasan platform dan daftar seluruh organisasi | Ya, setelah autentikasi pusat | Tidak | Tidak |
| Membuat/mengubah draf, mengatur domain, memulai atau melanjutkan setup | Ya; policy dan audit di server | Tidak | Tidak |
| Mengubah status layanan atau melihat pemeriksaan teknis lintas tenant | Ya; setiap tindakan dicatat | Tidak | Tidak |
| Melihat log aktivitas pusat | Ya, sesuai izin operator | Tidak | Tidak |
| Melihat konfigurasi dan pengguna tenant sendiri | Tidak lewat panel pusat | Ya, hanya tenant dari domain/kredensial yang sudah diverifikasi | Tidak |
| Membaca/mengubah data tenant lain atau DB pusat | Tidak kecuali operasi platform yang eksplisit | Tidak | Tidak |
| Mengakses PPDB, kesantrian, keuangan, atau Portal Orang Tua pada v2 | Tidak dalam slice fondasi | Ditolak di server saat modul nonaktif | Tidak |
| Menghapus permanen atau impersonasi | Tidak tersedia | Tidak tersedia | Tidak |

## Sumber otoritas

Panel pusat menggunakan model/guard operator tersendiri. Admin tenant tidak dapat mendaftar sebagai operator melalui form tenant. Domain permintaan dinormalisasi dan dicocokkan ke registri pusat di server; jangan menerima `tenant_id`, koneksi database, atau hak modul dari field request. Resolusi gagal harus menolak akses secara tertutup.

Setiap query tenant dijalankan setelah konteks domain diverifikasi dan koneksi terpilih. Policy tetap memeriksa pengguna, tenant, resource, dan tindakan. Ganti/bersihkan koneksi dan session tenant saat request berakhir. Cookie platform dan tenant memakai nama, domain, path, dan konfigurasi penyimpanan v2 yang berbeda dari aplikasi lama.

Database pusat memegang metadata tenant dan event operator. Tiap tenant mempunyai database, kredensial, penyimpanan privat, namespace session, dan cakupan cadangan sendiri. App tidak menaruh row data santri pada registry pusat. Upload/download/export memeriksa kepemilikan tenant pada server sebelum membaca objek.

Menu/route yang disembunyikan bukan pengamanan. Middleware/policy memeriksa aktivasi katalog pada server untuk halaman, action, API, background job dan ekspor. Tulis uji negatif dengan dua tenant fiktif: pengguna A tidak membaca/mengubah resource B walau ID resource diketahui. Log hanya mencatat metadata minimal, aktor operator, tenant ID internal, action, waktu dan hasil; tidak mencatat kata sandi, token, payload santri atau secret.
