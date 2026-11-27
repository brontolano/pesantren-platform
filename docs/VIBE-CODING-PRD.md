# Bekerja dengan Codex untuk mengeksekusi PRD

## Tujuan panduan

`AGENTS.md` memberi Codex konteks dan batas kerja setiap kali folder proyek ini dibuka. Panduan ini membantu Anda memberi tugas yang terukur, meninjau rencana, lalu meminta perubahan sedikit demi sedikit.

Tidak perlu berpindah ke mode lain atau memasang plugin khusus. Buka folder `pesantren-online-27112026` sebagai proyek di Codex. Codex membaca `AGENTS.md` dari folder itu.

PRD baru ada di `docs/PRD-REDESIGN-PESANTREN-ONLINE.md`. Statusnya masih draf 0.1. Isinya mengarahkan produk, belum menjadi persetujuan untuk membangun semua modul sekaligus.

## Siklus kerja

Kerjakan satu alur pengguna dalam satu tugas. Hindari meminta Codex membangun seluruh PRD dalam satu instruksi.

1. **Pilih hasil yang terlihat.** Contoh: superadmin dapat mengubah alamat tenant dan melihat hasilnya pada ringkasan tenant.
2. **Minta pemetaan tanpa mengubah kode.** Codex perlu menghubungkan requirement PRD dengan halaman, model, izin, dan modul yang sudah ada.
3. **Tinjau rencana.** Pastikan cakupannya kecil, data tenant terlindungi, dan keputusan produk yang belum jelas ditandai.
4. **Minta implementasi setelah rencana disetujui.** Codex mengubah alur itu saja dan memberi tahu bagian yang belum selesai.
5. **Minta pemeriksaan yang sesuai.** Sebutkan pemeriksaan yang diizinkan, terutama jika perlu menjalankan Docker, test, atau build yang berat.
6. **Tinjau hasil sebelum GitHub atau produksi.** Periksa ringkasan perubahan dan minta commit, push, atau deploy secara terpisah saat sudah siap.

## Prompt awal yang bisa langsung dipakai

```text
Saya ingin mengeksekusi PRD baru Pesantren.online secara bertahap.

Baca AGENTS.md, docs/PRD-REDESIGN-PESANTREN-ONLINE.md, dan dokumen kode yang terkait. Jangan ubah berkas dulu.

Fokus pertama: alur superadmin untuk mengelola profil, alamat, kontak, dan status domain tenant. Petakan requirement TEN-01 sampai TEN-04 ke implementasi yang sudah ada. Tunjukkan:
1. alur pengguna yang sekarang dan masalah yang masih ada;
2. berkas, model, halaman, izin, dan modul yang terlibat;
3. satu irisan kerja terkecil yang memberi hasil nyata;
4. kriteria penerimaan dan risiko isolasi tenant;
5. keputusan produk yang masih saya perlu jawab;
6. pemeriksaan yang disarankan, dengan perkiraan kebutuhan resource.

Jangan mulai mengubah kode sampai saya menyetujui rencana.
```

Prompt ini meminta Codex mulai dari pemetaan, bukan langsung menulis banyak kode. Anda bisa mengganti fokus dan ID requirement setelah melihat hasilnya.

## Prompt implementasi

Setelah rencana disetujui, lanjutkan di chat yang sama:

```text
Saya setujui irisan kerja yang tadi. Implementasikan irisan itu saja sesuai AGENTS.md dan pola yang sudah ada.

Jaga isolasi tenant dan otorisasi server. Jangan mengubah alur lain atau membuat pilihan untuk keputusan PRD yang belum disetujui. Pertahankan perubahan saya yang sudah ada.

Sebelum selesai, periksa diff dan jelaskan berkas yang berubah, perilaku baru, kriteria penerimaan yang terpenuhi, serta bagian yang belum selesai. Jangan commit, push, atau deploy.
```

## Prompt pemeriksaan

Jalankan tahap ini saat Anda siap memeriksa hasil. Ini secara jelas meminta Codex menjalankan pemeriksaan:

```text
Tinjau perubahan untuk requirement [ID PRD]. Cari bug, risiko lintas tenant, masalah izin, kondisi kosong/gagal, dan dampak ke data lama.

Tampilkan dahulu perintah pemeriksaan yang paling ringan dan apa yang dibuktikan masing-masing. Jalankan pemeriksaan yang relevan untuk irisan ini, lalu laporkan hasil sebenarnya. Jangan menyalakan seluruh stack lokal atau menjalankan rangkaian test penuh jika tidak dibutuhkan.
```

## Cara memecah roadmap PRD

| Tahap PRD | Hasil yang dikejar | Contoh requirement |
|---|---|---|
| 0. Validasi | Pastikan tugas nyata, istilah, dan keputusan terbuka dipahami bersama pesantren uji. | Tugas mengubah kontak, menyiapkan situs, meminta bantuan, dan memahami modul. |
| 1. Fondasi | Superadmin mengelola tenant, alamat, kontak, domain, onboarding, izin, serta audit. | `TEN-01` sampai `TEN-04`; ringkasan tenant sesuai prioritas berikutnya. |
| 2. Situs tenant | Admin tenant mengelola profil publik dan alur draf, pratinjau, lalu terbit. | `CMS-01` sampai `CMS-03`. |
| 3. Layanan platform | Tenant menerima pengumuman, membuka bantuan, dan mengelola aktivasi modul. | `COM-01`, `COM-02`, `SUP-01`, `SUP-02`, `MOD-01`, `MOD-02`. |
| 4. Kontribusi dan skala | Menambah kemampuan setelah model layanan, penerima, tata kelola, dan aturan diputuskan. | `DON-01` sampai `DON-04`; jangan aktifkan pembayaran sebelum keputusan `DON-04`. |

Urutan ini mengikuti PRD. Codex tetap perlu memeriksa kode yang ada karena beberapa kemampuan mungkin sudah tersedia sebagian.

## Tanda sebuah irisan siap ditinjau

- Pengguna dan tugasnya jelas.
- Kriteria penerimaan menyebut hasil yang dapat diamati.
- Akses diuji di server sesuai peran dan tenant.
- Perubahan tidak membocorkan data tenant lain.
- Form memiliki validasi dan pesan gagal yang bisa dipahami.
- UI punya keadaan kosong, proses, berhasil, dan gagal yang sesuai.
- Migrasi mempertahankan data lama atau menjelaskan langkah transisi.
- Pemeriksaan dijalankan hanya setelah diminta dan dilaporkan sesuai hasil sebenarnya.

## Hal yang harus diputuskan oleh pemilik produk

PRD masih memuat pertanyaan terbuka tentang pesantren uji, arti tenant aktif, kanal komunikasi, kewenangan support, batas monitoring, model biaya, dan kontribusi sukarela. Codex boleh mengerjakan irisan yang tidak bergantung pada keputusan itu. Codex harus mengangkat keputusan sebelum mengunci alur yang berdampak pada uang, privasi, data tenant, atau cara layanan dijalankan.

## Rujukan proyek

- `AGENTS.md`: petunjuk otomatis untuk Codex di proyek ini.
- `docs/PRD-REDESIGN-PESANTREN-ONLINE.md`: kebutuhan produk baru dan kriteria penerimaannya.
- `docs/BLUEPRINT-ARSITEKTUR.md`: batas arsitektur dan tenancy.
- `docs/LOCAL-TESTING.md`: pemeriksaan serta cara membuka aplikasi lokal.
- `docs/DEPLOY-VPS.md`: kondisi deploy produksi yang sudah diverifikasi.
- `.unlazy/prd-pesantren-online-20261127/corpus/graphify-out/GRAPH_REPORT.md`: peta hubungan kode yang dibuat dari baseline `cf87b257`; buka sumber aktual sebelum mengubah kode karena peta mungkin tertinggal dari branch sekarang.
