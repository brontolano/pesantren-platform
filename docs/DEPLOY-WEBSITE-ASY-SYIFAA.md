# Deploy Website asy-syifaa.com (Container Terpisah di VPS Platform)

> Status: **RUNBOOK** · Dibuat 2026-06-05 · Pemilik: Hamdan
> Konteks: website PHP native thin-client `asy-syifaa.com` (repo `brontolano/asy-syifaa-`)
> di-deploy di VPS platform yang sama, TERPISAH dari codebase platform (bukan bagian monolith).
> Ini implementasi nyata pertama dari PLAN-INTEGRASI-WEBSITE.md (website custom dikelola Hamdan).

## Arsitektur

```
Internet ──443/80──▶ Caddy (container pesantren-app / FrankenPHP)
                       ├─ pesantren.online + subdomain tenant → Laravel (php_server)
                       ├─ asy-syifaa.com        → reverse_proxy → asy-syifaa-nginx:80
                       │                                            └─ fastcgi → asy-syifaa-php:9000
                       └─ www.asy-syifaa.com    → redirect 301 ke apex
```

- **Source + media website:** `/opt/asy-syifaa-website` (host, ~855MB, di luar repo platform)
- **Container:** `asy-syifaa-nginx` (nginx:alpine) + `asy-syifaa-php` (php:8.2-fpm-alpine) — lihat `docker-compose.production.yml`
- **SSL:** otomatis Let's Encrypt oleh Caddy (site block eksplisit, bukan on-demand)
- **DNS:** `asy-syifaa.com`, `www.`, `api.`, `erp.` → A record 187.77.116.167 (sudah benar per 2026-06-05)

## Update konten website

```bash
# Dari mesin lokal: upload ulang file yang berubah, lalu tidak perlu restart
scp -r path/ke/file root@srv1654110.hstgr.cloud:/opt/asy-syifaa-website/path/ke/file
# PHP berubah? Tidak perlu apa-apa (no opcache validate issue di image stock).
# nginx-docker.conf berubah? docker restart asy-syifaa-nginx
```

## Catatan integrasi (fase berikutnya)

- Website memanggil `https://api.asy-syifaa.com` & `https://erp.asy-syifaa.com` (timeout 4–8 dtk,
  degrade gracefully). Endpoint TARGET ada di `SPEC-API-WEBSITE-ASY-SYIFAA.md` — saat API platform
  dibangun, daftarkan `api.asy-syifaa.com` sebagai domain tenant lalu website langsung hidup penuh.
- JANGAN memasukkan source website ke repo platform — tetap dua codebase terpisah sesuai
  PLAN-INTEGRASI-WEBSITE.md (website = wajah, platform = mesin).

## Rollback

```bash
# Hapus site block asy-syifaa di Caddyfile + 2 service di compose, lalu:
cd /opt/pesantren-platform && docker compose -f docker-compose.production.yml up -d --build
# File website di /opt/asy-syifaa-website tidak tersentuh.
```
