#!/bin/bash
# ============================================================
# setup-vps.sh — First-time VPS deployment script
# Platform Pesantren White-Label
# VPS: Hostinger Docker+Traefik (Ubuntu 24.04)
# ============================================================
set -euo pipefail

# --- Config ---
REPO_URL="https://github.com/brontolano/pesantren-platform.git"
APP_DIR="/opt/pesantren-platform"
DOMAIN="pesantren.online"
ACME_EMAIL="hamdan.sumedang@gmail.com"

echo "=========================================="
echo "  Platform Pesantren — VPS Setup"
echo "=========================================="

# --- Step 1: System updates ---
echo "[1/8] Updating system..."
apt-get update -qq && apt-get upgrade -y -qq

# --- Step 2: Verify Docker & Docker Compose ---
echo "[2/8] Verifying Docker..."
docker --version || { echo "ERROR: Docker not found"; exit 1; }
docker compose version || { echo "ERROR: Docker Compose not found"; exit 1; }

# --- Step 3: Setup Traefik network ---
echo "[3/8] Setting up Traefik network..."
docker network create traefik-net 2>/dev/null || echo "traefik-net already exists"

# --- Step 4: Configure Traefik for SSL ---
echo "[4/8] Configuring Traefik for Let's Encrypt SSL..."
mkdir -p /opt/traefik
cat > /opt/traefik/traefik.yml << 'EOF'
api:
  dashboard: false

entryPoints:
  web:
    address: ":80"
  websecure:
    address: ":443"

certificatesResolvers:
  letsencrypt:
    acme:
      email: ACME_EMAIL_PLACEHOLDER
      storage: /letsencrypt/acme.json
      httpChallenge:
        entryPoint: web

providers:
  docker:
    endpoint: "unix:///var/run/docker.sock"
    exposedByDefault: false
    network: traefik-net
EOF
sed -i "s/ACME_EMAIL_PLACEHOLDER/$ACME_EMAIL/" /opt/traefik/traefik.yml

mkdir -p /opt/traefik/letsencrypt
touch /opt/traefik/letsencrypt/acme.json
chmod 600 /opt/traefik/letsencrypt/acme.json

# Stop existing Hostinger Traefik and replace with our config
echo "Stopping existing Traefik..."
docker stop traefik 2>/dev/null || true
docker rm traefik 2>/dev/null || true

docker run -d \
  --name traefik \
  --restart unless-stopped \
  --network traefik-net \
  -p 80:80 \
  -p 443:443 \
  -v /var/run/docker.sock:/var/run/docker.sock:ro \
  -v /opt/traefik/traefik.yml:/etc/traefik/traefik.yml:ro \
  -v /opt/traefik/letsencrypt:/letsencrypt \
  traefik:v3.4

echo "Traefik started with Let's Encrypt SSL"

# --- Step 5: Clone repository ---
echo "[5/8] Cloning repository..."
if [ -d "$APP_DIR" ]; then
  echo "Directory exists, pulling latest..."
  cd "$APP_DIR" && git pull origin main
else
  git clone "$REPO_URL" "$APP_DIR"
  cd "$APP_DIR"
fi

# --- Step 6: Setup environment ---
echo "[6/8] Setting up environment..."
if [ ! -f "$APP_DIR/.env" ]; then
  cp "$APP_DIR/.env.production" "$APP_DIR/.env"
  echo ""
  echo "!!! PENTING: Edit .env sebelum lanjut !!!"
  echo "  nano $APP_DIR/.env"
  echo ""
  echo "Yang WAJIB diganti:"
  echo "  - DB_PASSWORD (buat password kuat)"
  echo "  - REDIS_PASSWORD (buat password kuat)"
  echo "  - MAIL_PASSWORD (dari Hostinger email)"
  echo ""
  read -p "Tekan ENTER setelah selesai edit .env..." _
fi

# --- Step 7: Generate APP_KEY if empty ---
echo "[7/8] Building & starting containers..."
cd "$APP_DIR"
docker compose -f docker-compose.production.yml up -d --build

echo "Waiting for containers to be healthy..."
sleep 15

# Generate APP_KEY
docker compose -f docker-compose.production.yml exec -T app php artisan key:generate --force

# Run migrations
docker compose -f docker-compose.production.yml exec -T app php artisan migrate --force

# Seed default data
docker compose -f docker-compose.production.yml exec -T app php artisan db:seed --force

# Cache config
docker compose -f docker-compose.production.yml exec -T app php artisan config:cache
docker compose -f docker-compose.production.yml exec -T app php artisan route:cache
docker compose -f docker-compose.production.yml exec -T app php artisan view:cache
docker compose -f docker-compose.production.yml exec -T app php artisan event:cache

# Fix storage permissions
docker compose -f docker-compose.production.yml exec -T app chown -R www-data:www-data storage bootstrap/cache

# --- Step 8: Verify ---
echo "[8/8] Verifying deployment..."
echo ""
docker compose -f docker-compose.production.yml ps
echo ""
echo "=========================================="
echo "  DEPLOYMENT SELESAI!"
echo "=========================================="
echo ""
echo "  Platform:  https://$DOMAIN"
echo "  Admin:     https://$DOMAIN/admin"
echo ""
echo "  NEXT STEPS:"
echo "  1. Pointing DNS: A record $DOMAIN → $(curl -s ifconfig.me)"
echo "  2. Pointing DNS: A record *.$DOMAIN → $(curl -s ifconfig.me)"
echo "  3. Buat tenant: docker compose -f docker-compose.production.yml exec app php artisan tenant:create"
echo "  4. Setup backup: crontab -e → tambah backup script"
echo ""
