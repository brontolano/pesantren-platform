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

# Enkripsi at-rest (Blueprint §9/§16 — data santri = anak di bawah umur).
# OPT-IN: set env BACKUP_ENC_PASS (mis. di /etc/environment atau baris cron) →
# tiap file di-enkripsi AES-256 (ekstensi .enc). Bila TIDAK diset, backup tetap
# polos seperti semula (tanpa kegagalan diam-diam). Dekripsi: lihat
# docs/RUNBOOK-PEMULIHAN-BENCANA.md. Catatan keamanan: passphrase simetris ada di
# env VPS → lindungi terhadap eksfiltrasi FILE; untuk tahan kompromi VPS penuh,
# tingkatkan ke kunci-publik (gpg/age) — lihat runbook.
ENC_EXT=""
[ -n "${BACKUP_ENC_PASS:-}" ] && ENC_EXT=".enc"
maybe_encrypt() {  # stdin -> stdout; enkripsi hanya bila BACKUP_ENC_PASS diset
  if [ -n "${BACKUP_ENC_PASS:-}" ]; then
    openssl enc -aes-256-cbc -pbkdf2 -salt -pass env:BACKUP_ENC_PASS
  else
    cat
  fi
}

# Roles & globals (pg_dumpall --globals-only) — dibutuhkan saat restore dari nol.
docker exec pesantren-postgres sh -c 'pg_dumpall -U "$POSTGRES_USER" --globals-only' \
  | gzip | maybe_encrypt > "$BACKUP_DIR/globals_${TS}.sql.gz${ENC_EXT}"

# Daftar semua DB non-template (central + tenant*), kecuali DB maintenance 'postgres'.
DBS=$(docker exec pesantren-postgres sh -c \
  'psql -U "$POSTGRES_USER" -d postgres -tAc "SELECT datname FROM pg_database WHERE datistemplate = false AND datname <> '\''postgres'\''"')

COUNT=0
for DB in $DBS; do
  docker exec pesantren-postgres sh -c 'pg_dump -U "$POSTGRES_USER" "$1"' _ "$DB" \
    | gzip | maybe_encrypt > "$BACKUP_DIR/pesantren_${DB}_${TS}.sql.gz${ENC_EXT}"
  COUNT=$((COUNT + 1))
done

# Hapus backup lebih tua dari RETENTION_DAYS hari. Glob .sql.gz* mencakup file
# polos (.sql.gz) MAUPUN terenkripsi (.sql.gz.enc).
find "$BACKUP_DIR" \( -name "pesantren_*.sql.gz*" -o -name "globals_*.sql.gz*" \) -mtime +"$RETENTION_DAYS" -delete

ENC_NOTE=$([ -n "$ENC_EXT" ] && echo "terenkripsi" || echo "polos (BACKUP_ENC_PASS tak diset)")
echo "$(date '+%Y-%m-%d %H:%M:%S') Backup OK: ${COUNT} database + globals -> $BACKUP_DIR (ts ${TS}, ${ENC_NOTE})"
