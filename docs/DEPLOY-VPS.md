# Deploy ke VPS Hostinger

## Prerequisites
- VPS Hostinger dengan Docker+Traefik (Ubuntu 24.04)
- Domain `pesantren.online` sudah dibeli
- SSH access ke VPS

## GitHub Actions Secrets

Set ini di GitHub → Settings → Secrets → Actions:

| Secret | Value | Contoh |
|---|---|---|
| `VPS_HOST` | IP address VPS | `srv1654110.hstgr.cloud` |
| `VPS_USER` | SSH user | `root` |
| `VPS_PORT` | SSH port | `22` |
| `VPS_SSH_KEY` | Private key untuk SSH | Generate dengan `ssh-keygen` |
| `VPS_PATH` | Path app di VPS | `/opt/pesantren-platform` |

## First-Time Setup

```bash
# SSH ke VPS
ssh root@srv1654110.hstgr.cloud

# Download & jalankan setup script
curl -sL https://raw.githubusercontent.com/brontolano/pesantren-platform/main/scripts/setup-vps.sh | bash
```

Atau manual:
```bash
ssh root@srv1654110.hstgr.cloud
git clone https://github.com/brontolano/pesantren-platform.git /opt/pesantren-platform
cd /opt/pesantren-platform
chmod +x scripts/setup-vps.sh
./scripts/setup-vps.sh
```

## DNS Setup

Di Hostinger DNS Manager atau registrar domain:

| Type | Name | Value |
|---|---|---|
| A | `@` | `<IP VPS>` |
| A | `*` | `<IP VPS>` |
| CNAME | `www` | `pesantren.online` |

Untuk tenant dengan custom domain (misal `asy-syifaa.sch.id`):
- Pesantren set CNAME: `asy-syifaa.sch.id` → `pesantren.online`
- Pesantren set CNAME: `sistem.asy-syifaa.sch.id` → `pesantren.online`

## Subsequent Deploys

Otomatis via GitHub Actions saat push ke `main` (test harus hijau).

Manual:
```bash
ssh root@srv1654110.hstgr.cloud
cd /opt/pesantren-platform
git pull origin main
docker compose -f docker-compose.production.yml up -d --build
docker compose -f docker-compose.production.yml exec app php artisan migrate --force
docker compose -f docker-compose.production.yml exec app php artisan optimize:clear
```
