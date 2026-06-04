# ============================================================
# start-dev.ps1 — Jalankan Platform Pesantren di lokal
# Double-click file ini atau run di PowerShell dari folder project
# ============================================================

$ErrorActionPreference = "Stop"
$ProjectPath = Split-Path -Parent $MyInvocation.MyCommand.Path
Set-Location $ProjectPath

Write-Host "================================================" -ForegroundColor Cyan
Write-Host " Platform Pesantren — Dev Startup" -ForegroundColor Cyan
Write-Host "================================================" -ForegroundColor Cyan
Write-Host ""

# 1. Stop container lama
Write-Host "[1/7] Stopping containers..." -ForegroundColor Yellow
docker compose down 2>&1 | Out-Null

# 2. Build dan start
Write-Host "[2/7] Building & starting containers..." -ForegroundColor Yellow
docker compose up -d --build
if ($LASTEXITCODE -ne 0) {
    Write-Host "ERROR: docker compose up gagal!" -ForegroundColor Red
    exit 1
}

# 3. Tunggu PostgreSQL ready
Write-Host "[3/7] Waiting for PostgreSQL..." -ForegroundColor Yellow
$maxWait = 30
$waited = 0
do {
    Start-Sleep -Seconds 2
    $waited += 2
    $pgReady = docker compose exec -T postgres pg_isready -U postgres 2>&1
    Write-Host "  Waited ${waited}s..." -ForegroundColor DarkGray
} while ($pgReady -notmatch "accepting" -and $waited -lt $maxWait)

if ($waited -ge $maxWait) {
    Write-Host "WARNING: PostgreSQL mungkin belum ready, lanjut anyway..." -ForegroundColor Yellow
}

# 4. Install vendor jika belum ada
Write-Host "[4/7] Install composer dependencies..." -ForegroundColor Yellow
docker compose exec app composer install --no-interaction --prefer-dist --optimize-autoloader

# 5. Migrate & seed
Write-Host "[5/7] Migrate database..." -ForegroundColor Yellow
docker compose exec app php artisan migrate --force

Write-Host "      Seeding roles & permissions..." -ForegroundColor Yellow
docker compose exec app php artisan db:seed --force

# 6. Cache Filament & Laravel
Write-Host "[6/7] Caching Filament & Laravel..." -ForegroundColor Yellow
docker compose exec app php artisan optimize:clear
docker compose exec app php artisan filament:cache-components
docker compose exec app php artisan icons:cache
docker compose exec app php artisan config:cache
docker compose exec app php artisan route:cache
docker compose exec app php artisan view:cache
docker compose exec app php artisan event:cache

# 7. Buat tenant jika belum ada
Write-Host "[7/7] Setup tenants..." -ForegroundColor Yellow
docker compose exec app php artisan tenant:create "Asy-Syifaa" asy-syifaa 2>&1 | ForEach-Object {
    if ($_ -notmatch "already") { Write-Host "  $_" }
}
docker compose exec app php artisan tenant:create "Demo" demo 2>&1 | ForEach-Object {
    if ($_ -notmatch "already") { Write-Host "  $_" }
}

Write-Host ""
Write-Host "================================================" -ForegroundColor Green
Write-Host " SELESAI! Platform siap diakses:" -ForegroundColor Green
Write-Host "================================================" -ForegroundColor Green
Write-Host ""
Write-Host "  Landing page    : http://localhost:8000" -ForegroundColor Cyan
Write-Host "  Admin Asy-Syifaa: http://asy-syifaa.localhost" -ForegroundColor Cyan
Write-Host "  Admin Demo      : http://demo.localhost" -ForegroundColor Cyan
Write-Host ""
Write-Host "  Login: admin@asy-syifaa.localhost / password" -ForegroundColor White
Write-Host ""
Write-Host "PASTIKAN hosts file sudah berisi:" -ForegroundColor Yellow
Write-Host "  127.0.0.1 asy-syifaa.localhost demo.localhost" -ForegroundColor White
Write-Host ""
Write-Host "Cek status: docker compose ps" -ForegroundColor DarkGray
Write-Host "Lihat log : docker compose logs app --tail=30" -ForegroundColor DarkGray
Write-Host "================================================" -ForegroundColor Green
