# Backup, restore, dan rollback

## Prinsip

Backup v2 tidak pernah mengambil kredensial dari aplikasi lama. Inventaris mencakup basis data pusat, semua database tenant yang terdaftar, file privat setiap tenant, versi skema, serta waktu dan hasil backup. Lindungi key enkripsi metadata koneksi secara terpisah. Batasi akses backup, enkripsi at-rest dan in-transit, atur retensi, serta catat keberhasilan tanpa memasukkan secret ke log.

## Backup

1. Pastikan database pusat dan registri tenant tersedia; buat snapshot inventaris tenant yang konsisten.
2. Ambil logical backup PostgreSQL pusat dan tiap database tenant memakai akun backup khusus dengan hak minimum.
3. Simpan objek privat per tenant dengan identitas dan hash checksum yang bisa diverifikasi.
4. Simpan metadata versi app/migrasi dan waktu UTC; jangan simpan token invite aktif atau file `.env` dalam dokumentasi/artefak publik.
5. Simpan backup terenkripsi di lokasi terpisah dengan kebijakan akses dan retensi yang disetujui.

## Restore

1. Pilih titik pemulihan dan tenant yang dimaksud; lakukan di lingkungan pemulihan terisolasi, bukan langsung ke produksi.
2. Pulihkan pusat/tenant serta berkas ke database dan bucket kosong dengan izin minimum. Restore tenant tunggal harus menjaga batas DB tenant.
3. Validasi tabel inti, jumlah record sintetis/anonim, objek berkas, checksum, migrasi dan resolusi domain; jangan mencetak isi record.
4. Jalankan smoke test login operator dan admin tenant serta uji penolakan tenant kedua.
5. Catat RPO/RTO yang terukur, tindakan operator, kegagalan dan persetujuan sebelum pertukaran traffic.

## Rollback kode/skema

V2 belum dirilis. Sebelum rilis, tetapkan prosedur versi sebelumnya, kompatibilitas migrasi expand/contract, backup pra-rilis, waktu freeze, dan pemilik keputusan. Bila aplikasi gagal, kembalikan traffic/kode ke artefak sebelumnya dan pulihkan DB hanya sesuai RPO/RTO yang telah diuji; jangan jalankan `migrate:rollback` membabi buta jika migrasi menghapus data. Perubahan skema bersifat additive hingga rencana pemulihan tervalidasi. Tidak ada prosedur ini yang memberi izin untuk memindahkan data v1 ke v2.
