#!/bin/bash
# =============================================================================
# Script Backup Harian PostgreSQL — Platform Pesantren
# Sesuai Blueprint §7: "Backup harian PostgreSQL terjadwal + terenkripsi"
# =============================================================================
#
# Cara setup di VPS:
#   1. chmod +x /opt/pesantren/scripts/backup-postgres.sh
#   2. Isi variabel di bawah atau export dari environment
#   3. Tambahkan ke crontab: 0 2 * * * /opt/pesantren/scripts/backup-postgres.sh
#      (backup tiap hari jam 02:00 WIB)
#   4. Test manual: sudo -u www-data /opt/pesantren/scripts/backup-postgres.sh
# =============================================================================

set -euo pipefail

# ── Konfigurasi ──────────────────────────────────────────────────────────────
DB_HOST="${DB_HOST:-postgres}"
DB_PORT="${DB_PORT:-5432}"
DB_NAME="${DB_NAME:-pesantren_platform}"
DB_USER="${DB_USER:-pesantren}"
PGPASSWORD="${PGPASSWORD:?ERROR: PGPASSWORD belum diset}"
export PGPASSWORD

# Direktori backup — pastikan sudah ada dan hanya bisa dibaca root/www-data
BACKUP_DIR="${BACKUP_DIR:-/var/backups/pesantren-platform}"
RETENTION_DAYS="${RETENTION_DAYS:-30}"

# Enkripsi — gunakan GPG symmetric dengan passphrase kuat
BACKUP_PASSPHRASE="${BACKUP_PASSPHRASE:?ERROR: BACKUP_PASSPHRASE belum diset}"

# Notifikasi (opsional — isi jika ingin notif ke webhook/Slack)
NOTIFY_WEBHOOK="${NOTIFY_WEBHOOK:-}"

# ── Setup ─────────────────────────────────────────────────────────────────────
TIMESTAMP=$(date +"%Y%m%d_%H%M%S")
BACKUP_FILENAME="pesantren_platform_${TIMESTAMP}.sql.gz.gpg"
BACKUP_PATH="${BACKUP_DIR}/${BACKUP_FILENAME}"
LOG_FILE="${BACKUP_DIR}/backup.log"

mkdir -p "${BACKUP_DIR}"
chmod 700 "${BACKUP_DIR}"

log() {
    echo "[$(date '+%Y-%m-%d %H:%M:%S')] $*" | tee -a "${LOG_FILE}"
}

# ── Eksekusi backup ───────────────────────────────────────────────────────────
log "Memulai backup database: ${DB_NAME}"

# Dump → gzip → enkripsi GPG symmetric
pg_dump \
    -h "${DB_HOST}" \
    -p "${DB_PORT}" \
    -U "${DB_USER}" \
    -d "${DB_NAME}" \
    --no-password \
    --format=plain \
    --no-owner \
    --no-privileges \
    | gzip \
    | gpg --batch --yes \
          --symmetric \
          --cipher-algo AES256 \
          --passphrase "${BACKUP_PASSPHRASE}" \
          --output "${BACKUP_PATH}"

BACKUP_SIZE=$(du -sh "${BACKUP_PATH}" | cut -f1)
log "Backup selesai: ${BACKUP_FILENAME} (${BACKUP_SIZE})"

# ── Rotasi: hapus backup lebih dari RETENTION_DAYS ───────────────────────────
log "Menghapus backup lebih dari ${RETENTION_DAYS} hari..."
find "${BACKUP_DIR}" -name "pesantren_platform_*.sql.gz.gpg" \
    -mtime "+${RETENTION_DAYS}" -delete
REMAINING=$(find "${BACKUP_DIR}" -name "pesantren_platform_*.sql.gz.gpg" | wc -l)
log "Backup tersisa: ${REMAINING} file"

# ── Verifikasi integritas file backup ────────────────────────────────────────
gpg --batch --yes \
    --symmetric \
    --cipher-algo AES256 \
    --passphrase "${BACKUP_PASSPHRASE}" \
    --list-only "${BACKUP_PATH}" > /dev/null 2>&1 \
    && log "Verifikasi integritas: OK" \
    || { log "ERROR: Verifikasi integritas GAGAL!"; exit 1; }

# ── Notifikasi webhook (opsional) ────────────────────────────────────────────
if [[ -n "${NOTIFY_WEBHOOK}" ]]; then
    curl -s -X POST "${NOTIFY_WEBHOOK}" \
        -H 'Content-Type: application/json' \
        -d "{\"text\": \"✅ Backup DB pesantren selesai: ${BACKUP_FILENAME} (${BACKUP_SIZE})\"}" \
        || log "Peringatan: Notifikasi webhook gagal (tidak kritikal)"
fi

log "Backup selesai dengan sukses."
