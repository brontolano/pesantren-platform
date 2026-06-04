#!/bin/bash
# Backup harian PostgreSQL pesantren-platform.
# Dijadwalkan via cron: 0 17 * * * (= 00:00 WIB, VPS pakai UTC).
set -euo pipefail

BACKUP_DIR=/opt/backups
RETENTION_DAYS=14
mkdir -p "$BACKUP_DIR"

TS=$(date +%Y%m%d_%H%M%S)
FILE="$BACKUP_DIR/pesantren_${TS}.sql.gz"

# Dump memakai kredensial dari env container postgres
docker exec pesantren-postgres sh -c "pg_dump -U \"\$POSTGRES_USER\" \"\$POSTGRES_DB\"" | gzip > "$FILE"

# Hapus backup lebih tua dari RETENTION_DAYS hari
find "$BACKUP_DIR" -name "pesantren_*.sql.gz" -mtime +$RETENTION_DAYS -delete

echo "$(date '+%Y-%m-%d %H:%M:%S') Backup OK: $FILE ($(du -h "$FILE" | cut -f1))"
