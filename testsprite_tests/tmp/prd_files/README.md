# Pesantren Platform

Platform ERP pesantren white-label berbasis Laravel multi-tenant sesuai konstitusi di [`docs/BLUEPRINT-ARSITEKTUR.md`](C:\Users\maula\Downloads\ABDM\_Agent Manager\_PESANTREN\pesantren-platform\docs\BLUEPRINT-ARSITEKTUR.md).

## Stack

- Laravel 13
- Filament 5
- stancl/tenancy
- spatie/laravel-permission
- nwidart/laravel-modules
- Inertia.js + Vue 3
- PostgreSQL 16
- Redis 7
- Docker Compose + Traefik

## Menjalankan Lokal

### Langkah 1 — Hosts file (sekali saja)

Tambahkan ke `C:\Windows\System32\drivers\etc\hosts` (sebagai Administrator):

```text
127.0.0.1 asy-syifaa.localhost demo.localhost
```

### Langkah 2 — Build & jalankan container

```bash
docker compose up -d --build
```

### Langkah 3 — Setup (WAJIB setelah build pertama)

```bash
bash scripts/dev-setup.sh
```

Script ini otomatis: install vendor, migrasi, seeder, cache Filament, cache Laravel.

**Mengapa wajib?** Platform ini punya 40+ Filament resources dari 12 modul. Tanpa cache, panel admin timeout 30 detik saat loading. `filament:cache-components` menyelesaikan masalah ini.

### Langkah 4 — Buat tenant

```bash
docker compose exec app php artisan tenant:create "Asy-Syifaa" asy-syifaa
docker compose exec app php artisan tenant:create "Demo" demo
```

### URL Lokal

- `http://localhost:8000` — Landing page
- `http://asy-syifaa.localhost:8000/admin` — Panel admin Asy-Syifaa
- `http://demo.localhost:8000/admin` — Panel admin Demo

Login: `admin@asy-syifaa.localhost` / `password`

### Setelah update kode

```bash
# Jika mengubah resource/page Filament baru:
docker compose exec app php artisan filament:cache-components

# Jika mengubah route:
docker compose exec app php artisan route:cache

# Jika mengubah config:
docker compose exec app php artisan config:cache

# Jika ada masalah aneh — clear semua cache:
docker compose exec app php artisan optimize:clear && bash scripts/dev-setup.sh
```

## Testing Lokal

Jalankan test dari host atau CI runner. `phpunit.xml` memakai `DB_HOST=127.0.0.1` agar cocok dengan service PostgreSQL yang diekspos oleh Docker/Actions.

```bash
php artisan test
```

Prasyarat:

- container PostgreSQL dan Redis sudah hidup
- port `5432` dan `6379` terbuka di host lokal

Jika ingin menjalankan test dari dalam container `app`, override host database saat eksekusi:

```bash
docker compose exec -e DB_HOST=postgres app php artisan test
```

## CI/CD

### CI

Workflow [`/.github/workflows/test.yml`](C:\Users\maula\Downloads\ABDM\_Agent Manager\_PESANTREN\pesantren-platform\.github\workflows\test.yml) berjalan pada `push` dan `pull_request` ke `main` dan memblokir deploy jika test gagal.

### CD

Workflow [`/.github/workflows/deploy.yml`](C:\Users\maula\Downloads\ABDM\_Agent Manager\_PESANTREN\pesantren-platform\.github\workflows\deploy.yml) berjalan pada `push` ke `main`, namun job deploy hanya jalan setelah job test hijau.

Secret GitHub yang wajib diisi:

- `VPS_HOST`
- `VPS_USER`
- `VPS_PORT`
- `VPS_SSH_KEY`
- `VPS_PATH`

Struktur target di VPS:

- Repo diekstrak ke `${VPS_PATH}/current`
- File `.env` production disimpan permanen di `${VPS_PATH}/shared/.env`
- Direktori `storage` persisten disimpan di `${VPS_PATH}/shared/storage`

Alur deploy:

1. Runner membuat arsip source tanpa `.git`, `vendor`, dan `node_modules`.
2. Arsip dikirim ke VPS via SCP.
3. VPS mengekstrak source ke `${VPS_PATH}/current`.
4. `.env` dan `storage` shared dipasang ulang.
5. `docker compose up -d --build` dijalankan.
6. Migrasi production dijalankan dengan `php artisan migrate --force`.
7. Cache aplikasi di-refresh.

## Monitoring

Monitoring memakai tool siap pakai, bukan dibangun sendiri:

- Lokal/opsional: service `uptime-kuma` dengan profile `monitoring`
- Production ringan: Uptime Kuma self-hosted atau UptimeRobot

Menjalankan Uptime Kuma lokal:

```bash
docker compose --profile monitoring up -d uptime-kuma
```

URL monitoring lokal:

- `http://localhost:3001`

Monitor minimum yang disarankan:

- `http://localhost:8000`
- `http://localhost:8000/admin`
- health endpoint tenant/production yang nanti dipakai live

## Catatan Operasional

- Server production harus sudah memiliki Docker Engine + Docker Compose plugin.
- Container `app` harus dapat menjalankan `php artisan` dan `composer install`.
- Bila struktur production berbeda dari asumsi workflow ini, ubah nilai `VPS_PATH` atau perintah di workflow sebelum dipakai live.
