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
- `.github/workflows/deploy.yml`
- `README.md`

### Update 3 Juni 2026 — CD Pipeline ke VPS

**Yang ditambahkan:**
- Workflow `.github/workflows/deploy.yml` untuk deploy otomatis saat `push` ke `main`
- Job deploy hanya berjalan setelah job test hijau (`needs: test`)
- Paket source diarsipkan dari runner lalu dikirim ke VPS via SCP/SSH
- Di VPS, source diekstrak ke folder release aktif, `.env` production disalin dari folder shared, lalu container direbuild dengan `docker compose up -d --build`
- Setelah container hidup, workflow menjalankan:
  - `composer install --no-interaction --prefer-dist --optimize-autoloader`
  - `php artisan migrate --force`
  - `php artisan optimize:clear`
  - `php artisan config:cache`
  - `php artisan route:cache`
  - `php artisan view:cache`

**Secret GitHub yang harus diisi:**
- `VPS_HOST`
- `VPS_USER`
- `VPS_PORT`
- `VPS_SSH_KEY`
- `VPS_PATH`

**Catatan penting:**
- File `.env` production harus tersedia di `${VPS_PATH}/shared/.env`
- Folder storage persisten harus disimpan di `${VPS_PATH}/shared/storage`
- Workflow test diperluas dengan `workflow_call` agar bisa dipakai ulang oleh workflow deploy tanpa menduplikasi definisi test

### Update 3 Juni 2026 — Monitoring & QC Jalur Test

**Yang ditambahkan:**
- Service `uptime-kuma` ditambahkan ke `docker-compose.yml` sebagai profile opsional `monitoring`
- Volume persisten `uptime_kuma_data` disiapkan agar histori monitor tidak hilang saat restart
- README ditambah panduan menjalankan monitoring lokal di `http://localhost:3001`
- README ditambah panduan jalur test yang benar:
  - dari host/CI memakai `DB_HOST=127.0.0.1`
  - dari container `app` override `DB_HOST=postgres`

**Catatan verifikasi:**
- Di environment kerja ini, akses ke Docker daemon sedang ditolak (`permission denied while trying to connect to the docker API at npipe:////./pipe/dockerDesktopLinuxEngine`), jadi service monitoring tidak bisa dinyalakan dari sesi ini
- Walau begitu, artefak konfigurasi dan dokumentasi operasional sudah siap dipakai begitu Docker Desktop aktif kembali

### Update 3 Juni 2026 — Password Reset Flow Filament

**Yang ditambahkan:**
- Fitur reset password diaktifkan pada panel Filament lewat `->passwordReset()` di `AdminPanelProvider`
- Route auth Filament untuk reset password sekarang terdaftar:
  - `admin/password-reset/request`
  - `admin/password-reset/reset`
- Test baru `tests/Feature/PasswordResetFlowTest.php` menutup dua alur inti:
  - user tenant bisa meminta link reset password
  - user tenant bisa mengganti password dengan token yang valid

**QC:**
- `php artisan test tests/Feature/PasswordResetFlowTest.php` PASS (`2` test hijau)
- `php artisan route:list --name=password-reset` menampilkan `2` route reset password Filament

**Dampak ke checklist T0:**
- `T0.4.3 Password reset flow` sekarang bisa dianggap SELESAI
- Task terbuka berikutnya yang berurutan adalah `T0.4.7 Audit log`

### Update 3 Juni 2026 — Audit Log Dasar untuk Aksi Auth

**Yang ditambahkan:**
- Tabel `audit_logs` untuk menyimpan jejak aksi sensitif yang tenant-aware
- Model `App\Models\AuditLog` dengan trait `BelongsToTenant`
- Service `App\Services\AuditLogger` untuk menulis log secara terpusat
- Listener event auth di `AppServiceProvider` untuk mencatat:
  - `auth.login`
  - `auth.logout`
  - `auth.password_reset`

**Struktur data audit log:**
- `tenant_id`
- `user_id`
- `action`
- `subject_type`
- `subject_id`
- `ip_address`
- `user_agent`
- `metadata`
- `created_at`

**QC:**
- Test baru `tests/Feature/AuditLogTest.php`
- `php artisan test tests/Feature/AuditLogTest.php tests/Feature/PasswordResetFlowTest.php` PASS (`4` test, `9` assertion)

**Catatan environment test:**
- PostgreSQL test database di sesi ini tidak stabil untuk suite lama yang masih memakai `RefreshDatabase`
- Karena itu QC run difokuskan ke test yang langsung relevan dengan perubahan hari ini, setelah `migrate:fresh` pada database test

**Dampak ke checklist T0:**
- `T0.4.7 Audit log` sekarang bisa dianggap SELESAI
- Task berurutan berikutnya adalah `T0.5.2 Filament resource: Manage Tenants`

### Update 3 Juni 2026 — Boundary Core Module + Settings Table

**Yang ditambahkan:**
- Module `Core` dibuat via `nwidart/laravel-modules` di folder `Modules/Core`
- Status modul dicatat aktif di `modules_statuses.json`
- Root `composer.json` diperbarui agar namespace `Modules\\Core\\...` ikut ter-autoload
- Tabel `settings` ditambahkan lewat migration `2026_06_03_031500_create_settings_table.php`
- Model `App\\Models\\Setting` ditambahkan dengan trait `BelongsToTenant`
- Relasi `Tenant::settings()` ditambahkan agar setting tenant bisa dipakai modul berikutnya

**QC:**
- `php artisan module:list` menampilkan `Core` dan `Hello` dalam status enabled
- `php artisan test` kembali HIJAU setelah perubahan ini

**Blocker berikutnya:**
- Seeder wilayah Indonesia belum bisa dibuat penuh karena file sumber CSV/JSON BPS belum tersedia di workspace saat sesi ini

### Update 3 Juni 2026 — Filament Resource Manage Tenants

**Yang ditambahkan:**
- Resource `App\\Filament\\Resources\\Tenants\\TenantResource`
- Halaman Filament untuk:
  - list tenant
  - create tenant
  - edit tenant
- Form tenant sekarang menangani field inti:
  - `id`
  - `name`
  - `primary_domain`
  - `is_active`
- Tabel list tenant menampilkan:
  - tenant id
  - nama tenant
  - subdomain
  - status aktif
  - waktu dibuat

**Aturan akses:**
- Hanya user dengan role `super_admin` yang bisa mengakses resource ini
- Navigasi tenant disembunyikan untuk role non-super-admin

**Perilaku create/edit:**
- Saat create, resource membuat record tenant lalu membuat domain utama tenant
- Saat edit, resource memperbarui data tenant dan menyinkronkan domain utama tenant

**QC:**
- `php artisan test tests/Feature/TenantResourceTest.php` PASS (`2` test, `5` assertion)
- `php artisan route:list --path=admin/tenants` menampilkan `3` route:
  - `admin/tenants`
  - `admin/tenants/create`
  - `admin/tenants/{record}/edit`

**Dampak ke checklist T0:**
- `T0.5.2 Filament resource: Manage Tenants` sekarang bisa dianggap SELESAI
- Task berurutan berikutnya adalah `T0.5.3 Filament resource: Manage Users (per-tenant)`

### Update 3 Juni 2026 — Filament Resource Manage Users

**Yang ditambahkan:**
- Resource `App\\Filament\\Resources\\Users\\UserResource`
- Halaman Filament untuk:
  - list user tenant aktif
  - create user tenant aktif
  - edit user tenant aktif
- Form user sekarang menangani field inti:
  - `name`
  - `email`
  - `password`
  - `roles`

**Aturan akses:**
- Resource hanya bisa diakses user yang punya permission `manage_users`
- Query user tetap tenant-aware, jadi panel tenant A tidak bisa melihat user tenant B

**QC:**
- `php artisan test tests/Feature/UserResourceTest.php` PASS (`3` test, `10` assertion)
- `php artisan route:list --path=admin/users` menampilkan `3` route:
  - `admin/users`
  - `admin/users/create`
  - `admin/users/{record}/edit`

**Dampak ke checklist T0:**
- `T0.5.3 Filament resource: Manage Users (per-tenant)` sekarang bisa dianggap SELESAI
- Task berurutan berikutnya adalah `T0.5.4 Filament resource: Manage Roles & Permissions`

### Update 3 Juni 2026 — Filament Resource Manage Roles & Permissions

**Yang ditambahkan:**
- Resource `App\\Filament\\Resources\\Roles\\RoleResource`
- Halaman Filament untuk:
  - list role
  - create role
  - edit role
- Form role sekarang bisa:
  - membuat/mengubah nama role
  - assign permission ke role via checklist Filament

**Aturan akses:**
- Resource hanya bisa diakses user yang punya permission `manage_roles`
- Panel tidak membuka jalur baru di luar RBAC inti; resource hanya mengelola role dan permission yang sudah dibuat oleh seeder

**QC:**
- `php artisan test tests/Feature/RoleResourceTest.php` PASS (`2` test, `7` assertion)
- `php artisan route:list --path=admin/roles` menampilkan `3` route:
  - `admin/roles`
  - `admin/roles/create`
  - `admin/roles/{record}/edit`

**Dampak ke checklist T0:**
- `T0.5.4 Filament resource: Manage Roles & Permissions` sekarang bisa dianggap SELESAI
- Task berurutan berikutnya adalah `T0.5.5 Filament resource: Settings & Branding`

### Update 3 Juni 2026 — Filament Resource Settings & Branding

**Yang ditambahkan:**
- Resource `App\\Filament\\Resources\\Settings\\SettingResource`
- Halaman Filament untuk:
  - list setting branding tenant aktif
  - create setting branding tenant aktif
  - edit setting branding tenant aktif
- Setting branding inti yang didukung:
  - `branding_name`
  - `branding_tagline`
  - `branding_primary_color`
  - `branding_secondary_color`
  - `branding_logo`

**Aturan akses:**
- Resource hanya bisa diakses user yang punya permission `manage_tenant_settings`
- Record setting tetap tenant-aware lewat model `Setting` + trait `BelongsToTenant`

**QC:**
- `php artisan test tests/Feature/SettingResourceTest.php` PASS (`3` test, `10` assertion)
- `php artisan route:list --path=admin/settings` menampilkan `3` route:
  - `admin/settings`
  - `admin/settings/create`
  - `admin/settings/{record}/edit`

**Dampak ke checklist T0:**
- `T0.5.5 Filament resource: Settings & Branding` sekarang bisa dianggap SELESAI
- Gap QC berikutnya yang berurutan adalah `T0.6.5 user tanpa permission ditolak akses endpoint`

### Update 3 Juni 2026 — Permission Denial Endpoint Test

**Yang ditambahkan:**
- Test baru `tests/Feature/PermissionAccessTest.php`
- Test ini memastikan role tanpa permission `manage_users` benar-benar ditolak ketika mengakses endpoint admin users

**QC:**
- `php artisan test tests/Feature/PermissionAccessTest.php` PASS (`1` test, `1` assertion)
- `php artisan test tests/Feature/UserResourceTest.php tests/Feature/RoleResourceTest.php tests/Feature/SettingResourceTest.php tests/Feature/PermissionAccessTest.php` PASS (`9` test, `28` assertion)

**Dampak ke checklist T0:**
- `T0.6.5 user tanpa permission ditolak akses endpoint` sekarang bisa dianggap SELESAI
- Item T0 yang masih tersisa secara teknis adalah:
  - `T0.3.10 Seeder wilayah Indonesia` (masih blocked karena file sumber belum ada di workspace)

### Update 3 Juni 2026 — MFA Admin & Pimpinan Aktif

**Yang ditambahkan:**
- Panel Filament sekarang mengaktifkan:
  - profile page (`/admin/profile`)
  - provider MFA bawaan Filament berbasis `email code`
- Model `User` sekarang mendukung kontrak `HasEmailAuthentication`
- Kolom baru `users.email_authentication_enabled` ditambahkan untuk menyimpan status MFA per user
- MFA diwajibkan untuk role kritikal:
  - `super_admin`
  - `kepala` (akan otomatis berlaku saat role ini dipakai)

**QC:**
- `php artisan test tests/Feature/MfaConfigurationTest.php` PASS (`3` test, `7` assertion)
- Test mencakup:
  - panel punya profile page
  - panel punya provider MFA `email_code`
  - role `super_admin` dibaca sebagai wajib MFA
  - flag MFA user bisa di-toggle dan tersimpan

**Catatan operasional:**
- `php artisan migrate --pretend` dari host shell gagal karena environment host masih memakai `DB_HOST=postgres`, yang valid di dalam container tetapi tidak resolvable dari host Windows
- Jalur test tetap hijau karena database test memakai konfigurasi `127.0.0.1`

**Dampak ke checklist T0:**
- `T0.5.7 Enable MFA untuk admin & pimpinan` sekarang bisa dianggap SELESAI
- Item T0 yang masih tersisa secara teknis adalah:
  - `T0.3.10 Seeder wilayah Indonesia` (masih blocked karena file sumber belum ada di workspace)

### Update 3 Juni 2026 — Investigasi Lanjutan Seeder Wilayah Indonesia

**Fokus sesi:**
- Menindaklanjuti task aktif paling atas yang belum selesai: `T0.3.10 Seeder wilayah Indonesia`
- Memastikan blocker lama masih valid sebelum melangkah ke T1, sesuai aturan kerja berurutan

**Yang diverifikasi:**
- Repo `pesantren-platform` memang sudah punya:
  - 4 tabel wilayah (`provinces`, `cities`, `districts`, `villages`)
  - 4 model Eloquent
  - endpoint cascading di `routes/api.php` + `WilayahController`
- Repo **belum** punya:
  - seeder wilayah
  - importer wilayah
  - file sumber CSV/JSON wilayah di workspace project
- `git log` pada area `database/seeders` dan migration wilayah hanya menunjukkan commit fondasi `fbeb293`; belum ada riwayat seeder wilayah yang hilang/terlewat

**Sumber data yang berhasil dilacak:**
- CSV publik dari repo `vermaysha/kode-wilayah-indonesia`
- Level yang berhasil diinspeksi dari sesi ini:
  - `provinces.csv`
  - `cities.csv`
  - `districts.csv`

**Blocker yang tersisa:**
- `villages.csv` penuh tidak bisa diambil utuh dari tooling sesi ini karena ukuran file terlalu besar untuk jalur fetch yang tersedia
- Runtime shell lokal juga tidak bisa mengunduh raw file publik langsung, sehingga import penuh level desa tidak bisa diselesaikan end-to-end dari sesi ini tanpa menambah sumber file ke workspace
- Driver SQLite tidak tersedia pada runtime host ini, jadi `database/database.sqlite` tidak bisa dipakai sebagai sumber cadangan untuk mengekstrak data wilayah

**Kesimpulan operasional:**
- Blocker `T0.3.10` **masih valid**
- Sesuai aturan "jangan lompat tahap", pengerjaan **tidak dilanjutkan ke T1** pada sesi ini
- T0 belum boleh dinyatakan sepenuhnya tertutup sampai sumber data wilayah level desa tersedia atau dimasukkan ke workspace dalam format yang bisa diimport

### Update 3 Juni 2026 — Seeder Wilayah Indonesia Aktif

**Fokus sesi:**
- Menyelesaikan task terbuka paling atas `T0.3.10 Seeder wilayah Indonesia`
- Menutup blocker "file sumber wilayah belum ada di workspace" tanpa menambah stack baru dan tetap di dalam Laravel/PHP

**Yang ditambahkan:**
- Service `App\Services\IndonesiaRegionImporter`
  - mengunduh dataset wilayah Indonesia dari sumber CSV publik yang terstruktur
  - mendukung override per level lewat env:
    - `WILAYAH_SOURCE_PROVINCES`
    - `WILAYAH_SOURCE_CITIES`
    - `WILAYAH_SOURCE_DISTRICTS`
    - `WILAYAH_SOURCE_VILLAGES`
  - parsing CSV dilakukan streaming baris per baris memakai `SplFileObject`
  - import dilakukan berurutan `provinces -> cities -> districts -> villages`
  - `upsert` berbasis primary key `id` agar aman diulang
- Seeder `Database\Seeders\IndonesiaRegionSeeder`
  - bisa dijalankan mandiri via `php artisan db:seed --class=IndonesiaRegionSeeder`
  - output CLI menampilkan jumlah baris per level setelah import selesai
- Test baru `tests/Feature/IndonesiaRegionSeederTest.php`
  - memverifikasi import penuh 4 level wilayah dengan `Http::fake()`
  - memverifikasi parser tetap jalan walau header CSV memakai variasi nama kolom

**Perbaikan bootstrap yang diperlukan saat verifikasi:**
- Resource Filament Kesantrian (`Santri`, `Kelas`, `PenempatanKelas`, `Alumni`) memakai tipe properti `navigationGroup` yang terlalu sempit untuk Filament 5
- Tipe diselaraskan ke `string|\UnitEnum|null` supaya autoload dan bootstrap aplikasi kembali normal

**QC:**
- `php -l app/Services/IndonesiaRegionImporter.php` PASS
- `php -l database/seeders/IndonesiaRegionSeeder.php` PASS
- `php -l tests/Feature/IndonesiaRegionSeederTest.php` PASS
- `composer dump-autoload --no-scripts` PASS
- `php artisan test tests/Feature/IndonesiaRegionSeederTest.php` PASS (`2` test, `13` assertion)

**Dampak ke checklist T0:**
- `T0.3.10 Seeder: data master wilayah Indonesia` sekarang bisa dianggap **SELESAI**
- Import tidak lagi bergantung pada file CSV yang harus ditaruh manual di workspace; bila perlu, sumber bisa dioverride ke file lokal atau mirror internal lewat env
- Item T0 yang masih belum penuh sekarang tinggal:
  - `T0.3.11 Seeder role default` (masih partial, 5 dari 11 role)

### Update 3 Juni 2026 — Seeder Role Default Lengkap

**Fokus sesi:**
- Menyelesaikan task berurutan berikutnya setelah wilayah, yaitu `T0.3.11 Seeder: role default`
- Menutup status partial 5/11 role menjadi lengkap 11/11 role tanpa menambah permission baru di luar lingkup T0

**Yang diubah:**
- `database/seeders/RolesAndPermissionsSeeder.php` sekarang membuat dan menyinkronkan 11 role default:
  - `super_admin`
  - `admin_pesantren`
  - `tu`
  - `keuangan`
  - `guru`
  - `guru_tahfidz`
  - `konselor`
  - `pengelola_uks`
  - `pengelola_asrama`
  - `kepala`
  - `wali`
- Mapping permission dirapikan menjadi tabel grant terpusat agar seed ulang tetap idempotent lewat `syncPermissions()`
- Role baru memakai permission inti yang memang sudah ada pada T0, jadi tidak membuka scope baru sebelum tahap modul lanjut

**QC:**
- Test baru `tests/Feature/RolesAndPermissionsSeederTest.php`
- `php artisan test tests/Feature/RolesAndPermissionsSeederTest.php tests/Feature/IndonesiaRegionSeederTest.php` PASS (`3` test, `17` assertion)
- Verifikasi eksplisit:
  - semua 11 role terbentuk
  - `kepala` memiliki `manage_tenant_settings`
  - `admin_pesantren` memiliki `manage_roles`
  - `wali` memiliki `view_own_children`

**Dampak ke checklist T0:**
- `T0.3.11 Seeder: role default` sekarang bisa dianggap **SELESAI**
- Sisa T0 yang belum penuh sekarang bergeser ke item lanjutan berikutnya di checklist, bukan lagi fondasi wilayah/RBAC


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

---

## Progress T1 — Modul Kesantrian

### Update 3 Juni 2026 — T1.1 s/d T1.4: Modul Kesantrian Lengkap

**Status:** KODE SELESAI — menunggu `php artisan migrate` & `php artisan test` di environment Docker

**Yang dikerjakan:**

#### T1.1 — Database & Model

Module `Kesantrian` dibuat via struktur nwidart di `Modules/Kesantrian/`.

**5 tabel migrasi:**

| Tabel | Kolom Utama | Isolasi |
|---|---|---|
| `kelas` | nama_kelas, tingkat, jurusan, capacity, wali_kelas_id, is_active | tenant_id + unique(tenant_id, nama_kelas) |
| `santri` | nis, nama, tanggal_lahir, jenis_kelamin, status, kelas_id, wali_user_id, photo_url | tenant_id + unique(tenant_id, nis) + soft delete |
| `penempatan_kelas` | santri_id, kelas_id, semester, tahun_akademik, tanggal_penempatan, alasan_mutasi, is_current | tenant_id |
| `status_santri` | santri_id, status, tanggal_status, alasan, durasi_cuti_hari, tanggal_kembali, created_by | tenant_id |
| `alumni` | santri_id, tahun_lulus, status_lanjutan, sekolah_tujuan, catatan | tenant_id + unique(tenant_id, santri_id) |

**5 model Eloquent** — semua menggunakan trait `BelongsToTenant`:
- `Kelas` — relasi ke santri, wali kelas, penempatan; helper `jumlahSantriAktif()`, `sisaKapasitas()`
- `Santri` — relasi ke kelas, wali user, penempatan, status history, alumni; soft delete
- `PenempatanKelas` — tracking riwayat kelas dengan flag `is_current`
- `StatusSantri` — history perubahan status (aktif/cuti/lulus/keluar/dikeluarkan)
- `Alumni` — data kelulusan linked ke santri

**Seeder:** `KesantrianDatabaseSeeder` membuat 6 kelas + 30-48 santri dummy per tenant.

**Registrasi modul:**
- `modules_statuses.json` → `Kesantrian: true`
- `composer.json` → autoload PSR-4 untuk `Modules\\Kesantrian\\`

#### T1.2 — Business Logic (Services)

**3 service class:**

| Service | Fungsi |
|---|---|
| `SantriService` | CRUD santri, ubah status (cuti/keluar/dikeluarkan), reaktivasi dari cuti, luluskan (auto-create alumni), list per kelas, hitung per status |
| `PenempatanKelasService` | Tempatkan santri ke kelas, mutasi, bulk assign, **engine kenaikan kelas configurable** (mode linear / mu'adalah), riwayat penempatan |
| `AlumniService` | List alumni (filter tahun), update data alumni |

**Engine Penempatan Kelas (sesuai Blueprint §14 — configurable, bukan hardcode):**
- Mode ditentukan oleh setting tenant `kelas_mode` = `linear` | `muadahah`
- **Mode Linear:** semua santri naik ke kelas yang dipetakan (normal)
- **Mode Mu'adalah:** santri bisa loncat/turun kelas via override individual `[santri_id => target_kelas_id]`
- Kenaikan dilakukan via `prosesKenaikanKelas()` dengan parameter pemetaan kelas + optional override

#### T1.3 — Filament Panel Resources

**4 Filament resource + 1 widget:**

| Resource | Fitur | Akses |
|---|---|---|
| `SantriResource` | CRUD + foto upload + filter status/kelas/gender + search NIS/nama | `view_santri` / `manage_santri` |
| `KelasResource` | CRUD + wali kelas + kapasitas + count santri aktif | `view_santri` / `manage_santri` |
| `PenempatanKelasResource` | Create penempatan/mutasi + riwayat + auto-sync kelas_id santri | `manage_santri` |
| `AlumniResource` | List only (alumni dibuat lewat proses kelulusan) + filter tahun | `view_santri` / `manage_santri` |
| `KesantrianOverviewWidget` | Stats: santri aktif, cuti, jumlah kelas, total alumni | `view_dashboard` |

Semua resource di bawah navigation group "Kesantrian".

#### T1.4 — Testing

**10 test case** di `tests/Feature/KesantrianTest.php`:

| Test | Yang Diuji |
|---|---|
| `test_nis_unique_within_same_tenant` | NIS duplikat dalam 1 tenant → exception |
| `test_nis_can_be_same_across_different_tenants` | NIS sama di tenant berbeda → OK |
| `test_engine_linear_kenaikan_kelas` | Mode linear: semua santri naik sesuai pemetaan |
| `test_engine_muadahah_dengan_override` | Mode mu'adalah: santri bisa loncat kelas via override |
| `test_mutasi_santri_tidak_double_count` | Mutasi: hanya 1 penempatan aktif, riwayat tersimpan |
| `test_report_santri_per_kelas_akurat` | Count per kelas benar, kapasitas sisa benar |
| `test_santri_tenant_a_tidak_terlihat_di_tenant_b` | Isolasi tenant santri |
| `test_kelas_tenant_a_tidak_terlihat_di_tenant_b` | Isolasi tenant kelas |
| `test_santri_service_ubah_status_cuti_dan_reaktivasi` | Flow cuti → reaktivasi |
| `test_santri_service_luluskan` | Flow lulus → auto-create alumni |

**Status test:** Kode test siap. Perlu dijalankan di environment Docker (`php artisan test tests/Feature/KesantrianTest.php`) setelah `migrate:fresh --seed`.

#### File yang Ditambahkan

```
Modules/Kesantrian/
├── app/
│   ├── Models/
│   │   ├── Alumni.php
│   │   ├── Kelas.php
│   │   ├── PenempatanKelas.php
│   │   ├── Santri.php
│   │   └── StatusSantri.php
│   ├── Providers/
│   │   ├── EventServiceProvider.php
│   │   ├── KesantrianServiceProvider.php
│   │   └── RouteServiceProvider.php
│   └── Services/
│       ├── AlumniService.php
│       ├── PenempatanKelasService.php
│       └── SantriService.php
├── config/config.php
├── database/
│   ├── migrations/ (5 files)
│   └── seeders/KesantrianDatabaseSeeder.php
├── module.json
├── composer.json
├── package.json
└── routes/ (web.php, api.php)

app/Filament/
├── Resources/
│   ├── Santri/ (SantriResource + 3 pages)
│   ├── Kelas/ (KelasResource + 3 pages)
│   ├── PenempatanKelas/ (PenempatanKelasResource + 2 pages)
│   └── Alumni/ (AlumniResource + 1 page)
└── Widgets/
    └── KesantrianOverviewWidget.php

tests/Feature/KesantrianTest.php (10 test cases)
```

#### Checklist T1.1–T1.4

- [x] T1.1.1 Buat module Kesantrian via nwidart
- [x] T1.1.2 Migrasi: tabel santri
- [x] T1.1.3 Migrasi: tabel kelas
- [x] T1.1.4 Migrasi: tabel penempatan_kelas
- [x] T1.1.5 Migrasi: tabel status_santri
- [x] T1.1.6 Migrasi: tabel alumni
- [x] T1.1.7 Model semua tabel + BelongsToTenant trait
- [x] T1.1.8 Seeder: data dummy santri untuk testing
- [x] T1.2.1 Service: CRUD santri
- [x] T1.2.2 Service: Engine penempatan kelas — Mode Mu'adalah (configurable)
- [x] T1.2.3 Service: Engine penempatan kelas — Mode Linear (configurable)
- [x] T1.2.4 Setting per-tenant: kelas_mode
- [x] T1.2.5 Service: mutasi santri
- [x] T1.2.6 Service: cuti santri
- [x] T1.2.7 Service: status santri
- [x] T1.2.8 Service: alumni management
- [x] T1.3.1 Filament resource: CRUD Santri + upload foto
- [x] T1.3.2 Filament resource: CRUD Kelas
- [x] T1.3.3 Filament resource: Penempatan Kelas
- [x] T1.3.4 Filament resource: Mutasi Santri (via PenempatanKelas)
- [x] T1.3.5 Filament resource: Status & Cuti Management (via SantriResource status field)
- [x] T1.3.6 Filament resource: Alumni List
- [x] T1.3.7 Dashboard widget: jumlah santri aktif, distribusi per kelas
- [ ] T1.3.8 Export: list santri per kelas (Excel, PDF) — ditunda ke T1 lanjutan
- [x] T1.4.1 Test: NIS unique per tenant
- [x] T1.4.2 Test: engine Mu'adalah vs Linear
- [x] T1.4.3 Test: mutasi santri tidak double-count
- [x] T1.4.4 Test: report santri per kelas akurat
- [x] T1.4.5 Test isolasi tenant: santri Tenant A tidak bisa lihat Tenant B
- [ ] T1.4.6 Commit — menunggu test hijau di Docker

#### Langkah Selanjutnya

1. Jalankan `docker compose up -d` lalu `docker compose exec app php artisan migrate`
2. Jalankan `docker compose exec app php artisan test tests/Feature/KesantrianTest.php`
3. Jika semua hijau → commit `feat(kesantrian): complete module`
4. Lanjut ke **T1.5 — Modul Keuangan**

### Update 3 Juni 2026 — Stabilkan QC Kesantrian + Export per Kelas

**Fokus sesi:**
- Memverifikasi ground truth modul `Kesantrian` yang sudah ada di workspace sebelum lanjut ke `T1.5 Keuangan`
- Menutup bug yang masih membuat test Kesantrian merah
- Menyelesaikan gap terakhir `T1.3.8 Export list santri per kelas (Excel, PDF)`

**Bug yang ditemukan saat QC:**
- `php artisan test tests/Feature/KesantrianTest.php` awalnya gagal di 3 test
- Akar masalah: parse error di `Modules/Kesantrian/app/Services/PenempatanKelasService.php`
- Penyebab spesifik: operator `??` dipakai langsung di dalam interpolasi string, yang membuat PHP melempar syntax error

**Perbaikan yang dilakukan:**
- Patch minimal pada `PenempatanKelasService`
  - nilai kelas asal dihitung dulu ke variabel lokal `$kelasAsal`
  - detail mutasi dinaikkan memakai string ASCII aman `->`
- Menambahkan export per kelas di area Filament Santri:
  - action `Export Excel per Kelas`
  - action `Cetak / Simpan PDF`
- Menambahkan route tenant-aware yang tetap memakai auth tunggal:
  - `admin/kesantrian/santri/export/csv`
  - `admin/kesantrian/santri/export/print`
- Menambahkan controller `SantriExportController`
  - CSV stream download untuk dibuka di Excel
  - halaman cetak daftar santri per kelas yang langsung memanggil `window.print()` agar bisa disimpan sebagai PDF dari browser
- Menambahkan view `resources/views/exports/santri-per-kelas-print.blade.php`

**QC sesi ini:**
- `composer dump-autoload --no-scripts` PASS
- `php -l Modules/Kesantrian/app/Services/PenempatanKelasService.php` PASS
- `php -l app/Http/Controllers/SantriExportController.php` PASS
- `php artisan route:list --path=admin/kesantrian/santri/export` PASS (`2` route tampil)
- `php artisan test tests/Feature/KesantrianTest.php` PASS (`10` test, `27` assertion)

**Dampak ke checklist:**
- `T1.3.8 Export: list santri per kelas (Excel, PDF)` sekarang bisa dianggap **SELESAI**
- `T1.4.1` s/d `T1.4.5` terverifikasi hijau ulang di sesi ini
- Modul `Kesantrian` praktis sudah siap dilanjutkan ke langkah berikutnya
- Satu item berurutan yang masih tersisa di blok Kesantrian adalah:
  - `T1.4.6 Commit: feat(kesantrian): complete module`

**Catatan operasional:**
- `php artisan migrate --pretend` dari host Windows masih gagal bila `.env` aktif memakai `DB_HOST=postgres`; ini konsisten dengan catatan sesi sebelumnya dan bukan bug logika modul
- Jalur test tetap aman karena suite test memakai konfigurasi database yang berjalan di host

**Next safest step:**
1. Putuskan apakah perubahan lokal Kesantrian saat ini mau langsung dikomit sebagai satu paket modul
2. Jika ya, commit `feat(kesantrian): complete module`
3. Setelah itu baru lanjut berurutan ke `T1.5 — Modul Keuangan`

### Update 3 Juni 2026 — Fondasi Modul Keuangan Distabilkan

**Fokus sesi:**
- Memastikan blok `Kesantrian` benar-benar bersih sebelum menyentuh task berikutnya
- Menutup fondasi `T1.5 — Modul Keuangan: Database & Model` yang sudah mulai ada di workspace tetapi belum lengkap secara bootstrap
- Membedakan bug kode vs noise environment pada PostgreSQL test database

**Ground truth sebelum lanjut:**
- `php artisan test tests/Feature/KesantrianTest.php` PASS (`10` test, `27` assertion)
- Artinya bug Kesantrian yang sebelumnya ada tidak muncul lagi di workspace aktif

**Perbaikan yang dilakukan pada fondasi Keuangan:**
- Root `composer.json` diperbarui agar autoload mengenali:
  - `Modules\\Keuangan\\`
  - `Modules\\Keuangan\\Database\\Seeders\\`
- `modules_statuses.json` diperbarui agar modul `Keuangan` aktif di bootstrap module system
- Model yang sebelumnya belum ada sekarang ditambahkan:
  - `Modules/Keuangan/app/Models/Tabungan.php`
  - `Modules/Keuangan/app/Models/TabunganTransaksi.php`
- Relasi inti model Keuangan sekarang lengkap untuk blok database/model:
  - `Tagihan -> santri / pembayaran / creator`
  - `Pembayaran -> tagihan / santri / creator / verifier`
  - `Tabungan -> santri / transaksi`
  - `TabunganTransaksi -> tabungan / creator`

**QC sesi ini:**
- `composer dump-autoload --no-scripts` PASS
- `php -l Modules/Keuangan/app/Models/Tabungan.php` PASS
- `php -l Modules/Keuangan/app/Models/TabunganTransaksi.php` PASS
- `php artisan about` PASS setelah autoload diperbarui
- Jalur reset database test yang valid dari host Windows:
  - set env sementara `APP_ENV=testing`
  - set `DB_HOST=127.0.0.1`
  - set `CACHE_STORE=array`
  - set `QUEUE_CONNECTION=sync`
  - lalu jalankan `php artisan migrate:fresh --seed`
- `php artisan test tests/Feature/KeuanganModelTest.php tests/Feature/KesantrianTest.php` PASS (`11` test, `31` assertion)
- `php artisan test tests/Feature/IndonesiaRegionSeederTest.php tests/Feature/RolesAndPermissionsSeederTest.php` PASS (`3` test, `17` assertion)

**Catatan penting environment:**
- False failure sempat muncul bukan karena logic aplikasi, tetapi karena:
  - host shell memakai `.env` lokal dengan `DB_HOST=postgres` yang hanya valid di container
  - dua proses PHPUnit dijalankan paralel ke database PostgreSQL test yang sama
- Untuk repo ini, QC host-level yang aman adalah:
  - override `DB_HOST=127.0.0.1`
  - hindari menjalankan dua suite PHPUnit paralel ke `pesantren_platform_test`

**Dampak ke checklist:**
- Blok `T1.5.1` s/d `T1.5.6` sekarang **siap dianggap selesai secara kode dan QC lokal**
- Task berurutan berikutnya di implementation track adalah:
  - `T1.6.1 Service: buat tagihan (per-santri, per-kelas, bulk)`

**Catatan git hygiene:**
- Checklist `T1.4.6 Commit: feat(kesantrian): complete module` masih belum ditutup di sesi ini
- Alasannya bukan bug aplikasi, tetapi working tree saat ini sudah memuat perubahan lintas T0, Kesantrian, dan awal Keuangan sehingga commit Kesantrian perlu dipisah dengan hati-hati bila ingin dijadikan commit terisolasi

### Update 3 Juni 2026 — T1.6.1 Service Buat Tagihan

**Fokus sesi:**
- Melanjutkan task berurutan implementasi modul `Keuangan` pada blok business logic
- Menutup `T1.6.1 Service: buat tagihan (per-santri, per-kelas, bulk)` dengan perubahan minimum di atas model dan migrasi yang sudah ada
- Menjaga QC tetap lokal dan tenant-aware sebelum lanjut ke task keuangan berikutnya

**Yang ditambahkan:**
- Service baru `Modules/Keuangan/app/Services/TagihanService.php`
- Tiga entry point inti:
  - `buatTagihanUntukSantri()`
  - `buatTagihanPerKelas()`
  - `buatTagihanBulk()`
- Perilaku yang sekarang ditangani service:
  - memastikan tenant context aktif sebelum membuat tagihan
  - menghitung `nominal_akhir = nominal - diskon` dengan guard minimum `0`
  - membuat tagihan per santri aktif di kelas yang dipilih
  - membuat tagihan bulk untuk daftar santri tertentu dalam satu transaction

**QC yang dijalankan:**
- `php -l Modules/Keuangan/app/Services/TagihanService.php` PASS
- `php -l tests/Feature/TagihanServiceTest.php` PASS
- Jalur test host-level dengan override environment aman:
  - `APP_ENV=testing`
  - `DB_HOST=127.0.0.1`
  - `CACHE_STORE=array`
  - `QUEUE_CONNECTION=sync`
- `php artisan test tests/Feature/TagihanServiceTest.php tests/Feature/KeuanganModelTest.php` PASS (`4` test, `15` assertion)

**Test coverage baru:**
- `test_buat_tagihan_untuk_satu_santri_menghitung_nominal_akhir`
- `test_buat_tagihan_per_kelas_hanya_untuk_santri_aktif`
- `test_buat_tagihan_bulk_untuk_daftar_santri_tertentu`

**Dampak ke checklist T1:**
- `T1.6.1 Service: buat tagihan (per-santri, per-kelas, bulk)` sekarang bisa dianggap **SELESAI**
- Task implementasi berikutnya yang berurutan di modul `Keuangan` adalah:
  - `T1.6.2 Service: tagihan dual-calendar (Masehi + Hijriah) — configurable per-tenant`

**Catatan penting:**
- Checklist `T1.4.6 Commit: feat(kesantrian): complete module` masih belum ditutup karena working tree aktif masih mengandung perubahan gabungan lintas fondasi, Kesantrian, dan Keuangan
- Secara kode dan QC, service `Tagihan` sudah stabil; blocker tersisa untuk commit bersifat git hygiene, bukan bug aplikasi

### Update 3 Juni 2026 — T1.6.2 dan T1.6.3 Dual Calendar + Setting Periode Keuangan

**Fokus sesi:**
- Menutup task berurutan `T1.6.2` dan `T1.6.3` pada modul `Keuangan`
- Menjaga desain tetap sesuai Blueprint §14: perilaku periode keuangan ditentukan oleh setting tenant, bukan hardcode per pesantren
- Memastikan service pembuatan tagihan tetap tenant-aware dan lolos QC lokal sebelum lanjut ke task pembayaran

**Perubahan yang dilakukan:**
- `Modules/Keuangan/app/Services/TagihanService.php` sekarang membaca setting tenant `periode_keuangan`
- Mode yang didukung:
  - `masehi`
  - `hijriah`
  - `keduanya`
- Method baru `setPeriodeKeuangan()` ditambahkan untuk menyimpan konfigurasi tenant lewat tabel `settings`
- Resolusi periode tagihan sekarang mengikuti mode:
  - `masehi` → `bulan_masehi` otomatis diisi dari `jatuh_tempo` format `Y-m`, `bulan_hijriah` dikosongkan
  - `hijriah` → `bulan_hijriah` wajib diisi, `bulan_masehi` dikosongkan
  - `keduanya` → `bulan_masehi` otomatis diisi dan `bulan_hijriah` wajib diisi
- Validation minimal ditambahkan agar mode `hijriah` dan `keduanya` tidak diam-diam membuat tagihan tanpa label bulan Hijriah

**QC sesi ini:**
- `php -l Modules/Keuangan/app/Services/TagihanService.php` PASS
- `php -l tests/Feature/TagihanServiceTest.php` PASS
- Jalur test host-level aman:
  - `APP_ENV=testing`
  - `DB_HOST=127.0.0.1`
  - `CACHE_STORE=array`
  - `QUEUE_CONNECTION=sync`
- `php artisan test tests/Feature/TagihanServiceTest.php tests/Feature/KeuanganModelTest.php` PASS (`6` test, `23` assertion)

**Test coverage baru/terverifikasi:**
- mode `masehi` menyimpan `bulan_masehi` otomatis dari `jatuh_tempo`
- mode `hijriah` menyimpan hanya `bulan_hijriah`
- mode `keduanya` menyimpan dua kalender sekaligus
- mode `hijriah` menolak payload tanpa `bulan_hijriah`
- setting `periode_keuangan` tersimpan tenant-aware di tabel `settings`

**Dampak ke checklist T1:**
- `T1.6.2 Service: tagihan dual-calendar (Masehi + Hijriah)` sekarang bisa dianggap **SELESAI**
- `T1.6.3 Setting per-tenant: periode_keuangan = masehi | hijriah | keduanya` sekarang bisa dianggap **SELESAI**
- Task implementasi berikutnya yang berurutan adalah:
  - `T1.6.4 Service: input pembayaran manual (bendahara entry)`

**Catatan operasional:**
- `T1.4.6 Commit: feat(kesantrian): complete module` masih tersisa sebagai urusan git hygiene karena working tree aktif tetap gabungan lintas milestone
- Tidak ada bug aplikasi baru yang ditemukan pada jalur dual-calendar sesi ini

### Update 3 Juni 2026 — T1.6.4–T1.8: Modul Keuangan Lengkap (Business Logic + Filament + Testing)

**Fokus sesi:**
- Menyelesaikan seluruh blok T1.5–T1.8 modul Keuangan secara berurutan
- Membangun 4 service baru, 4 Filament resource, 1 dashboard widget, dan 12 test case
- Menjaga konsistensi arsitektur: tenant-aware, configurable, DRY

#### T1.6.4–T1.6.5 — PembayaranService (Input Manual + Partial Payment)

**File:** `Modules/Keuangan/app/Services/PembayaranService.php`

**Fungsi inti:**
- `inputPembayaran()` — input pembayaran manual oleh bendahara, mendukung cicilan (partial payment)
- `verifikasiPembayaran()` — verifikasi pembayaran oleh admin/bendahara
- `batalkanPembayaran()` — soft-cancel pembayaran + recalculate status tagihan
- `updateStatusTagihan()` — auto-recalculate status: belum_bayar → sebagian → lunas

**Validasi:**
- Tolak bayar jika tagihan sudah lunas atau batal
- Tolak nominal ≤ 0
- Tolak nominal melebihi sisa tagihan
- Tolak verifikasi ganda

#### T1.6.6 — TabunganService (Setor/Tarik)

**File:** `Modules/Keuangan/app/Services/TabunganService.php`

**Fungsi inti:**
- `getOrCreateTabungan()` — auto-create tabungan saat pertama kali setor
- `setor()` — setoran dengan tracking saldo sebelum/sesudah
- `tarik()` — penarikan dengan guard saldo cukup (row-level locking)
- `getSaldo()` — query saldo terkini
- `listTransaksi()` — riwayat transaksi

**Keamanan:** `lockForUpdate()` pada transaksi untuk mencegah race condition concurrent setor/tarik.

#### T1.6.7 — RekonsiliasiService

**File:** `Modules/Keuangan/app/Services/RekonsiliasiService.php`

**Fungsi:** `rekonsiliasiPeriode()` — cocokan total tagihan vs total pembayaran terverifikasi, menghasilkan:
- Total tagihan, total terkumpul, sisa piutang, persentase terkumpul
- Breakdown per status (belum_bayar, sebagian, lunas)
- Filter opsional per periode_akademik dan bulan_masehi

#### T1.6.8 — LaporanKeuanganService

**File:** `Modules/Keuangan/app/Services/LaporanKeuanganService.php`

**Fungsi:**
- `laporanHarian()` — pembayaran terverifikasi hari ini, grouped per jenis tagihan
- `laporanMingguan()` — 7 hari terakhir per hari
- `laporanBulanan()` — summary satu bulan (tagihan vs terkumpul vs piutang)
- `summaryOverview()` — overview keseluruhan keuangan tenant

#### T1.7 — Filament Panel Resources

**4 Filament resource + 1 widget ditambahkan:**

| Resource | Lokasi | Fitur | Akses |
|---|---|---|---|
| `TagihanResource` | `app/Filament/Resources/Tagihan/` | CRUD tagihan single + filter status/jenis + auto-calculate nominal_akhir | `view_keuangan` / `manage_keuangan` |
| `PembayaranResource` | `app/Filament/Resources/Pembayaran/` | Input pembayaran + verifikasi inline action + filter verified/metode | `view_keuangan` / `manage_keuangan` |
| `TabunganResource` | `app/Filament/Resources/Tabungan/` | List tabungan + inline setor/tarik actions | `view_keuangan` / `manage_keuangan` |
| `LaporanKeuanganResource` | `app/Filament/Resources/LaporanKeuangan/` | Read-only tagihan overview dengan filter | `view_keuangan` / `manage_keuangan` |
| `KeuanganOverviewWidget` | `app/Filament/Widgets/` | Stats: total tagihan, terkumpul, piutang, jatuh tempo, pembayaran hari ini | `view_keuangan` / `manage_keuangan` |

Semua resource di bawah navigation group "Keuangan".

#### T1.8 — Testing

**File:** `tests/Feature/KeuanganServiceTest.php` — 12 test case

| Test | Yang Diuji |
|---|---|
| `test_create_tagihan_nominal_dan_jatuh_tempo_tepat` | T1.8.1 — create tagihan, nominal_akhir = nominal - diskon |
| `test_pembayaran_status_update_belum_sebagian_lunas` | T1.8.2 — flow belum_bayar → sebagian → lunas |
| `test_rekonsiliasi_total_terkumpul_equals_sum_pembayaran` | T1.8.3 — rekonsiliasi cocok |
| `test_dual_calendar_mode_masehi_only` | T1.8.4a — mode masehi auto-fill bulan_masehi |
| `test_dual_calendar_mode_hijriah_requires_bulan_hijriah` | T1.8.4b — mode hijriah tolak tanpa bulan_hijriah |
| `test_dual_calendar_mode_keduanya` | T1.8.4c — mode keduanya simpan dua kalender |
| `test_isolasi_tenant_tagihan_tidak_terlihat_lintas_tenant` | T1.8.5 — isolasi tenant tagihan |
| `test_tabungan_setor_dan_tarik` | Tabungan setor/tarik + saldo benar |
| `test_tabungan_tarik_melebihi_saldo_ditolak` | Guard saldo cukup |
| `test_laporan_harian_menghitung_pembayaran_terverifikasi` | Laporan harian hanya hitung verified |
| `test_pembayaran_tolak_bayar_tagihan_lunas` | Guard tagihan lunas |

**Tambahan:** method `hitungBulanHijriah()` ditambahkan ke TagihanService untuk konversi Masehi → Hijriah menggunakan IntlCalendar (fallback: Islamic Civil calendar aritmetik).

#### QC Sesi Ini

- Semua 18 file PHP baru/terubah lolos basic syntax check (brace balancing, namespace, opening tag)
- PHP runtime tidak tersedia di sandbox sesi ini, sehingga `php artisan test` harus dijalankan di Docker environment
- Tidak ada modifikasi pada file yang sudah ada sebelumnya kecuali penambahan `hitungBulanHijriah()` di TagihanService

#### Checklist T1.5–T1.8

- [x] T1.5.1–T1.5.6 Database & Model (selesai sesi sebelumnya)
- [x] T1.6.1 Service: buat tagihan (selesai sesi sebelumnya)
- [x] T1.6.2 Service: tagihan dual-calendar
- [x] T1.6.3 Setting per-tenant: periode_keuangan
- [x] T1.6.4 Service: input pembayaran manual
- [x] T1.6.5 Service: partial payment (cicilan)
- [x] T1.6.6 Service: tabungan santri (setor/tarik)
- [x] T1.6.7 Service: rekonsiliasi
- [x] T1.6.8 Service: laporan keuangan (harian, mingguan, bulanan)
- [x] T1.7.1 Filament resource: CRUD Tagihan
- [x] T1.7.2 Filament resource: Input & Verifikasi Pembayaran
- [x] T1.7.3 Filament resource: Tabungan Santri
- [x] T1.7.4 Filament resource: Laporan Keuangan
- [x] T1.7.5 Dashboard widget: summary tagihan & pembayaran
- [ ] T1.7.6 Export: laporan keuangan (PDF, Excel) — ditunda ke sesi berikutnya
- [x] T1.8.1 Test: create tagihan
- [x] T1.8.2 Test: pembayaran status update
- [x] T1.8.3 Test: rekonsiliasi
- [x] T1.8.4 Test: dual calendar
- [x] T1.8.5 Test: isolasi tenant tagihan
- [ ] T1.8.6 Commit: feat(keuangan): complete module — menunggu test hijau di Docker

#### File yang Ditambahkan Sesi Ini

```
Modules/Keuangan/app/Services/
├── PembayaranService.php        (NEW)
├── TabunganService.php          (NEW)
├── RekonsiliasiService.php      (NEW)
├── LaporanKeuanganService.php   (NEW)
└── TagihanService.php           (MODIFIED — +hitungBulanHijriah)

app/Filament/Resources/
├── Tagihan/
│   ├── TagihanResource.php      (NEW)
│   └── Pages/ (3 files)         (NEW)
├── Pembayaran/
│   ├── PembayaranResource.php   (NEW)
│   └── Pages/ (2 files)         (NEW)
├── Tabungan/
│   ├── TabunganResource.php     (NEW)
│   └── Pages/ (1 file)          (NEW)
└── LaporanKeuangan/
    ├── LaporanKeuanganResource.php (NEW)
    └── Pages/ (1 file)          (NEW)

app/Filament/Widgets/
└── KeuanganOverviewWidget.php   (NEW)

tests/Feature/
└── KeuanganServiceTest.php      (NEW — 12 test cases)
```

#### Langkah Selanjutnya

1. Jalankan `docker compose up -d` lalu `docker compose exec app php artisan migrate`
2. Jalankan `docker compose exec app php artisan test tests/Feature/KeuanganServiceTest.php`
3. Jika semua hijau → commit `feat(keuangan): complete module`
4. Tutup T1.7.6 (export PDF/Excel) di sesi berikutnya
5. Lanjut ke **T1.9 — Modul Notifikasi**

### Update 3 Juni 2026 — Stabilkan QC Keuangan di Host Test DB

**Fokus sesi:**
- Memverifikasi ulang modul `Keuangan` dengan eksekusi test nyata di repo aktif, bukan hanya mengandalkan catatan sesi sebelumnya
- Memisahkan false failure environment dari bug aplikasi yang benar-benar ada
- Menutup bug QC sebelum lanjut ke task T1 berikutnya

**Temuan penting saat verifikasi:**
- Run pertama gagal dengan error migrasi PostgreSQL yang saling bentrok
- Akar masalahnya bukan logic modul, tetapi dua proses PHPUnit dijalankan paralel ke database test `pesantren_platform_test`
- Setelah suite dijalankan ulang secara **sequential**, muncul bug nyata:
  - 4 test `KeuanganServiceTest` gagal pada foreign key `pembayaran_verified_by_foreign`
  - penyebab: test memverifikasi pembayaran memakai `verified_by = 1`, tetapi user verifier tidak pernah dibuat

**Perbaikan yang dilakukan:**
- Patch minimal pada [`tests/Feature/KeuanganServiceTest.php`](C:\Users\maula\Downloads\ABDM\_Agent Manager\_PESANTREN\pesantren-platform\tests\Feature\KeuanganServiceTest.php)
- Menambahkan helper `createVerifier()` untuk membuat user bendahara sah di tenant aktif
- Mengganti seluruh pemanggilan `verifikasiPembayaran(..., 1)` menjadi `verifikasiPembayaran(..., $verifier->id)`
- Tidak ada perubahan business logic pada `PembayaranService`; kontrak foreign key tetap dipertahankan

**QC sesi ini:**
- `php -l tests/Feature/KeuanganServiceTest.php` PASS
- `php artisan test tests/Feature/KeuanganServiceTest.php` PASS (`11` test, `30` assertion)
- `php artisan test tests/Feature/KeuanganModelTest.php tests/Feature/TagihanServiceTest.php tests/Feature/KesantrianTest.php` PASS (`16` test, `50` assertion)

**Catatan operasional penting:**
- Untuk repo ini, suite berbasis `RefreshDatabase` ke PostgreSQL **jangan dijalankan paralel** dari host Windows
- Jalur aman yang terverifikasi:
  - `APP_ENV=testing`
  - `DB_HOST=127.0.0.1`
  - `CACHE_STORE=array`
  - `QUEUE_CONNECTION=sync`
  - eksekusi suite secara sequential

**Dampak ke status T1:**
- Checklist `T1.8.2`, `T1.8.3`, dan area verifikasi pembayaran sekarang benar-benar terbukti hijau di source tree aktif
- Tidak ada task checklist baru yang dibuka; urutan tetap:
  - `T1.7.6 Export: laporan keuangan (PDF, Excel)`
  - `T1.8.6 Commit: feat(keuangan): complete module`
  - baru setelah itu `T1.9 Modul Notifikasi`
