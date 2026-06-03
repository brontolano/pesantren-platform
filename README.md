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

```bash
docker compose up -d
docker compose exec app php artisan migrate
docker compose exec app php artisan tenant:create "Asy-Syifaa" asy-syifaa
docker compose exec app php artisan tenant:create "Demo" demo
docker compose exec app php artisan db:seed
```

Tambahkan host lokal berikut:

```text
127.0.0.1 asy-syifaa.localhost demo.localhost
```

URL lokal:

- `http://localhost:8000`
- `http://asy-syifaa.localhost:8000/admin`
- `http://demo.localhost:8000/admin`

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
