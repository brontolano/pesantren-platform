# Rencana Eksekusi — Tahap T0: Fondasi

> **Acuan:** `BLUEPRINT-ARSITEKTUR.md` (konstitusi). Tiap prompt AI di bawah WAJIB diawali dengan menyuruh AI membaca blueprint dulu.
> **Tujuan T0:** Membangun *kerangka kosong* multi-tenant yang bisa login & isolasi data — SEBELUM satu fitur pesantren pun dibuat. Ini membuktikan arsitektur Anda benar.
> **Mengapa penting:** Kalau fondasi salah, semua modul di atasnya rapuh — persis penyakit v1. T0 adalah asuransi termurah.

---

## Definition of Done (T0 dianggap selesai bila SEMUA ini hijau)

1. `docker compose up` menyalakan seluruh sistem (app, PostgreSQL, Redis, Traefik) dengan satu perintah.
2. Bisa membuat 2 tenant (mis. `asy-syifaa.localhost` & `demo.localhost`) lewat 1 perintah artisan.
3. Bisa login sebagai user di tenant masing-masing (satu sistem auth).
4. Peran (role) berfungsi: user tanpa izin tidak melihat menu terlarang.
5. **Test isolasi tenant HIJAU** — user tenant A mustahil melihat data tenant B.
6. Modul bisa di-on/off per-tenant lewat tabel `tenant_modules`.
7. CI GitHub Actions menjalankan test otomatis tiap push; deploy ditolak bila test merah.

Jangan lanjut ke T1 (modul pesantren) sebelum 7 poin ini tercapai.

---

## Langkah 0 — Persiapan Alat (sekali saja)

Anda tidak perlu jago PHP — AI yang menulis kode, Anda yang mengarahkan & menguji. Yang perlu dipasang di komputer:

| Alat | Fungsi | Catatan |
|---|---|---|
| Docker Desktop | Menjalankan seluruh sistem | Wajib — ini "satu kali instal" Anda |
| Git + akun GitHub | Simpan kode & CI/CD | Repo privat dulu |
| Editor AI (Cursor / VS Code + Claude) | Tempat AI menulis kode | Cursor paling ramah pemula |
| (opsional) DBeaver | Lihat isi database | Untuk verifikasi visual |

> Tidak perlu install PHP/PostgreSQL/Node langsung di komputer — semua jalan di dalam Docker.

---

## Alur Kerja Tiap Langkah (ulangi pola ini)

1. **Buka sesi AI baru**, tempel prompt langkah tersebut.
2. AI menghasilkan kode → Anda review sekilas (apakah patuh blueprint?).
3. Jalankan & uji (perintah ada di tiap langkah).
4. Bila jalan → `git commit`. Bila error → tempel pesan error ke AI, perbaiki.
5. Baru lanjut langkah berikutnya. **Satu langkah tuntas dulu.**

---

## Langkah 1 — Project Laravel + Paket Inti

**Tujuan:** Project Laravel 12 baru dengan semua paket fondasi terpasang.

**Prompt untuk AI Agent:**
```
Baca file BLUEPRINT-ARSITEKTUR.md lebih dulu dan patuhi sepenuhnya.

Buatkan project Laravel 12 baru bernama "pesantren-platform". Pasang dan
konfigurasikan paket berikut sesuai dokumentasi resmi versi terbaru:
- filament/filament (panel admin)
- stancl/tenancy (multi-tenant)
- spatie/laravel-permission (RBAC)
- nwidart/laravel-modules (modul independen)
- inertiajs + vue 3 (frontend) via Laravel starter resmi
- laravel/sanctum (auth API)

Gunakan PostgreSQL sebagai database. Jangan tulis logika bisnis apa pun
dulu — hanya kerangka project yang bersih dan terkonfigurasi. Jelaskan tiap
perintah dengan bahasa sederhana untuk pemula.
```

**Uji:** Project terbuat, `composer install` sukses tanpa error.

---

## Langkah 2 — Docker Compose (Satu Kali Instal)

**Tujuan:** Seluruh sistem hidup dengan `docker compose up`.

**Prompt untuk AI Agent:**
```
Sesuai BLUEPRINT-ARSITEKTUR.md §7, buatkan konfigurasi Docker Compose untuk
project ini berisi service: app (Laravel + PHP 8.3), PostgreSQL 16, Redis 7,
dan Traefik (reverse proxy + auto-SSL untuk subdomain).

Siapkan agar subdomain lokal seperti asy-syifaa.localhost dan demo.localhost
mengarah ke app. Sertakan file .env contoh dan instruksi menjalankan langkah
demi langkah untuk pemula. Pastikan `docker compose up` cukup untuk
menyalakan semuanya.
```

**Uji:** `docker compose up` → buka `http://localhost` → halaman Laravel muncul.

---

## Langkah 3 — Multi-Tenancy + Identifikasi Subdomain

**Tujuan:** Sistem mengenali tenant dari subdomain (Hukum #3 blueprint).

**Prompt untuk AI Agent:**
```
Sesuai BLUEPRINT-ARSITEKTUR.md §3, konfigurasikan stancl/tenancy dengan
strategi SINGLE-DATABASE (row-level isolation, bukan database-per-tenant).

- Identifikasi tenant lewat subdomain (asy-syifaa.localhost = tenant A).
- Buat model Tenant beserta migrasi tabel central.
- Buat command artisan "tenant:create {nama} {subdomain}" untuk membuat
  tenant baru beserta data awalnya.

Belum perlu data pesantren — fokus mekanisme tenant saja.
```

**Uji:** Jalankan `php artisan tenant:create "Asy-Syifaa" asy-syifaa` dan `... "Demo" demo` → 2 tenant tercipta di database.

---

## Langkah 4 — Auth Tunggal + RBAC

**Tujuan:** Satu sistem login untuk semua peran (Hukum #2 blueprint).

**Prompt untuk AI Agent:**
```
Sesuai BLUEPRINT-ARSITEKTUR.md §1 (Hukum #2) dan §8.8, buat SATU sistem auth:
- Login Filament untuk staff (admin/TU/keuangan/guru).
- Integrasi spatie/laravel-permission untuk peran & izin.
- Buat peran contoh: super_admin, tu, keuangan, guru, wali.
- User yang login HANYA melihat menu sesuai izinnya.
- Setiap User terikat ke satu tenant (kecuali super_admin platform).

Jangan buat sistem login kedua untuk modul mana pun. Satu auth saja.
```

**Uji:** Buat user di tiap tenant, login, pastikan peran berbeda melihat menu berbeda.

---

## Langkah 5 — Sistem Modul On/Off

**Tujuan:** Modul bisa dinyalakan/dimatikan per tenant (§4 & §12 blueprint).

**Prompt untuk AI Agent:**
```
Sesuai BLUEPRINT-ARSITEKTUR.md §4 dan §12, siapkan sistem modul memakai
nwidart/laravel-modules:
- Buat tabel "tenant_modules" (tenant_id, module_name, is_active).
- Buat middleware/helper yang mengecek apakah sebuah modul aktif untuk tenant
  saat ini; bila tidak aktif, menu & rute modul itu disembunyikan.
- Buat satu modul contoh KOSONG bernama "Hello" untuk membuktikan mekanisme
  on/off bekerja (tanpa logika bisnis).

Juga siapkan kerangka KOSONG tabel billing sesuai §12: plans,
tenant_subscriptions, platform_invoices (migrasi saja, tanpa logika).
```

**Uji:** Nonaktifkan modul "Hello" untuk tenant Demo → menunya hilang di Demo tapi tetap ada di Asy-Syifaa.

---

## Langkah 6 — Master Wilayah (Data Referensi)

**Tujuan:** Data wilayah Indonesia, dipakai bersama semua tenant (§3).

**Prompt untuk AI Agent:**
```
Sesuai BLUEPRINT-ARSITEKTUR.md, buat modul Master Wilayah (Province, City,
District, Village) sebagai data CENTRAL (dibaca semua tenant, tidak
diduplikasi per-tenant). Sediakan seeder/importer dan endpoint untuk
pemilih wilayah bertingkat (provinsi -> kota -> kecamatan -> desa).
```

**Uji:** Pemilih wilayah bertingkat berfungsi.

---

## Langkah 7 — TEST ISOLASI TENANT (Paling Penting)

**Tujuan:** Membuktikan tenant A tidak bisa melihat data tenant B. Ini test keamanan #1 (§6 blueprint).

**Prompt untuk AI Agent:**
```
Sesuai BLUEPRINT-ARSITEKTUR.md §6, tulis automated test (Pest/PHPUnit) yang:
1. Membuat 2 tenant + masing-masing 1 user & 1 data contoh (mis. record di
   modul Hello).
2. Login sebagai user tenant A, lalu pastikan query HANYA mengembalikan data
   tenant A — TIDAK PERNAH data tenant B.
3. Test GAGAL bila ada kebocoran lintas-tenant.

Test ini wajib hijau dan jadi syarat kelulusan T0.
```

**Uji:** `php artisan test` → test isolasi HIJAU.

---

## Langkah 8 — CI/CD GitHub Actions

**Tujuan:** Test jalan otomatis tiap push; deploy ditolak bila merah (§6–§7).

**Prompt untuk AI Agent:**
```
Sesuai BLUEPRINT-ARSITEKTUR.md §6 dan §7, buat workflow GitHub Actions yang:
- Setiap push ke branch main: jalankan `php artisan test`.
- Bila test merah, build GAGAL (blokir deploy).
- (Opsional) langkah deploy ke VPS via SSH bila test hijau.
Sertakan instruksi setup secret/environment untuk pemula.
```

**Uji:** Push ke GitHub → Actions berjalan → test hijau → centang hijau muncul.

---

## Verifikasi Akhir T0

Cek ulang ke-7 poin Definition of Done di atas. Bila semua hijau:
- Commit & tag `v0-foundation`.
- T0 SELESAI. Arsitektur Anda terbukti benar.

Baru setelah ini, masuk **Tahap T1** (port modul Kesantrian & Keuangan dari v1 ke fondasi baru) — dengan v1 sebagai referensi logika, bukan disalin mentah.

---

## Resep Modul — Cetakan Baku untuk T1 dan Seterusnya

Inilah hasil terpenting T0 untuk Anda: **setelah fondasi siap, tiap modul baru dibangun dengan pola yang SAMA.** Konsistensi inilah yang membuat "membangun modul satu per satu" jadi enak dan tidak kacau seperti v1.

**Anatomi baku tiap modul** (semua modul wajib punya 7 bagian ini):

| Bagian | Isi | Aturan |
|---|---|---|
| 1. Migrasi & Model | Tabel + Eloquent model | WAJIB pakai trait `BelongsToTenant`; tanpa query DB mentah |
| 2. Pengaturan (config) | Knob kebijakan yang bisa beda antar-pesantren | Sesuai §14 — jangan hardcode aturan |
| 3. Service | Logika bisnis | Logika ada di sini, bukan di controller/view |
| 4. Tampilan | Filament resource (staff) / halaman Inertia-Vue (wali) | Satu auth, ikut RBAC |
| 5. Policy (RBAC) | Izin akses per peran | User tanpa izin tidak melihat modul |
| 6. Test | Feature test + test isolasi tenant | Modul tak "selesai" tanpa test hijau |
| 7. Registrasi | Daftar di `tenant_modules` (on/off) | Modul bisa dinyalakan per pesantren |

**Prompt template untuk membangun modul apa pun** (ganti `[NAMA MODUL]` & detailnya):
```
Baca BLUEPRINT-ARSITEKTUR.md, BLUEPRINT-BISNIS.md, dan bagian modul terkait di
PRD.md lebih dulu. Patuhi sepenuhnya.

Bangun modul "[NAMA MODUL]" mengikuti Anatomi Baku Modul (7 bagian) di RENCANA-T0.md:
1. Migrasi & model dengan trait BelongsToTenant (isolasi tenant wajib).
2. Identifikasi knob pengaturan yang mungkin BERBEDA antar-pesantren, dan
   jadikan configurable (Configuration over Customization, §14) — JANGAN
   hardcode kebijakan Asy-Syifaa.
3. Service untuk logika bisnis.
4. Tampilan: [Filament untuk staff / halaman Inertia-Vue untuk wali].
5. Policy RBAC untuk peran: [sebutkan peran].
6. Tulis feature test + pastikan test isolasi tenant tetap hijau.
7. Daftarkan modul di tabel tenant_modules agar bisa on/off.

Kerjakan SATU modul ini sampai tuntas & test hijau. Jangan menyentuh modul lain.
Jangan menambah bahasa/framework/database/auth baru.

Referensi: logika domain modul ini sudah ada di v1 — gunakan sebagai acuan
pengetahuan, tapi tulis ulang bersih sesuai aturan baru.
```

**Uji kelayakan tiap modul sebelum dianggap selesai (Uji Lakmus §14):**
> "Kalau pesantren lain minta aturan berbeda untuk modul ini — cukup ubah pengaturan, atau harus ubah kode?" Bila "ubah kode" → perbaiki desainnya dulu.

---

## Catatan untuk Solo Developer

- **Jangan lompat langkah.** Tiap langkah tuntas & hijau dulu. Inilah disiplin yang absen di v1.
- **Tiap sesi AI baru, selalu suruh baca blueprint dulu.** Itu yang menjaga konsistensi kode antar-sesi.
- **Bila AI menyarankan menambah bahasa/framework/database/auth baru → TOLAK.** Langgar konstitusi.
- **Simpan setiap keberhasilan dengan `git commit`.** Bisa mundur bila ada yang rusak.
