#!/bin/bash
# =============================================================================
# Dev Setup Script — Platform Pesantren
# Jalankan SEKALI setelah: docker compose up -d --build
# =============================================================================
# Fungsi:
#   1. Install composer dependencies ke named volume (bukan Windows filesystem)
#   2. Jalankan migrasi + seeder
#   3. Cache semua komponen Filament (KRITIS — mencegah timeout 30s)
#   4. Cache Laravel (config, route, view)
#
# PENTING: vendor/ dan bootstrap/cache/ disimpan di named Docker volumes,
# bukan di Windows filesystem → ini yang membuat dev jauh lebih cepat
# =============================================================================

set -e

echo "🚀 Setup development environment..."
echo ""

# Pastikan container app sudah running
if ! docker compose ps app 2>/dev/null | grep -q -E "running|Up"; then
    echo "❌ Container 'app' belum running."
    echo "   Jalankan dulu: docker compose up -d --build"
    exit 1
fi

echo "📦 Install composer dependencies (ke named volume — bukan Windows fs)..."
docker compose exec app composer install --no-interaction --prefer-dist --optimize-autoloader

echo ""
echo "🔑 Set storage permissions..."
docker compose exec app php artisan storage:link 2>/dev/null || true
docker compose exec app chmod -R 775 storage bootstrap/cache 2>/dev/null || true

echo ""
echo "⚙️  Jalankan migrasi..."
docker compose exec app php artisan migrate --force

echo ""
echo "🗄️  Jalankan seeder (roles & permissions)..."
docker compose exec app php artisan db:seed --force

echo ""
echo "🧹 Clear cache lama..."
docker compose exec app php artisan optimize:clear

echo ""
echo "⚡ Cache Filament components — KRITIS untuk mencegah timeout 30s..."
docker compose exec app php artisan filament:cache-components

echo ""
echo "🎨 Cache Filament icons..."
docker compose exec app php artisan icons:cache

echo ""
echo "⚡ Cache Laravel (config, route, view)..."
docker compose exec app php artisan config:cache
docker compose exec app php artisan route:cache
docker compose exec app php artisan view:cache
docker compose exec app php artisan event:cache

echo ""
echo "✅ Dev setup selesai!"
echo ""
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo "Akses:"
echo "  Landing page    : http://localhost:8000"
echo "  Admin Asy-Syifaa: http://asy-syifaa.localhost:8000/admin"
echo "  Admin Demo      : http://demo.localhost:8000/admin"
echo ""
echo "Login:"
echo "  Email   : admin@asy-syifaa.localhost"
echo "  Password: password"
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo ""
echo "💡 Tips performa:"
echo "  - Setelah setup ini, reload pertama mungkin masih 3-5 detik (worker boot)"
echo "  - Reload berikutnya: < 1 detik (PHP worker sudah di memory)"
echo "  - Jika ada error setelah update kode: docker compose restart app"
