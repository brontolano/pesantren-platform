# Halaman: Aplikasi operasional Filament

Mengikuti [`MASTER.md`](../MASTER.md). Berlaku untuk panel pusat (`/platform`) dan panel tenant (`/admin`) saat tema bawaan dipilih.

## Arah visual

- Gaya: ruang kerja SaaS kontemporer yang tenang; bidang netral, kartu putih, garis pemisah halus, emerald sebagai aksen tindakan.
- Kanvas terang memakai abu-hijau pucat. Dark mode memakai hijau arang yang berlapis, teks dengan kontras tinggi, dan aksen emerald yang lebih terang.
- Sidebar tetap mudah dipindai dengan grup yang jelas, ruang antar-item, serta state aktif yang lembut. Sediakan opsi ciutkan di desktop; jangan menyamarkan label penting.
- Heading halaman singkat dan tegas. Area data mengambil prioritas visual dibanding dekorasi.
- Jangan memakai header WhatsApp hijau penuh, latar hitam pekat, glow, blur glass, bayangan tebal, atau animasi dekoratif.

## Komponen dan interaksi

- Gunakan komponen native Filament untuk navigasi, tabel, form, badge, modal, dan aksi. Tema hanya memoles token, batas, fokus, dan state hover.
- Satu aksi utama per konteks. Aksi tambahan tetap sekunder.
- Target sentuh utama minimal 44px pada layar kecil; tombol ikon harus memiliki nama aksesibel.
- Fokus keyboard selalu terlihat; pesan bantuan dan error dekat dengan field.
- Status operasional memakai label teks selain warna.
- Hormati `prefers-reduced-motion`; transisi singkat dan tidak menggeser layout.
- Tabel mempertahankan overflow horizontal terkontrol pada ponsel; shell dan header tidak boleh membuat scroll horizontal halaman.

## Batas tema

- Override ini berlaku pada entry tema bawaan. Preset warna tenant tetap mengatur warna primer dan state aktif.
- Tema eksternal yang dipilih tenant (Brisk, Nord, Moonlight, Neo-Brutalis) mempertahankan CSS bundel masing-masing.
- Default mode terang memberi tampilan konsisten pada instalasi baru; preferensi tema yang dipilih pengguna tetap dihormati.
- Jangan menambahkan shadcn/React hanya untuk memoles Filament; gunakan komponen dan utility CSS yang sudah tersedia.
