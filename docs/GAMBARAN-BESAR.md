# Gambaran Besar — Platform Pesantren White-Label

> **Status:** Terkunci v1.0 · 2026-06-02 · Pemilik: Hamdan
> Dokumen ini adalah *pintu masuk* seluruh proyek. Baca ini dulu sebelum dokumen lain.

---

## Visi Satu Kalimat

> **Sistem Pesantren yang distandarisasi tetapi fleksibel** — satu platform yang bisa menyesuaikan diri ke kebutuhan & kebijakan internal tiap pesantren, tanpa mengubah kode.

Asy-Syifaa Wal Mahmuudiyyah adalah **pesantren pertama** yang memakainya — sekaligus **portofolio hidup** yang menjual produk ini ke pesantren lain.

---

## Masalah yang Dipecahkan

Pesantren menjalankan operasional (pendaftaran, keuangan, akademik, asrama, hafalan) lewat buku manual, spreadsheet, dan grup WhatsApp. Akibatnya: data ganda, tunggakan SPP tinggi, dan wali "buta" terhadap perkembangan anaknya. Belum ada sistem terpadu yang **murah, fleksibel, dan berbahasa Indonesia** yang pas untuk beragam model pesantren.

---

## Tiga Prinsip Inti (Jantung Seluruh Keputusan)

1. **Satu Sistem, Banyak Wajah.** Website, app wali, panel staff, app keuangan — semua hanya *tampilan* di atas satu backend, satu login, satu database. Audiens baru = tambah peran, bukan tambah sistem. *(Inilah obat penyakit v1 yang punya 4 codebase terpisah.)*

2. **Configuration over Customization.** Perbedaan kebijakan antar-pesantren hidup di *pengaturan*, bukan *kode*. Asy-Syifaa hanyalah "set pengaturan pertama". Inilah yang membuat satu produk bisa dijual ke ribuan pesantren berbeda.

3. **Modular — Satu Kali Instal, Modul Sesuai Kebutuhan.** Tiap fungsi (PPDB, Keuangan, Akademik, Asrama, Tahfidz, dst) adalah modul independen yang bisa dinyalakan/dimatikan per pesantren. Error di satu modul tidak menjatuhkan yang lain.

---

## Model Bisnis Singkat

SaaS B2B: pesantren berlangganan platform. Harga **fleksibel per kerjasama** (per santri / paket flat / nego / gratis untuk mitra) lewat mesin paket yang bisa dikonfigurasi. Pendapatan dari langganan + modul tambahan. Detail di `BLUEPRINT-BISNIS.md`.

---

## Peta Jalan (Roadmap)

| Tahap | Fokus | Hasil |
|---|---|---|
| **T0** | Fondasi: multi-tenant, auth, RBAC, modul on/off, Docker, CI | Kerangka terbukti benar (test isolasi hijau) |
| **T1** | Port modul Kesantrian & Keuangan dari v1 | Asy-Syifaa kelola santri & tagihan nyata |
| **T2** | PPDB + Notifikasi WA | Pendaftaran online + broadcast tagihan |
| **T3** | Portal Wali + modul Akademik/Asrama/Kesehatan/Tahfidz | Wali pantau anak; operasional lengkap |
| **T4** | Billing SaaS + jual ke pesantren kedua | Mulai komersialisasi |

**Disiplin emas:** Asy-Syifaa harus stabil dulu (T1–T3) sebelum jualan (T4). Pesantren sendiri = bukti hidup yang menjual produk.

---

## Peta Dokumen Proyek

| Dokumen | Isi | Kapan dibaca |
|---|---|---|
| **GAMBARAN-BESAR.md** (ini) | Visi, prinsip, roadmap | Pertama, & saat bingung arah |
| **BLUEPRINT-ARSITEKTUR.md** | Blueprint *teknis* — stack, multi-tenant, modul, keamanan, testing, deploy | Sebelum & selama ngoding |
| **BLUEPRINT-BISNIS.md** | Blueprint *bisnis* — pasar, harga, pendapatan, go-to-market, risiko | Saat keputusan komersial |
| **RENCANA-T0.md** | Langkah eksekusi fondasi + prompt AI siap-pakai | Saat mulai membangun |
| **PRD.md** | Detail kebutuhan fungsional tiap modul (dari v1) | Saat membangun modul tertentu |

---

## Definisi Sukses

- **Teknis:** satu kali `docker compose up` menyalakan seluruh sistem; modul bisa on/off; data antar-pesantren terisolasi mutlak.
- **Bisnis:** Asy-Syifaa berjalan penuh sebagai portofolio; minimal 1 pesantren lain berlangganan dalam 12 bulan pasca-T4.
- **Pribadi (solo dev):** sistem bisa dikelola & dikembangkan sendiri dengan bantuan AI tanpa menjadi kacau seperti v1.
