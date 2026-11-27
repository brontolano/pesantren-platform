# Website Pesantren — editor tenant

Pekerjaan utama: mengisi identitas pesantren, memperbarui artikel/profil, dan memeriksa halaman publik. Identitas, artikel, dan galeri berada pada kelompok navigasi **Website Pesantren**. Pengaturan susunan section berada pada **Tampilan Website (Lanjutan)** agar tugas rutin tidak bercampur dengan konfigurasi tema.

- Pakai label yang menyebut hasil, bukan istilah internal CMS.
- Simpan identitas/kontak melalui form dengan label eksplisit; jelaskan bahwa kontak internal platform berbeda dari kontak publik website.
- Artikel dan galeri tetap dibatasi oleh hak akses tenant; draft tidak boleh tampil sebagai konten terbit.
- Editor website bawaan memakai alur draf → pratinjau → terbitkan. Simpan tidak mengganti versi publik.
- Pratinjau menampilkan banner yang jelas, memakai tautan tenant bertanda tangan dengan masa berlaku singkat, dan disetel `noindex,nofollow`.
- Setiap perubahan penting memiliki tombol simpan dan umpan balik berhasil/gagal. Terbitkan memerlukan konfirmasi.
- Tautan pratinjau membuka domain publik tenant, bukan host pusat, jika domain publik telah tersedia.
- Kontrol pengaktifan dan pengurutan section punya nama aksesibel dan target sentuh nyaman.
- Mode sederhana menampilkan identitas website dan konten rutin. Susunan section, pengaturan SEO, dan konfigurasi penuh tetap di Mode Lengkap.
- Editor identitas untuk situs eksternal tetap menerapkan perubahan langsung; beri tahu pengguna sebelum menyimpan.
- Jangan menyiratkan bahwa setiap tenant memiliki domain khusus atau situs telah terbit sebelum statusnya diverifikasi.
