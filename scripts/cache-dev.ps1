# ============================================================
# cache-dev.ps1 — Jalankan setelah container sudah UP
# Gunakan ini saat: panel admin lambat / ada error setelah update kode
# Lokasi: scripts/ — boleh dobel-klik dari sini.
# ============================================================

# Skrip ada di scripts/, naik 1 level ke ROOT project (tempat docker-compose.yml).
$ProjectPath = Split-Path -Parent (Split-Path -Parent $MyInvocation.MyCommand.Path)
Set-Location $ProjectPath

Write-Host "Clearing and rebuilding all caches..." -ForegroundColor Yellow

docker compose exec app php artisan optimize:clear
docker compose exec app php artisan filament:cache-components
docker compose exec app php artisan icons:cache
docker compose exec app php artisan config:cache
docker compose exec app php artisan route:cache
docker compose exec app php artisan view:cache
docker compose exec app php artisan event:cache

Write-Host ""
Write-Host "Done! Buka: http://asy-syifaa.localhost/admin" -ForegroundColor Green
