# Halaman: Ringkasan platform

Mengikuti [`MASTER.md`](../MASTER.md).

## Tujuan

Menjawab “apa yang perlu saya kerjakan selanjutnya?” untuk superadmin tanpa menampilkan metrik bisnis yang belum disepakati atau mengesankan bahwa aktivitas santri sedang diawasi.

## Hierarki

1. Judul kerja dan satu CTA utama untuk menambahkan pesantren.
2. Pemeriksaan operasional dengan waktu pemeriksaan dan keterangan sumber sinyal.
3. Ringkasan tenant yang berasal dari record pusat.
4. Jalan pintas yang dikelompokkan menurut pekerjaan: tenant/modul, komunikasi/dukungan, dan pengembangan.

Gunakan label “Layanan diaktifkan” atau “Dinonaktifkan”, bukan “sedang aktif/online”. Tambahkan keterangan bahwa status adalah konfigurasi yang tercatat, bukan telemetry aktivitas.

Jangan menampilkan MRR, tunggakan invoice, status tiket, uptime, atau notifikasi seolah-olah tersedia bila sumber datanya belum terverifikasi. Tampilkan keadaan gagal secara eksplisit, dengan langkah pemulihan.

Gaya dashboard mengikuti minimalisme institusional dari `MASTER.md`: permukaan terang yang tenang, emerald untuk tindakan utama, garis pemisah tipis, dan tanpa gradien dekoratif. Muat ulang menjalankan pemeriksaan lewat Livewire, menampilkan status memuat yang stabil, dan menghormati `prefers-reduced-motion`.

Penghitungan total, tenant aktif, dan tenant nonaktif menggunakan satu query agregat. Hindari poll otomatis pada dashboard agar layanan pendukung tidak terus dipanggil hanya karena halaman dibiarkan terbuka.

## Interaksi dan aksesibilitas

- Semua aksi berupa tautan dengan label yang menjelaskan tujuan; ukuran minimum 44px untuk target utama.
- Status memakai teks, bukan warna saja.
- Layout satu kolom di ponsel dan dua kolom pada layar lebar; tidak memakai grafik sebagai pengganti angka dan label.
- Hormati tema terang/gelap Filament melalui token utilitas yang tersedia.
