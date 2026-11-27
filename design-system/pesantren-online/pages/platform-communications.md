# Komunikasi dan dukungan platform

Pekerjaan utama superadmin: menyampaikan informasi resmi dan merespons permintaan bantuan. Pekerjaan staf tenant: menemukan pesan platform, menandai pesan sudah dibaca, membuka kasus bantuan, dan meneruskan konteks yang dibutuhkan.

- Pesan platform memiliki penerima yang eksplisit (semua tenant atau satu tenant), kategori, prioritas, waktu tayang, dan masa berakhir.
- Draf dibedakan dari pesan terbit. Pesan penting/mendesak memakai teks status, bukan warna saja.
- Kotak masuk tenant hanya menampilkan pesan yang cocok dengan tenant terautentikasi di query server.
- Kasus dukungan memisahkan catatan internal dari balasan tenant; catatan internal tidak dikirim sebagai percakapan publik.
- Tampilkan nomor kasus, status, waktu pembaruan dan riwayat percakapan; jangan janjikan SLA sebelum kapasitas ditetapkan.
- Form memakai label terlihat, batas panjang, error dekat field, serta konfirmasi setelah berhasil.
- Jangan meminta data santri sensitif untuk diagnosa awal. Kanal lampiran tidak dibuka sebelum batas jenis/ukuran dan masa retensi disepakati.
- Pemeriksaan tenant saat ini bersifat atas permintaan. Jangan menampilkan label “monitoring aktif” atau “tenant aktif” tanpa sinyal dan definisi yang dapat dibuktikan.
