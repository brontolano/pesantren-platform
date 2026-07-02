# Runbook Pemulihan Bencana — pesantren.online

> Blueprint Arsitektur §16 ("Ketahanan & Pemulihan"). SPOF disadari: 1 VPS memuat
> app, DB central, semua DB tenant, Redis, MinIO, n8n, WAHA. **Pemulihan adalah
> satu-satunya pertahanan — ia tidak boleh berupa asumsi.** Dokumen ini wajib
> diuji lewat drill (lihat §4), bukan hanya dibaca.

## 0. Aset & lokasi
- **VPS produksi:** `187.77.116.167` (alias SSH `vps-hostinger`), Ubuntu + Docker.
- **Backup DB:** `/opt/backups` — `scripts/backup-db.sh` via cron `0 17 * * *` (00:00 WIB). Retensi 14 hari. Mencakup `globals_*.sql.gz[.enc]` (roles) + `pesantren_<db>_*.sql.gz[.enc]` (central + tiap tenant).
- **Storage media tenant:** volume MinIO (`minio_data`) + `app_storage`.
- **Compose produksi:** `/opt/pesantren-platform/docker-compose.production.yml` (100% turunan git — anti-drift §7).

## 1. Enkripsi backup (opsional, dianjurkan)
`backup-db.sh` mengenkripsi **bila** env `BACKUP_ENC_PASS` diset (AES-256, file `.enc`); bila tidak, backup polos.

**Aktifkan:** set passphrase kuat di lingkungan cron (mis. `/etc/environment` atau baris cron), lalu uji 1 putaran backup.

**Dekripsi** sebelum restore file `.enc`:
```bash
openssl enc -d -aes-256-cbc -pbkdf2 -pass env:BACKUP_ENC_PASS -in <file>.sql.gz.enc | gunzip > <file>.sql
# atau langsung pipa ke psql (lihat §2)
```
> **Peringatan:** passphrase simetris berada di VPS → melindungi file backup yang
> ter-eksfiltrasi, TIDAK melindungi terhadap kompromi VPS penuh. Peningkatan: kunci
> publik (`gpg`/`age`) — enkripsi dgn public key di VPS, private key disimpan
> OFFLINE. Itu langkah berikutnya bila ada audit keamanan formal.

## 2. Urutan pemulihan (RTO target: catat saat drill)
Pulihkan **berurutan** — dependensi dari bawah ke atas:

**(a) Infra dasar**
```bash
ssh vps-hostinger
cd /opt/pesantren-platform && docker compose -f docker-compose.production.yml up -d postgres redis minio
```

**(b) Globals (roles) — WAJIB sebelum DB**
```bash
zcat globals_<ts>.sql.gz | docker exec -i pesantren-postgres psql -U "$POSTGRES_USER" -d postgres
# (.enc: openssl enc -d ... | gunzip | docker exec -i ... psql ...)
```

**(c) DB CENTRAL dulu** (`pesantren_platform` — registry tenant/domain/billing)
```bash
docker exec pesantren-postgres psql -U "$POSTGRES_USER" -d postgres -c 'CREATE DATABASE pesantren_platform;'
zcat pesantren_pesantren_platform_<ts>.sql.gz | docker exec -i pesantren-postgres psql -U "$POSTGRES_USER" -d pesantren_platform
```

**(d) DB TIAP TENANT** (`tenant<id>` — central harus pulih dulu agar daftar tenant diketahui)
```bash
for f in pesantren_tenant*_<ts>.sql.gz; do
  db=$(echo "$f" | sed -E 's/^pesantren_(.+)_[0-9]{8}_[0-9]{6}\.sql\.gz.*/\1/')
  docker exec pesantren-postgres psql -U "$POSTGRES_USER" -d postgres -c "CREATE DATABASE \"$db\";"
  zcat "$f" | docker exec -i pesantren-postgres psql -U "$POSTGRES_USER" -d "$db"
done
```

**(e) App + storage + worker**
```bash
docker compose -f docker-compose.production.yml up -d --build
# pulihkan volume MinIO/app_storage dari snapshot terpisah bila perlu.
```

**(f) DNS / SSL** — pastikan A/AAAA `pesantren.online` + domain tenant menunjuk ke VPS.
SSL on-demand (Caddy) terbit otomatis saat akses pertama. **Coupling SSL↔DB
disadari:** endpoint `/caddy/ssl-check` butuh app+DB hidup → SSL baru pulih setelah
(c)+(e) jalan.

**(g) Integritas pasca-restore — WAJIB**
```bash
# Buku besar: deteksi & sembuhkan jurnal yang hilang akibat restore parsial.
docker compose exec app php artisan ledger:rekonsiliasi            # deteksi semua tenant
docker compose exec app php artisan ledger:rekonsiliasi --repost   # sembuhkan
docker compose exec app php artisan keuangan:rekonsiliasi-harian   # drift status tagihan (I-7)
```

## 3. Verifikasi go-live
- `https://pesantren.online/` → landing 200; `/platform` login super_admin.
- 1 domain tenant (mis. `demo.pesantren.online`) → muat + login.
- `ledger:rekonsiliasi` = nol drift di semua tenant.

## 4. Drill restore (kuartalan — JANGAN dilewati)
Backup tanpa drill ≠ backup. Tiap kuartal:
1. Siapkan lingkungan kosong (VPS staging / lokal Docker).
2. Jalankan §2 (a→g) dari backup PRODUKSI terbaru.
3. **Ukur & catat RTO nyata** (menit dari mulai s/d §3 hijau) di tabel bawah.
4. Catat kendala + perbaiki runbook.

| Tanggal drill | RTO nyata | Pelaksana | Catatan |
|---|---|---|---|
| _(belum pernah — utang §16)_ | — | — | Jadwalkan drill pertama |

## 5. Yang masih utang (§16)
- ⬜ Drill restore pertama (isi tabel §4).
- ⬜ Uptime monitoring eksternal (Uptime Kuma / UptimeRobot) — alert saat padam.
- ⬜ (Opsi) Enkripsi kunci-publik (gpg/age) menggantikan passphrase simetris.
- ⬜ (Opsi) Salinan backup OFFSITE (saat ini /opt/backups satu disk dgn DB = bukan offsite).
