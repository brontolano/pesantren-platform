# Petunjuk Codex untuk Pesantren Platform

## Dokumen acuan

- PRD produk baru: `docs/PRD-REDESIGN-PESANTREN-ONLINE.md`. Dokumen ini berstatus draf 0.1 dan mencakup situs pusat, ruang kerja superadmin, aktivasi modul, serta situs publik tenant.
- `docs/PRD.md` adalah PRD lama untuk kebutuhan operasional pesantren. Jangan mencampur dua cakupan itu tanpa permintaan.
- Persyaratan PRD baru memiliki ID seperti `TEN-01`, `SUP-01`, dan `CMS-02`. Cantumkan ID terkait pada rencana kerja dan catatan implementasi.
- `docs/BLUEPRINT-ARSITEKTUR.md` menjelaskan batas arsitektur. Cocokkan keputusan di sana dengan kode dan `composer.json` sebelum mengubah sistem.
- `docs/LOCAL-TESTING.md` berisi cara menjalankan aplikasi dan pemeriksaan lokal. Baca sebelum menyalakan proses atau memakai basis data uji.
- `docs/VIBE-CODING-PRD.md` berisi cara kerja Codex bersama pemilik produk.

## Batas produk yang harus dijaga

- PRD masih perlu validasi dengan pesantren uji. Jangan anggap seluruh bagian P0/P1/P2 sudah disetujui untuk dibangun sekaligus.
- Kerjakan satu alur pengguna sampai utuh. Sebelum mengubah kode, petakan kebutuhan PRD ke halaman, model, izin, modul, dan pola yang sudah ada.
- Jangan mengarang keputusan untuk pertanyaan terbuka PRD. Catat pilihan yang dibutuhkan pemilik produk dan lanjutkan bagian lain yang tidak bergantung padanya.
- Fitur kontribusi platform harus tetap terpisah dari tagihan layanan. Jangan mengaktifkan alur pembayaran atau penggalangan sebelum penerima, tata kelola, klasifikasi, dan keputusan DON-04 disetujui.
- Definisikan monitoring dari kondisi layanan yang benar-benar terukur. Jangan menyimpulkan mutu atau keaktifan pesantren dari isi data santri.

## Aturan teknis

- Periksa `git status` sebelum mengubah berkas. Pertahankan perubahan yang sudah ada dan tetap di branch yang sedang dipakai kecuali pemilik produk meminta hal lain.
- Gunakan versi serta dependensi dari `composer.json`, `composer.lock`, `package.json`, dan `package-lock.json`. Blueprint dan README dapat tertinggal.
- Aplikasi ini satu codebase Laravel dengan panel Filament, modul di `Modules/`, tenancy, izin berbasis peran, serta Vue/Inertia. Ikuti pola di modul yang disentuh; jangan menambahkan aplikasi, autentikasi, atau basis data terpisah tanpa keputusan arsitektur.
- Isolasi tenant adalah batas keamanan. Periksa konteks tenant dan otorisasi di server untuk halaman, query, ekspor, lampiran, antrean, cache, dan notifikasi. Menyembunyikan tombol saja tidak cukup.
- Jangan membaca, menampilkan, atau menyalin rahasia dari `.env`, log autentikasi, atau basis data produksi. Jangan memasukkan kredensial atau data santri ke kode, fixture publik, keluaran terminal, atau Git.
- Untuk perubahan UI, gunakan komponen dan gaya yang telah ada. Pastikan label, validasi, fokus keyboard, status kosong/gagal, dan tampilan ponsel masuk ke rancangan alur.
- Jangan menyalakan Docker, build penuh, atau rangkaian test yang berat tanpa permintaan verifikasi. Bila diminta memeriksa, pilih pemeriksaan terkecil yang memberi bukti dan jelaskan kebutuhan resource sebelum menjalankan proses berat.

## GitHub dan produksi

- Catatan ini mencerminkan pemeriksaan VPS tanggal 27 November 2026. Produksi mengikuti `origin/main` dari VPS melalui cron `/etc/cron.d/pesantren-platform-autodeploy`, sekitar setiap lima menit, memakai `/opt/deploy.sh`.
- `.github/workflows/deploy.yml` di `main` hanya menyediakan pemicu manual. Jangan menganggap push GitHub Actions sebagai jalur deploy produksi.
- Perubahan pada branch lokal atau branch fitur tidak tampil di produksi sampai masuk ke `main`.
- Script produksi memakai `git reset --hard origin/main`, membangun ulang dan memulai ulang container aplikasi, lalu menjalankan migrasi. Perubahan server yang dilacak Git dapat tertimpa dan aplikasi dapat mengalami jeda singkat.
- Jangan melakukan commit, push, mengubah pengaturan GitHub, menjalankan perintah VPS, atau deploy hanya sebagai langkah sampingan. Lakukan jika permintaan yang sedang berlaku memang mencakup tindakan itu.

## Cara bekerja dan melaporkan

1. Ringkas tujuan pengguna dan requirement ID yang dipilih.
2. Periksa kode terkait dan pola yang sudah tersedia; gunakan Graphify sebagai peta tambahan bila graph ada, lalu buka sumber kodenya untuk memastikan.
3. Sebelum implementasi multi-berkas, tunjukkan satu rencana kecil berisi perubahan, kriteria penerimaan, risiko tenant/data, dan pemeriksaan yang diperlukan.
4. Implementasikan hanya irisan yang disetujui. Jangan mengubah requirement lain agar tampak lengkap.
5. Setelah pekerjaan, sebutkan berkas, perilaku yang berubah, pemeriksaan yang benar-benar dijalankan, hal yang belum terverifikasi, dan langkah berikutnya.

Bahas pekerjaan dengan pemilik produk dalam bahasa Indonesia yang jelas. Pertahankan nama file, kelas, perintah, dan istilah teknis dalam bentuk aslinya.
