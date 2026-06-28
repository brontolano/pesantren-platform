# Stage 0: Composer deps (vendor/) — dibutuhkan stage frontend karena tema Filament
# custom meng-@import 'vendor/filament/.../theme.css' & @source memindai app/Filament.
FROM composer:latest AS vendor
WORKDIR /app
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader --prefer-dist \
    --no-interaction --ignore-platform-reqs

# Stage 1: Build frontend assets
FROM node:22-alpine AS frontend
WORKDIR /app
COPY package.json package-lock.json ./
RUN npm ci
COPY vite.config.js ./
COPY resources/ resources/
# Theme Filament butuh vendor (@import) + app/Filament (@source) saat build CSS.
COPY app/ app/
COPY --from=vendor /app/vendor vendor
RUN npm run build

# Stage 2: PHP application with FrankenPHP
FROM dunglas/frankenphp:latest-php8.3

# Install PHP extensions
RUN install-php-extensions \
    pdo_pgsql \
    pgsql \
    zip \
    intl \
    bcmath \
    pcntl \
    redis \
    gd \
    opcache

# postgresql-client 16 (cocok dgn server pg16) untuk backup/restore DB tenant dari panel.
# Default Debian bookworm = pg15 → mismatch; pakai repo PGDG untuk v16.
RUN apt-get update \
    && apt-get install -y --no-install-recommends gnupg lsb-release wget ca-certificates gzip \
    && install -d /usr/share/postgresql-common/pgdg \
    && wget --quiet -O /usr/share/postgresql-common/pgdg/apt.postgresql.org.asc https://www.postgresql.org/media/keys/ACCC4CF8.asc \
    && echo "deb [signed-by=/usr/share/postgresql-common/pgdg/apt.postgresql.org.asc] http://apt.postgresql.org/pub/repos/apt $(lsb_release -cs)-pgdg main" > /etc/apt/sources.list.d/pgdg.list \
    && apt-get update \
    && apt-get install -y --no-install-recommends postgresql-client-16 \
    && rm -rf /var/lib/apt/lists/*

# OPcache config for production (no revalidation)
RUN echo "opcache.enable=1\n\
opcache.memory_consumption=256\n\
opcache.max_accelerated_files=20000\n\
opcache.validate_timestamps=0\n\
opcache.interned_strings_buffer=16" > /usr/local/etc/php/conf.d/opcache-prod.ini

COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

WORKDIR /app

COPY . .
COPY --from=frontend /app/public/build public/build

# Override FrankenPHP default Caddyfile dengan konfigurasi on-demand TLS kita
COPY Caddyfile /etc/caddy/Caddyfile

# Pakai vendor/ dari stage cache (composer.lock sama) lalu cukup bangun autoloader
# optimal + package:discover (post-autoload-dump). MENGHINDARI composer install
# ulang dari nol setiap perubahan kode app — yang sebelumnya mengunduh seluruh
# paket lagi (rapuh terhadap blip download GitHub/codeload, mis. HTTP 400).
COPY --from=vendor /app/vendor vendor
RUN composer dump-autoload --no-dev --optimize --classmap-authoritative

# Buat direktori temp Livewire (disk 'livewire-tmp', lihat config/filesystems.php).
# Direktori ini tidak ada di git (file-nya di-gitignore). Tanpa ini,
# Storage::disk('livewire-tmp')->put() gagal senyap (throw:false) →
# Livewire tidak bisa size() → loop "Menunggu ukuran berkas" tanpa henti.
RUN mkdir -p storage/app/livewire-tmp \
    && chown -R www-data:www-data storage bootstrap/cache

EXPOSE 80 443

CMD ["frankenphp", "run", "--config", "/etc/caddy/Caddyfile"]
