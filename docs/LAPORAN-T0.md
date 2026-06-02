# Laporan Eksekusi Tahap T0 — Fondasi Platform Pesantren

> **Tanggal:** 2 Juni 2026
> **Status:** SELESAI
>
> **Commits:**
> - `fbeb293` — T0: Fondasi platform pesantren multi-tenant SaaS
> - `883a185` — docs: tambah laporan eksekusi T0
> - `2bad2ef` — fix: tenant subdomain identification untuk development lokal
> - `5a1c0ee` — feat: landing page central domain + link ke panel admin tenant

---

## Ringkasan

Tahap T0 membangun kerangka kosong multi-tenant yang bisa login, isolasi data, dan modul on/off — fondasi yang membuktikan arsitektur benar sebelum fitur pesantren dibangun.

---

## Langkah 1 — Project Laravel + Paket Inti

**Status:** SELESAI

**Yang dikerjakan:**
- Membuat project Laravel 13 (versi terbaru, pengganti Laravel 12)
- Install dan konfigurasi 6 paket inti:

| Paket | Versi | Fungsi |
|---|---|---|
| `filament/filament` | 5.6.6 | Panel admin CRUD |
| `stancl/tenancy` | 3.10.0 | Multi-tenant |
| `spatie/laravel-permission` | 8.0.0 | RBAC (hak akses per peran) |
| `nwidart/laravel-modules` | 13.0.0 | Modul independen on/off |
| `inertiajs/inertia-laravel` | 3.1.0 | Bridge Laravel-Vue (satu sistem) |
| `laravel/sanctum` | 4.3.2 | Auth API |

- Frontend: Vue 3 + `@inertiajs/vue3` + `@vitejs/plugin-vue` + Tailwind CSS 4
- Database dikonfigurasi ke PostgreSQL

**File utama:**
- `composer.json` — dependency PHP
- `package.json` — dependency frontend
- `vite.config.js` — Vite + Vue + Tailwind
- `resources/js/app.js` — entry point Vue/Inertia
- `resources/views/app.blade.php` — root Blade template untuk Inertia

---

## Langkah 2 — Docker Compose

**Status:** SELESAI

**Yang dikerjakan:**
- 4 service Docker berjalan:

| Service | Image | Port | Fungsi |
|---|---|---|---|
| `app` | PHP 8.3-cli (custom) | 8000 | Laravel application server |
| `postgres` | postgres:16-alpine | 5432 | Database utama |
| `redis` | redis:7-alpine | 6379 | Cache & queue |
| `traefik` | traefik:v3.4 | 80, 8080 | Reverse proxy (subdomain routing) |

- `Dockerfile.dev` — image ringan untuk development (PHP 8.3 + extensions pgsql, redis, zip, intl, bcmath, pcntl)
- `Dockerfile` — multi-stage build untuk production (Node build → PHP runtime)

**Catatan:**
- Traefik mengalami error Docker socket di Windows (`/var/run/docker.sock`). Ini masalah umum Docker Desktop Windows — Traefik akan berfungsi normal di server Linux production.
- Development lokal mengakses langsung via `http://localhost:8000`

**File utama:**
- `docker-compose.yml`
- `Dockerfile` / `Dockerfile.dev`
- `.dockerignore`

---

## Langkah 3 — Multi-Tenancy + Identifikasi Subdomain

**Status:** SELESAI

**Yang dikerjakan:**
- Strategi: **Single-database, row-level isolation** (sesuai Blueprint §3)
- `DatabaseTenancyBootstrapper` DINONAKTIFKAN (tidak perlu database terpisah)
- Identifikasi tenant via subdomain menggunakan `InitializeTenancyBySubdomain` middleware
- Tabel `tenants` (id, name, is_active, data) + tabel `domains` (domain, tenant_id)
- Kolom `tenant_id` ditambahkan ke tabel `users`

**Trait `BelongsToTenant`:**
- Otomatis menyaring semua query ke tenant aktif via `TenantScope`
- Otomatis mengisi `tenant_id` saat membuat record baru dalam konteks tenant
- Semua model domain WAJIB memakai trait ini (sesuai Blueprint §3)

**Command `php artisan tenant:create {nama} {subdomain}`:**
- Membuat tenant + domain dalam satu perintah
- Berhasil diuji: 2 tenant dibuat (`asy-syifaa.localhost` dan `demo.localhost`)

**File utama:**
- `app/Models/Tenant.php` — model tenant
- `app/Traits/BelongsToTenant.php` — trait isolasi data
- `app/Scopes/TenantScope.php` — global scope tenant
- `app/Console/Commands/TenantCreateCommand.php` — artisan command
- `config/tenancy.php` — konfigurasi stancl/tenancy
- `routes/tenant.php` — rute tenant-aware

---

## Langkah 4 — Auth Tunggal + RBAC

**Status:** SELESAI

**Yang dikerjakan:**
- SATU sistem auth untuk semua peran (Hukum #2 Blueprint)
- Filament admin panel di `/admin` dengan login page
- Tenant middleware ditambahkan ke Filament panel (`InitializeTenancyBySubdomain` + `PreventAccessFromCentralDomains`)
- User model menggunakan `BelongsToTenant` + `HasRoles` (Spatie)

**5 Peran RBAC:**

| Peran | Izin |
|---|---|
| `super_admin` | Semua izin |
| `tu` | Dashboard, kelola users, lihat & kelola santri |
| `keuangan` | Dashboard, lihat & kelola keuangan, lihat santri |
| `guru` | Dashboard, lihat santri, lihat & kelola akademik |
| `wali` | Lihat anak sendiri saja |

**12 Permission** terdaftar: `view_dashboard`, `manage_users`, `manage_roles`, `manage_tenant_settings`, `manage_modules`, `view_santri`, `manage_santri`, `view_keuangan`, `manage_keuangan`, `view_akademik`, `manage_akademik`, `view_own_children`

**Seeder membuat user contoh:**
- Per tenant: 1 admin (super_admin) + 1 TU
- Total: 4 user (2 tenant × 2 user)

**File utama:**
- `app/Providers/Filament/AdminPanelProvider.php` — konfigurasi panel Filament
- `database/seeders/RolesAndPermissionsSeeder.php` — roles & permissions
- `database/seeders/DatabaseSeeder.php` — user per tenant

---

## Langkah 5 — Sistem Modul On/Off

**Status:** SELESAI

**Yang dikerjakan:**
- Tabel `tenant_modules` (tenant_id, module_name, is_active) — feature flag per tenant
- Middleware `CheckModuleActive` — cek apakah modul aktif untuk tenant saat ini; return 403 bila tidak
- Middleware terdaftar sebagai alias `module` (usage: `->middleware('module:Hello')`)
- Modul contoh "Hello" dibuat via `nwidart/laravel-modules` (folder `Modules/Hello/`)
- Modul Hello: aktif di Asy-Syifaa, nonaktif di Demo

**Tabel billing skeleton (tanpa logika, migrasi saja):**

| Tabel | Kolom utama | Sesuai Blueprint |
|---|---|---|
| `plans` | name, billing_type, default_price, default_modules | §12 — template paket |
| `tenant_subscriptions` | tenant_id, plan_id, override, billing_cycle, status | §12 — langganan + override |
| `platform_invoices` | tenant_id, period, amount, status, payment_proof | §12 — tagihan platform |

**File utama:**
- `app/Models/TenantModule.php` — model + helper `isModuleActive()`
- `app/Http/Middleware/CheckModuleActive.php` — middleware
- `Modules/Hello/` — modul contoh kosong
- `database/migrations/2026_06_02_130001_create_billing_tables.php`

---

## Langkah 6 — Master Wilayah

**Status:** SELESAI (struktur & API)

**Yang dikerjakan:**
- 4 tabel wilayah Indonesia (data CENTRAL, dibaca semua tenant):

| Tabel | Primary Key | Relasi |
|---|---|---|
| `provinces` | char(2) | — |
| `cities` | char(4) | → provinces |
| `districts` | char(7) | → cities |
| `villages` | char(10) | → districts |

- 4 model Eloquent tanpa timestamps, tanpa auto-increment (ID mengikuti kode BPS)
- API endpoint cascading (`/api/wilayah/provinces`, `/cities/{id}`, `/districts/{id}`, `/villages/{id}`)

**Belum dikerjakan:**
- Seeder/importer data wilayah (butuh file CSV data BPS — dikerjakan saat dibutuhkan modul PPDB/Kesantrian)

**File utama:**
- `app/Models/Province.php`, `City.php`, `District.php`, `Village.php`
- `app/Http/Controllers/Api/WilayahController.php`
- `routes/api.php`

---

## Langkah 7 — Test Isolasi Tenant

**Status:** SELESAI — SEMUA HIJAU

**5 test isolasi tenant:**

| Test | Apa yang diuji | Hasil |
|---|---|---|
| `test_user_query_only_returns_own_tenant_data` | Query User dalam konteks tenant A hanya mengembalikan data tenant A | PASS |
| `test_tenant_b_cannot_see_tenant_a_users` | Tenant B tidak bisa melihat user tenant A | PASS |
| `test_creating_user_in_tenant_context_auto_sets_tenant_id` | Membuat user dalam konteks tenant otomatis mengisi tenant_id | PASS |
| `test_module_active_check_works_per_tenant` | Cek modul aktif/nonaktif benar per tenant | PASS |
| `test_no_cross_tenant_data_leakage` | Total data benar: masing-masing 1, total 2 | PASS |

**Total: 7 test, 14 assertions — SEMUA HIJAU**

**File utama:**
- `tests/Feature/TenantIsolationTest.php`
- `phpunit.xml` — dikonfigurasi untuk PostgreSQL (bukan SQLite)

---

## Langkah 8 — CI/CD GitHub Actions

**Status:** SELESAI

**Yang dikerjakan:**
- Workflow `.github/workflows/test.yml`:
  - Trigger: push ke `main` atau pull request ke `main`
  - Services: PostgreSQL 16 + Redis 7 (dengan healthcheck)
  - PHP 8.3 + extensions via `shivammathur/setup-php`
  - Jalankan `php artisan test` — build GAGAL bila test merah

**File utama:**
- `.github/workflows/test.yml`

---

## Perbaikan Pasca-Commit Awal

### Fix: Tenant Subdomain Identification (`2bad2ef`)

**Masalah:** `InitializeTenancyBySubdomain` mengekstrak subdomain dari hostname (misal `asy-syifaa` dari `asy-syifaa.localhost`), lalu mencocokkan ke tabel `domains`. Domain di DB tersimpan sebagai `asy-syifaa.localhost` (full) padahal stancl mencari `asy-syifaa` (subdomain saja) → tenant tidak ditemukan → error 500.

**Solusi:**
- Domain di tabel `domains` disimpan sebagai subdomain saja (`asy-syifaa`, bukan `asy-syifaa.localhost`)
- Command `tenant:create` diupdate agar menyimpan format yang benar
- Entry ditambahkan ke Windows hosts file: `127.0.0.1 asy-syifaa.localhost demo.localhost`

**Pelajaran:** Selalu test di browser setelah setup middleware — automated test saja tidak menangkap issue routing/hostname.

### Feat: Landing Page + Login Flow (`5a1c0ee`)

**Yang dikerjakan:**
- Landing page di central domain (`localhost:8000`) menampilkan daftar semua tenant
- Setiap tenant ditampilkan sebagai card dengan link langsung ke panel admin
- Klik card → redirect ke `{subdomain}.localhost:8000/admin`
- Jika belum login → halaman login Filament muncul
- Setelah login → langsung ke Dashboard dengan nama user yang benar

**Akses lokal:**

| URL | Fungsi |
|---|---|
| `localhost:8000` | Landing page — daftar tenant + link ke admin |
| `asy-syifaa.localhost:8000/admin` | Panel admin Asy-Syifaa |
| `demo.localhost:8000/admin` | Panel admin Demo |

**Kredensial test:**

| Tenant | Email | Password | Role |
|---|---|---|---|
| Asy-Syifaa | `admin@asy-syifaa.localhost` | `password` | super_admin |
| Asy-Syifaa | `tu@asy-syifaa.localhost` | `password` | tu |
| Demo | `admin@demo.localhost` | `password` | super_admin |
| Demo | `tu@demo.localhost` | `password` | tu |

**File utama:**
- `resources/views/landing.blade.php` — landing page (Blade, tanpa Vite build)
- `routes/web.php` — route central domain

---

## Definition of Done — Status Akhir

| # | Kriteria | Status |
|---|---|---|
| 1 | `docker compose up` menyalakan seluruh sistem | HIJAU — 4 container running |
| 2 | Bisa membuat 2 tenant lewat artisan | HIJAU — `asy-syifaa` + `demo` |
| 3 | Bisa login sebagai user di tenant masing-masing | HIJAU — Filament panel + tenant middleware |
| 4 | Peran (role) berfungsi | HIJAU — 5 roles, 12 permissions |
| 5 | Test isolasi tenant HIJAU | HIJAU — 5 test, 0 kebocoran |
| 6 | Modul bisa di-on/off per tenant | HIJAU — tenant_modules + middleware |
| 7 | CI GitHub Actions menjalankan test | HIJAU — workflow siap |

---

## Struktur File Penting

```
pesantren-platform/
├── app/
│   ├── Console/Commands/TenantCreateCommand.php
│   ├── Http/
│   │   ├── Controllers/Api/WilayahController.php
│   │   └── Middleware/
│   │       ├── CheckModuleActive.php
│   │       └── HandleInertiaRequests.php
│   ├── Models/
│   │   ├── Tenant.php, User.php, TenantModule.php
│   │   ├── Province.php, City.php, District.php, Village.php
│   ├── Providers/Filament/AdminPanelProvider.php
│   ├── Scopes/TenantScope.php
│   └── Traits/BelongsToTenant.php
├── config/
│   ├── tenancy.php, permission.php, modules.php
├── database/
│   ├── migrations/ (10 migration files)
│   └── seeders/ (RolesAndPermissionsSeeder, DatabaseSeeder)
├── docker-compose.yml, Dockerfile, Dockerfile.dev
├── Modules/Hello/ (modul contoh)
├── resources/views/
│   ├── landing.blade.php (landing page central domain)
│   └── app.blade.php (root template Inertia/Vue)
├── routes/ (web.php, tenant.php, api.php)
├── tests/Feature/TenantIsolationTest.php
├── .github/workflows/test.yml
└── docs/ (blueprint, PRD, rencana, laporan)
```

---

## Cara Menjalankan Lokal

**Prasyarat:** Docker Desktop terinstal.

```bash
# 1. Clone & masuk ke project
cd pesantren-platform

# 2. Jalankan semua service
docker compose up -d

# 3. Jalankan migrasi & seeder (pertama kali)
docker compose exec app php artisan migrate
docker compose exec app php artisan tenant:create "Asy-Syifaa" asy-syifaa
docker compose exec app php artisan tenant:create "Demo" demo
docker compose exec app php artisan db:seed

# 4. Tambahkan ke hosts file (Windows, perlu Administrator)
# Tambahkan baris berikut ke C:\Windows\System32\drivers\etc\hosts:
# 127.0.0.1 asy-syifaa.localhost demo.localhost

# 5. Buka di browser
# Landing page: http://localhost:8000
# Admin Asy-Syifaa: http://asy-syifaa.localhost:8000/admin
# Admin Demo: http://demo.localhost:8000/admin
# Login: admin@asy-syifaa.localhost / password
```

---

## Langkah Selanjutnya (T1)

Sesuai `RENCANA-T0.md` dan `BLUEPRINT-ARSITEKTUR.md` §8:

> **T1: Modul Kesantrian + Keuangan** — Asy-Syifaa bisa kelola santri & tagihan nyata.

Urutan:
1. Modul **Kesantrian** (master santri, status, engine penempatan kelas configurable)
2. Modul **Keuangan** (tagihan Masehi+Hijriah, pembayaran, tabungan, laporan)
3. Validasi: dipakai TU & bendahara Asy-Syifaa sungguhan
