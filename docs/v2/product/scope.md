# Cakupan produk tahap fondasi

## Pengguna dan pekerjaan utama

Operator platform membuat dan memantau organisasi untuk pesantren, yayasan, organisasi Islam, dan majelis taklim dari panel pusat. Admin organisasi hanya mengelola ruang kerja organisasinya sendiri.

## Alur operator

1. Masuk melalui autentikasi operator pusat.
2. Buka Ringkasan untuk melihat jumlah organisasi per status penyiapan/layanan dan tindakan yang perlu diperiksa.
3. Buat draf organisasi; isi nama, jenis lembaga, slug/domain kanonis, serta admin pertama.
4. Tinjau domain dan penerima undangan. Jalankan penyiapan dengan status draf → menunggu → berjalan → siap atau gagal.
5. Jika langkah gagal, lanjutkan upaya untuk organisasi yang sama. Domain, basis data, dan admin tidak dibuat ulang.
6. Periksa hasil teknis terpisah dari status layanan administratif dan tindak lanjut yang direkam.

Ringkasan, wizard, navigasi, dan shell utama memakai Blade/Livewire serta design tokens v2 agar urutan dan hierarki konten dapat ditata bebas. Core UI dirancang di paket Laravel-compatible terpisah `packages/pesantren-design-system` (calon path/repo; belum dibuat); aplikasi memakai adapter untuk alur domain. Filament dapat dipakai sebagai alat admin pendukung untuk operasi internal yang cocok; ia bukan shell atau alur tabel utama. Desktop menggunakan sidebar kiri, judul/breadcrumb dan satu tindakan utama. Di ponsel, navigasi masuk drawer dan isi mengalir satu kolom.

## Status yang berbeda makna

**Status penyiapan:** Draf, Menunggu, Berjalan, Siap, Gagal. Status menunjukkan tahapan provisioning dan kapan operator dapat melanjutkan.

**Status layanan:** Nonaktif, Aktif, Ditangguhkan. Status menunjukkan keputusan operasional platform terhadap akses tenant. Jangan mengubahnya otomatis hanya karena probe teknis gagal.

**Pemeriksaan teknis:** Belum diperiksa, Sehat, Gagal, atau Kedaluwarsa; berisi waktu pemeriksaan, langkah yang gagal, dan saran pemulihan. Hasil teknis tidak mengubah layanan tanpa tindakan operator yang tercatat.

## Katalog modul

Katalog menjelaskan modul yang tersedia dan kesiapan/availability per tenant. Pada fondasi awal, PPDB, kesantrian, keuangan, dan Portal Orang Tua tetap nonaktif. Menyembunyikan menu bukan kontrol akses: semua route dan action harus menolak modul nonaktif melalui pemeriksaan server.

## Prinsip produk

- Bahasa Indonesia yang langsung, satu keputusan per layar.
- Minta informasi minimum; beri contoh format dan jelaskan akibat perubahan sebelum disimpan.
- Status selalu memiliki teks, bukan warna saja.
- Tidak ada penghapusan permanen atau impersonasi.
- Panel pusat hanya memakai metadata operasional. Jangan memuat nama, identitas, atau jumlah santri.
- Semua demo memakai tenant, domain, email, dan profil operator fiktif.


\n