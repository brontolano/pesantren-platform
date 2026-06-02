# Blueprint Arsitektur — Platform Pesantren White-Label (SaaS)

> **Status:** Konstitusi Proyek v1.0 · Tanggal: 2026-06-02
> **Pemilik:** Hamdan (Solo Developer + AI Agent)
> **Sifat dokumen:** Ini adalah *aturan tertinggi* proyek. Setiap sesi AI (Claude, Cursor, dll.) WAJIB membaca dokumen ini lebih dulu dan tidak boleh melanggarnya. Tujuannya: mencegah kekacauan yang membunuh sistem v1 (beda bahasa, beda keamanan, tidak terintegrasi).

---

## 0. Keputusan yang Sudah Dikunci (Tidak Diubah Tanpa Alasan Kuat)

| Keputusan | Pilihan Final | Alasan |
|---|---|---|
| **Tujuan produk** | SaaS komersial — dijual ke banyak pesantren | Multi-tenant wajib; isolasi data = kewajiban hukum |
| **Arsitektur** | Satu codebase: **Modular Monolith** | Obat untuk penyakit 4-codebase v1 |
| **Bahasa** | **Satu bahasa: PHP** (backend) + Vue (tampilan) | Akar kegagalan v1 = beda bahasa & beda keamanan |
| **Keamanan** | **Satu sistem auth** untuk semua peran | Tidak ada lagi dualisme auth |
| **Database** | **Satu PostgreSQL**, isolasi per-tenant via `tenant_id` | Sederhana, aman untuk solo dev |
| **Strategi rilis** | Desain multi-tenant, **luncurkan single-tenant dulu** (Asy-Syifaa sebagai tenant percontohan) | Hindari bakar kas untuk pelanggan yang belum ada |

---

## 1. Tiga Hukum Konstitusi (Non-Negotiable)

Aturan ini menjawab langsung 4 penyebab kegagalan v1.

1. **SATU SISTEM.** Tidak ada aplikasi/API/database terpisah. Semua (admin, website publik, app wali) hidup dalam satu codebase Laravel. Vue hanya lapisan tampilan via Inertia — bukan aplikasi terpisah.

2. **SATU KEAMANAN.** Hanya ada satu sistem login & otorisasi (Laravel Sanctum + Spatie Permission). Tidak boleh ada modul yang membuat sistem auth sendiri.

3. **ISOLASI TENANT MUTLAK.** Setiap baris data milik satu tenant. Tidak ada query yang boleh menembus batas tenant. Pelanggaran = bug keamanan tingkat tertinggi (bukan bug biasa).

---

## 2. Stack Teknologi Final (Terverifikasi Juni 2026)

| Lapisan | Teknologi | Versi | Peran |
|---|---|---|---|
| **Backend & Logika** | Laravel | 12.x | Inti sistem, business logic, keamanan |
| **Panel Admin** | Filament | 5.x | CRUD admin/TU/keuangan/guru (MFA bawaan, cepat) |
| **Tampilan (frontend)** | Vue 3 via **Inertia.js** | terbaru | App Wali + website — tampilan Vue yang Anda suka, TANPA API terpisah |
| **Database** | PostgreSQL | 16+ | Sumber kebenaran tunggal |
| **Cache & Queue** | Redis | 7+ | Antrian broadcast WA, cache |
| **Multi-tenant** | `stancl/tenancy` | v3+ | Isolasi data antar-pesantren |
| **Modular** | `nwidart/laravel-modules` | terbaru | Tiap modul = paket independen on/off |
| **Hak akses** | `spatie/laravel-permission` | terbaru | RBAC per peran |
| **WA Gateway** | n8n + WAHA | — | Notifikasi (fallback: WA Business API resmi sebagai P2) |
| **Infra** | Docker + Traefik (auto-SSL) + GitHub Actions | — | Deploy & CI/CD |

> **Kenapa Inertia + Vue?** Di v1, Vue & Laravel adalah dua aplikasi yang "ngobrol" lewat API — di sinilah dua sistem keamanan lahir & bentrok. Inertia membuat Vue hidup *di dalam* Laravel: satu login, satu repo, satu deploy, tapi tampilan tetap Vue modern.

---

## 3. Arsitektur Multi-Tenant — Cara Isolasi Data

**Strategi yang dipilih: Single-Database, Row-Level Isolation.** Semua pesantren berbagi satu database; setiap tabel punya kolom `tenant_id`. Paling sederhana & murah untuk solo dev, cukup aman bila disiplin.

**Aturan wajib:**

- Setiap model domain WAJIB memakai trait `BelongsToTenant` (dari `stancl/tenancy`). Ini otomatis menyaring semua query ke tenant aktif.
- **DILARANG** memakai query DB mentah (`DB::table(...)`) untuk data domain — trait tenant tidak melindungi query mentah. Selalu lewat Eloquent Model.
- Identifikasi tenant via subdomain: `asy-syifaa.app.com`, `pesantren-b.app.com`. Tenant aktif ditentukan di awal request.
- Data referensi bersama (Master Wilayah Indonesia) disimpan di level *central*, dibaca semua tenant — tidak diduplikasi per-tenant.

**Jalur upgrade keamanan (kalau pelanggan besar minta):** bisa pindah ke *database-per-tenant* tanpa ubah kode aplikasi, karena `stancl/tenancy` mendukung keduanya. Disimpan sebagai opsi masa depan, bukan sekarang (hindari over-engineering).

---

## 4. Peta Modul — Batas & Independensi

Setiap modul adalah **paket terisolasi** (folder `Modules/NamaModul/`) dengan migrasi, model, controller, dan view sendiri. Bisa diaktifkan/nonaktifkan per-tenant lewat tabel `tenant_modules` (feature flag).

| Modul | Isi | Prioritas | Bergantung pada |
|---|---|---|---|
| **Core** | Tenant, User, Auth, RBAC, Settings, Branding, Master Wilayah | Wajib (selalu aktif) | — |
| **CMS/Website** | Profil, artikel, galeri, form PPDB publik | P0 | Core |
| **PPDB** | Pendaftaran, data calon, ujian seleksi, dokumen, tagihan daftar, registrasi ulang | P0 | Core, Keuangan |
| **Keuangan** | Tagihan (Masehi+Hijriah), pembayaran, tabungan, laporan | P0 | Core |
| **Kesantrian** | Master santri, status, **engine penempatan kelas (mode Mu'adalah / linear — configurable)** | P0 | Core, PPDB |
| **Akademik** | Nilai, rapor, jadwal, periode akademik | P1 | Kesantrian |
| **Asrama** | Kamar, kapasitas, penempatan | P1 | Kesantrian |
| **Tahfidz** | Hafalan, ziyadah/muraja'ah, penilaian | P1 | Kesantrian |
| **Kesehatan & Konseling** | Riwayat kesehatan, sesi konseling | P1 | Kesantrian |
| **Kegiatan & Perizinan** | Kegiatan, presensi, izin keluar, kunjungan ortu | P1 | Kesantrian |
| **Notifikasi** | Template, broadcast WA, log, webhook | P0 | Core |
| **Portal Wali** | Dashboard wali, keuangan, monitoring santri | P1 | Kesantrian, Keuangan |
| **Billing SaaS** | Langganan pesantren ke platform Anda | P1 (Tahap T4) | Core |

> **Catatan migrasi v1 → arsitektur baru.** Modul CMS, PPDB, Keuangan (calon santri), dan Kesantrian sudah dibangun di v1 — logika domainnya diport, bukan dibuang. Akademik, Asrama, Kesehatan, Tahfidz, Kegiatan belum dibangun. Penempatan kelas Mu'adalah (loncat/turun) WAJIB dibuat sebagai mode configurable (lihat §14), bukan aturan keras.

**Aturan independensi modul (kunci agar mudah diperbaiki bila error):**

1. Modul **tidak boleh** memanggil tabel modul lain langsung. Komunikasi antar-modul lewat *Service* atau *Event*, bukan query silang.
2. Mematikan satu modul **tidak boleh** membuat modul lain error. Cek ketergantungan di tabel di atas.
3. Setiap modul punya foldernya sendiri — error di Keuangan tidak menyentuh file PPDB.

---

## 5. Alur Kerja AI Agent — Cara Membuat Modul Tanpa Saling Bentrok

Ini protokol yang menjawab penyebab kegagalan v1 ("AI menghasilkan kode tak konsisten"). Kerjakan **satu modul sampai tuntas & stabil** sebelum modul berikutnya. Jangan paralel.

**Siklus per modul (ulangi untuk tiap modul):**

1. **Brief** — Tunjukkan dokumen ini + PRD modul terkait ke AI. Wajib di awal tiap sesi.
2. **Skema dulu** — Minta AI buat migrasi database + model (dengan `BelongsToTenant`) lebih dulu. Review skema sebelum lanjut.
3. **Logika** — Service & controller untuk business logic. Satu modul, satu konteks.
4. **Tampilan** — Filament resource (admin) atau halaman Vue/Inertia (wali).
5. **Test** — AI wajib menulis test (lihat §6) di modul yang sama. **Modul tidak "selesai" tanpa test hijau.**
6. **Kunci** — Commit ke Git dengan pesan jelas. Baru lanjut modul berikutnya.

**Aturan emas untuk AI:**
- Selalu pakai pola Laravel standar (jangan reinvent). Library yang dipilih di §2 punya dokumentasi melimpah → kode AI lebih akurat.
- Satu sesi = satu modul. Konteks fokus = kode konsisten.
- Jika AI menyarankan menambah bahasa/framework/database baru → **TOLAK**. Langgar Hukum #1.

---

## 6. Strategi Testing (Jaring Pengaman)

Tanpa test, v1 Anda "error saat digabung". Test adalah asuransi termurah.

| Jenis Test | Untuk apa | Target |
|---|---|---|
| **Feature test** (Pest/PHPUnit) | Alur penting tiap modul (buat tagihan, daftar PPDB, login wali) | Wajib tiap modul P0 |
| **Tenant isolation test** | Pastikan tenant A tidak bisa lihat data tenant B | **Wajib** — ini test keamanan #1 |
| **Smoke test** | Aplikasi menyala & halaman utama tidak error setelah deploy | Otomatis di CI |

Aturan: **CI/CD menolak deploy jika ada test merah.** Tidak ada pengecualian.

---

## 7. Strategi Deploy (Satu Kali Instal)

Sesuai keinginan Anda: "satu kali instal, modul aktif sesuai kebutuhan."

- **Docker Compose** membungkus: app (Laravel), PostgreSQL, Redis, Traefik. Satu perintah `docker compose up` → seluruh sistem hidup. Bisa lokal maupun online (VPS).
- **Traefik** otomatis mengurus SSL (https) untuk tiap subdomain tenant.
- **GitHub Actions**: push ke `main` → jalankan test → bila hijau → deploy ke VPS otomatis.
- **Onboarding tenant baru** = satu perintah artisan (`php artisan tenant:create`) yang membuat subdomain + data awal. Tidak perlu install ulang.
- **Backup harian** PostgreSQL terjadwal + terenkripsi (data santri = anak di bawah umur).

---

## 8. Sekuensing Eksekusi (Urutan yang Menyelamatkan Kas & Waktu)

| Tahap | Fokus | Output | Selesai bila |
|---|---|---|---|
| **T0** | Fondasi: Core module (tenant, auth, RBAC, wilayah) + Docker + CI | Sistem kosong yang bisa login & multi-tenant | Test isolasi tenant hijau |
| **T1** | Modul Kesantrian + Keuangan (jantung pesantren) | Asy-Syifaa bisa kelola santri & tagihan nyata | Dipakai TU & bendahara sungguhan |
| **T2** | PPDB + Notifikasi WA | Pendaftaran online + broadcast tagihan | Lolos 1 musim PPDB |
| **T3** | Portal Wali (Vue/Inertia) + CMS | Wali lihat data nyata; website publik | ≥60% wali aktif |
| **T4** | Billing SaaS + onboarding pesantren ke-2 | Mulai jualan ke pesantren lain | Pesantren kedua bayar & jalan |

> **Disiplin terpenting:** Jangan sentuh T4 (jualan) sebelum T1–T3 stabil di Asy-Syifaa. Pesantren Anda sendiri adalah bukti hidup yang menjual produk Anda.

---

## 9. Keamanan Data Santri (Wajib — Anak di Bawah Umur)

- Isolasi tenant (§3) + isolasi data wali (wali hanya lihat anaknya sendiri) lewat RBAC.
- Password ter-hash (bcrypt bawaan Laravel). MFA tersedia (bawaan Filament 5) untuk admin.
- Audit log untuk aksi sensitif: verifikasi pembayaran, ubah nilai, ekspor data.
- Backup terenkripsi. Patuhi UU PDP Indonesia (lihat Open Question OQ-5 di PRD — perlu kebijakan privasi sebelum rilis publik).

---

## 10. Yang SECARA SADAR Tidak Dikerjakan Dulu (Anti Over-Engineering)

| Ditunda | Kapan ditinjau |
|---|---|
| Database-per-tenant | Bila ada pelanggan enterprise minta isolasi fisik |
| Payment gateway otomatis (VA/QRIS) | Setelah T2; v1 cukup transfer manual + unggah bukti |
| Microservices / pisah API | **Tidak pernah** — itu kembali ke penyakit v1 |
| Push notification & marketplace | Setelah T3 |
| LMS/e-learning penuh | Di luar lingkup; fokus administrasi |

---

## 11. Banyak Aplikasi = Banyak Wajah, Satu Sistem

Tiap peran boleh punya aplikasinya sendiri (shell/PWA terpisah: app guru, app wali, app keuangan), **tapi semua menyedot dari satu backend, satu login, satu database.** Yang boleh berbeda hanya "kepala" (tampilan & UX). Yang haram berbeda: backend, auth, database.

| Wajah (aplikasi) | Untuk siapa | Disajikan oleh |
|---|---|---|
| Website publik | Pengunjung | Vue/Inertia (anonim) |
| Panel Staff | TU, guru, keuangan, yayasan | Filament — menu beda per **peran**, bukan app beda |
| App Wali (PWA) | Orang tua | Vue/Inertia |
| App Keuangan | Santri/wali | Modul di dalam App Wali, bukan app baru |

**Aturan emas (anti-pengulangan kegagalan v1):**
> Audiens/aplikasi baru = tambah **peran** + tambah **tampilan**, di atas otak & database yang sama. **TIDAK PERNAH** = tambah codebase, auth, atau database baru. "Aplikasi terpisah dengan backend sendiri" = ALARM MERAH, langgar Hukum #1.

Catatan biaya: tiap shell frontend tambahan = pekerjaan tambahan. Tambahkan shell terpisah hanya bila UX-nya benar-benar beda. Untuk staff, satu panel dengan menu per-peran lebih hemat daripada banyak app.

---

## 12. Mesin Billing SaaS — Konfigurable per Kerjasama

Harga **tidak di-hardcode**. Sistem = mesin paket data-driven: **Template Paket** (dipakai ulang) + **Override per-tenant** (deal khusus). Saat override kosong → ikut template; saat diisi → menang. Inilah cara satu sistem melayani "berbagai jenis kebijakan kerjasama".

**Tiga tabel (siapkan kerangka sekarang, isi logika di Tahap T4):**

| Tabel | Isi |
|---|---|
| `plans` | Template: nama, tipe billing (per_santri/flat/custom/gratis), tarif default, modul default |
| `tenant_subscriptions` | tenant_id, plan_id, **override** (harga, modul, diskon), siklus, masa kerjasama, status |
| `platform_invoices` | tenant_id, periode, jumlah, status, bukti bayar — **memakai ulang logika modul Keuangan** |

**Aturan:**
- Toggle "modul aktif" di panel billing = aksi yang sama dengan tabel `tenant_modules` (§4). Menjual modul = mengaktifkan modul.
- Tagihan platform ke pesantren memakai ulang engine Invoice/Payment modul Keuangan — jangan bikin mesin baru (DRY).
- Penagihan = invoice langganan (bulanan/tahunan) + transfer manual. Auto-debit (Midtrans/Xendit) = P2, hanya saat klien >10.

---

## 13. Build vs Buy — Tooling Operasional (Disiplin Solo Developer)

Prinsip: **bangun hanya yang jadi pembeda inti produk Anda (sistem pesantren). Sisanya — pakai yang sudah jadi.** Waktu solo dev = sumber daya paling langka. Jam yang dipakai membangun ticketing = jam yang tidak dipakai membangun produk yang menghasilkan uang.

| Kebutuhan | Bangun / Pakai jadi | Acuan alat | Kapan perlu |
|---|---|---|---|
| Dashboard billing & data tenant | **Bangun (murah)** — sudah dapat gratis dari Filament | Panel Filament Anda sendiri | T4 |
| Monitoring bisnis (MRR, tenant aktif, modul) | **Bangun (murah)** — data sudah ada di DB | Widget Filament | T4 |
| Monitoring server (uptime, CPU/RAM) | **PAKAI JADI** — jangan bangun | Uptime Kuma (gratis, self-host) atau UptimeRobot | Saat online (T0) |
| Log error & observability | **PAKAI JADI** | Sentry (gratis tier) / Laravel Telescope | T1 |
| Ticketing / troubleshooting | **PAKAI JADI** — jangan bangun | Awal: WhatsApp + spreadsheet. Skala: Chatwoot (open-source gratis) | >5 klien |

**Peringatan eksekutif:** Membangun sendiri sistem ticketing & monitoring server adalah jebakan over-engineering klasik — itu masalah yang sudah dipecahkan orang lain & tersedia gratis. Untuk 1–5 klien pertama, troubleshooting cukup via WhatsApp + catatan sederhana. Bangun/integrasi helpdesk hanya saat volume keluhan benar-benar membebani Anda.

---

## 14. Configuration over Customization — Kunci White-Label

Asy-Syifaa berperan ganda: **sistem produksi nyata** + **portofolio/etalase** yang menjual produk ke pesantren lain. Agar peran kedua hidup, satu prinsip wajib dipegang mati-matian.

> **Setiap perbedaan kebijakan antar-pesantren hidup di PENGATURAN (data), bukan di KODE.** Asy-Syifaa bukan versi kode khusus — ia hanya **set pengaturan pertama**.

Jika kebijakan internal pesantren ditanam ke dalam kode, sistem hanya cocok untuk Asy-Syifaa dan **tidak bisa dijual** — tujuan SaaS gagal total.

**Yang WAJIB jadi pengaturan (configurable per-tenant), bukan kode:**

| Area | Contoh perbedaan antar-pesantren |
|---|---|
| Keuangan | Periode (Masehi/Hijriah/semester), jenis & nominal SPP, metode pembayaran, rekening |
| Akademik | Struktur kelas/tingkat, metode & skala penilaian, format rapor |
| Tahfidz | Metode setoran, target hafalan, cara penilaian ziyadah/muraja'ah |
| Kesantrian | Aturan izin, jenis kegiatan, kategori pelanggaran/poin |
| Identitas | Kop surat, logo, nama lembaga, branding, domain |
| Modul | Modul mana yang aktif (lihat §4 & §12) |

**Uji lakmus (wajib tiap membangun fitur):**
> "Kalau pesantren lain minta aturan berbeda — cukup ubah pengaturan, atau harus ubah kode?" Bila jawabannya "ubah kode" → desain salah, perbaiki dulu sebelum lanjut.

**Konsekuensi urutan kerja:** modul dikembangkan **linear** (satu per satu, sampai tuntas) berdasarkan kebutuhan staff nyata Asy-Syifaa — tapi tiap modul dibangun dengan "knob pengaturan", bukan aturan keras. Kebutuhan staff nyata = sumber prioritas (bangun yang benar-benar dipakai), bukan tebakan.

---

*Dokumen ini adalah konstitusi. Bila ada konflik antara saran AI dan dokumen ini, dokumen ini menang. Perbarui hanya dengan keputusan sadar & alasan tertulis.*
