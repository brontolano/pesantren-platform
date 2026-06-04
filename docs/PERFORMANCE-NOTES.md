# Catatan Optimasi Performa — Platform Pesantren

> **Status:** T4.4 selesai (sebagian besar). Sisa ditangani saat T5 production.
> **Tanggal:** 4 Juni 2026

---

## Yang Sudah Dikerjakan (T4.4)

### Fix Development Lokal

| Item | Status | Detail |
|---|---|---|
| Ganti `php artisan serve` → FrankenPHP | ✅ | `Dockerfile.dev` pakai `dunglas/frankenphp:latest-php8.3` + `Caddyfile` |
| Ganti `SESSION_DRIVER=database` → Redis | ✅ | `.env` + `.env.example` diupdate |
| Tambah OPcache di `Dockerfile.dev` | ✅ | `validate_timestamps=1, revalidate_freq=0` |
| Aktifkan SPA mode Filament | ✅ | `->spa()` di `AdminPanelProvider` |
| `Model::preventLazyLoading()` | ✅ | AppServiceProvider, development only |
| Eager loading semua Filament resource | ✅ | `->with()` di semua resource yang punya relasi |
| Production cache di deploy workflow | ✅ | `config:cache`, `route:cache`, `view:cache`, `event:cache` |
| Index komposit `tenant_id` | ✅ | Migration `2026_06_03_220500_add_performance_indexes.php` |
| Vite production build di deploy workflow | ✅ | `npm run build` — minify JS/CSS |
| Gzip/Brotli compression di Traefik | ✅ | Middleware `compress` ditambahkan |

### Indeks Database yang Sudah Ada

```sql
-- santri
INDEX (tenant_id, status)
INDEX (tenant_id, kelas_id)

-- tagihan
INDEX (tenant_id, status)
INDEX (tenant_id, santri_id)

-- pembayaran
INDEX (tenant_id, tagihan_id)
INDEX (tenant_id, is_verified)

-- penempatan_kelas
INDEX (tenant_id, santri_id, is_current)

-- audit_logs
INDEX (tenant_id, created_at)
```

---

## Yang Ditunda ke T5 (Butuh Environment Production)

| Item | Kenapa ditunda |
|---|---|
| **T4.4.0c** — Pindah ke WSL2 filesystem | Berlaku saat setup workstation developer, bukan VPS |
| **T4.4.7** — EXPLAIN ANALYZE 5 halaman | Butuh data produksi nyata (santri + tagihan real) |
| **T4.4.10** — Load testing 5+ tenant concurrent | Butuh VPS live |
| **T4.4.11** — Penetration test & security audit | Butuh domain live + tool eksternal |
| **T4.4.12** — SLA & support tier | Keputusan bisnis — dikerjakan bersamaan dengan onboarding pesantren ke-2 |
| **T4.4.13** — Kebijakan privasi & ToS | Legal review — perlu konsultasi hukum UU PDP |
| **T4.4.14** — Disaster recovery test | Butuh backup production aktual |

---

## Panduan Developer: Cara Menjalankan Dev yang Cepat

```bash
# 1. Gunakan FrankenPHP (bukan php artisan serve)
docker compose up -d

# 2. Pastikan .env memakai Redis untuk session & cache
SESSION_DRIVER=redis
CACHE_STORE=redis
QUEUE_CONNECTION=redis

# 3. Untuk performa terbaik di Windows: pindah project ke WSL2
# Clone di: /home/{user}/projects/pesantren-platform/
# (bukan di /mnt/c/Users/...)
# Docker volume mount dari WSL2 native = 10–50x lebih cepat

# 4. Jalankan test dengan env yang benar
APP_ENV=testing DB_HOST=127.0.0.1 CACHE_STORE=array QUEUE_CONNECTION=sync php artisan test
```

---

## Panduan Production: Cache Wajib Aktif

```bash
# Dijalankan otomatis oleh deploy workflow, tapi bisa manual juga:
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan event:cache
php artisan optimize
```
