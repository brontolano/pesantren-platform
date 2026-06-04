#!/bin/bash
# =============================================================================
# Script Restore PostgreSQL — Platform Pesantren
# Untuk disaster recovery test (T4.4.14) dan pemulihan data
# =============================================================================
#
# Penggunaan:
#   ./restore-postgres.sh <path-ke-file-backup.sql.gz.gpg>
#
# Contoh:
#   ./restore-postgres.sh /var/backups/pesantren-platform/pesantren_platform_20260604_020001.sql.gz.gpg
# =============================================================================

set -euo pipefail

BACKUP_FILE="${1:?ERROR: Sertakan path file backup sebagai argumen pertama}"

DB_HOST="${DB_HOST:-postgres}"
DB_PORT="${DB_PORT:-5432}"
DB_NAME="${DB_NAME:-pesantren_platform}"
DB_USER="${DB_USER:-pesantren}"
PGPASSWORD="${PGPASSWORD:?ERROR: PGPASSWORD belum diset}"
export PGPASSWORD

BACKUP_PASSPHRASE="${BACKUP_PASSPHRASE:?ERROR: BACKUP_PASSPHRASE belum diset}"

if [[ ! -f "${BACKUP_FILE}" ]]; then
    echo "ERROR: File backup tidak ditemukan: ${BACKUP_FILE}"
    exit 1
fi

echo "⚠️  PERINGATAN: Ini akan MENGHAPUS semua data di database ${DB_NAME} dan menggantinya dengan isi backup."
read -rp "Ketik 'YA LANJUTKAN' untuk konfirmasi: " CONFIRM
if [[ "${CONFIRM}" != "YA LANJUTKAN" ]]; then
    echo "Dibatalkan."
    exit 0
fi

echo "[$(date)] Mendekripsi dan restore dari: ${BACKUP_FILE}"

# Dekripsi → gunzip → restore ke PostgreSQL
gpg --batch --yes \
    --decrypt \
    --passphrase "${BACKUP_PASSPHRASE}" \
    "${BACKUP_FILE}" \
    | gunzip \
    | psql \
        -h "${DB_HOST}" \
        -p "${DB_PORT}" \
        -U "${DB_USER}" \
        -d "${DB_NAME}" \
        --no-password

echo "[$(date)] ✅ Restore selesai."
