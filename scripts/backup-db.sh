#!/bin/bash
# Backup harian PostgreSQL pesantren-platform — SEMUA database (central + per-tenant).
# Sebelumnya hanya $POSTGRES_DB (central) yang ter-dump; DB tenant (DB-per-tenant,
# stancl/tenancy) luput dari backup. Skrip ini menemukan semua DB non-template
# secara dinamis sehingga tenant baru otomatis ikut ter-backup.
# Dijadwalkan via cron: 0 17 * * * (= 00:00 WIB, VPS pakai UTC).
set -euo pipefail

BACKUP_DIR=/opt/backups
RETENTION_DAYS=14
mkdir -p "$BACKUP_DIR"

TS=$(date +%Y%m%d_%H%M%S)

# Roles & globals (pg_dumpall --globals-only) — dibutuhkan saat restore dari nol.
docker exec pesantren-postgres sh -c 'pg_dumpall -U "$POSTGRES_USER" --globals-only' \
  | gzip > "$BACKUP_DIR/globals_${TS}.sql.gz"

# Daftar semua DB non-template (central + tenant*), kecuali DB maintenance 'postgres'.
DBS=$(docker exec pesantren-postgres sh -c \
  'psql -U "$POSTGRES_USER" -d postgres -tAc "SELECT datname FROM pg_database WHERE datistemplate = false AND datname <> '\''postgres'\''"')

COUNT=0
for DB in $DBS; do
  docker exec pesantren-postgres sh -c 'pg_dump -U "$POSTGRES_USER" "$1"' _ "$DB" \
    | gzip > "$BACKUP_DIR/pesantren_${DB}_${TS}.sql.gz"
  COUNT=$((COUNT + 1))
done

# Hapus backup lebih tua dari RETENTION_DAYS hari (pola lama pesantren_* tetap tercakup).
find "$BACKUP_DIR" \( -name "pesantren_*.sql.gz" -o -name "globals_*.sql.gz" \) -mtime +"$RETENTION_DAYS" -delete

echo "$(date '+%Y-%m-%d %H:%M:%S') Backup OK: ${COUNT} database + globals -> $BACKUP_DIR (ts ${TS})"
