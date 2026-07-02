# BLUEPRINT — Runtime per-Tenant (Isolasi T2)

> **Refs:** Issue #342 (T2), lanjutan #341 (T1 healthcheck+autorestart), pendahulu #343 (T3 white-label). Epik: #344.
> **Status:** Dokumen keputusan untuk owner — BELUM implementasi. Ditulis 2026-07-02 berdasarkan file infra nyata di repo (`docker-compose.production.yml`, `Caddyfile`, `Dockerfile`, `scripts/setup-vps.sh`, `/opt/deploy.sh` di VPS).

---

## 1. Ringkasan eksekutif

**Masalah.** Hari ini SATU container FrankenPHP (`pesantren-app`) melayani SEMUA tenant. Insiden 2026-06-30 membuktikan risikonya: runtime bersama degraded karena tekanan memori → seluruh platform blank (HTTP 200, body 0 byte). Data sudah terisolasi (DB fisik per tenant, PostgreSQL), tapi **runtime PHP belum**.

**Solusi T2.** Caddy tetap satu (reverse proxy + TLS on-demand), tapi proses PHP dipecah: tenant besar dapat container sendiri; tenant kecil berbagi pool. Crash/lonjakan satu tenant tidak lagi menumbangkan yang lain.

**Rekomendasi utama:** model **HYBRID (c)** — mulai dari pool bersama (kondisi sekarang), lalu "promosikan" tenant ke container sendiri saat besar/berbayar. Di VPS 8GB saat ini hanya muat **±3–5 container tenant tambahan**; skala lebih dari itu butuh upgrade VPS (perkiraan Rp150–500 ribu/bulan, lihat §3).

**Kapan perlu pindah T2 (trigger):**

| Sinyal | Ambang saran |
|---|---|
| Jumlah tenant aktif harian | > 5 tenant dengan trafik nyata (bukan hanya terdaftar) |
| Ada tenant "berat" | 1 tenant menyumbang > 40% request / sering memicu OOM |
| Insiden runtime bersama | Terulang ≥ 2× meski T1 (healthcheck+autorestart) sudah jalan |
| Komersial | Ada tenant berbayar yang menuntut SLA / white-label (menyambung T3) |
| Anggaran | Owner siap menambah biaya server bulanan |

Sampai salah satu trigger terpenuhi, **T1 (#341) cukup** — jangan bayar kompleksitas T2 lebih awal.

---

## 2. Model penempatan

### (a) Container per-tenant (semua tenant)
- ✅ Isolasi maksimal, model mental sederhana ("1 pesantren = 1 kotak").
- ❌ Boros RAM: tiap container membawa opcache 256M + thread FrankenPHP sendiri, walau tenant idle. Di 8GB langsung mentok di segelintir tenant.
- ❌ Deploy = restart N container; provisioning tenant baru = wajib spin container (lebih rapuh).

### (b) Per-grup (tenant besar sendiri, kecil berbagi pool)
- ✅ Hemat RAM: puluhan tenant kecil cukup 1–2 pool.
- ✅ Blast radius terkendali: tenant berat diisolasi, pool kecil tetap saling memengaruhi tapi bebannya rendah.
- ❌ Perlu kebijakan "kapan tenant dipromosikan" + routing per-Host yang dirawat.

### (c) Hybrid ⭐ REKOMENDASI
Kombinasi (b) + jalur evolusi:
1. **Pool default** = container `pesantren-app` yang sudah ada (tidak ada perubahan untuk tenant kecil).
2. **Container dedicated** hanya untuk tenant yang memenuhi kriteria promosi (trafik tinggi / berbayar / bermasalah).
3. Semua container memakai **image yang sama** (`pesantren-platform-app:latest`) — beda hanya di env/route. Ini penting: kode masih satu (lihat §7, itu ranah T3).

**Alasan:** VPS 8GB tidak muat model (a); model (c) memberi 90% manfaat isolasi dengan biaya RAM minimal, dan tidak mengubah apa pun untuk tenant existing sampai dipromosikan satu per satu. Rollback per-tenant juga trivial (hapus route → kembali ke pool).

---

## 3. Estimasi kapasitas & biaya

> ⚠️ Semua angka di bagian ini adalah **PERKIRAAN** dari konfigurasi di repo + pengalaman umum FrankenPHP/Laravel; validasi dengan pengukuran nyata di PoC (§6).

### 3.1 RAM per container FrankenPHP (dari konfigurasi nyata)

Fakta dari repo:
- `Dockerfile`: **tidak ada** direktif `worker`/`num_threads` di Caddyfile → FrankenPHP jalan **mode classic**, thread default = **2 × vCPU** (di VPS 2 vCPU ≈ 4 thread PHP).
- `memory_limit = 256M` per request (uploads-prod.ini), `opcache.memory_consumption = 256M` (dialokasikan sekali per container, shared antar thread).
- Compose produksi tidak memberi `mem_limit` pada `app` (hanya Ollama yang dibatasi 4G).

Perkiraan per container app:

| Komponen | Perkiraan |
|---|---|
| Proses FrankenPHP/Caddy dasar | ~60–100 MB |
| Opcache (alokasi penuh saat panas) | ~150–256 MB |
| Heap PHP aktif (Filament berat; 2–4 thread × 50–150 MB rata-rata, puncak 256M/req) | ~150–400 MB |
| **Total wajar per container** | **~350–500 MB idle-hangat, ~700 MB–1 GB saat sibuk** |

Angka perencanaan yang dipakai blueprint ini: **512 MB/container (mem_limit disarankan), cadangan burst 1 GB untuk pool utama**.

### 3.2 Berapa tenant muat di VPS 8GB sekarang?

Penghuni RAM saat ini (compose produksi): Ollama **4G (hard limit)**, Postgres ±0.5–1G, Redis + n8n + WAHA + MinIO + nginx/php situs asy-syifaa ±1–1.5G, 3 queue worker (Laravel CLI) ±0.4–0.6G, OS+Docker ±0.5G → **sisa untuk container app: ±1.5–2.5 GB** (inilah kenapa insiden 30 Juni terjadi).

→ Dengan 512 MB/container: pool utama (1) + **±2–4 container dedicated**. **Titik pecah: ±3–5 tenant dedicated** — setelah itu WAJIB salah satu:
- pindahkan/bekukan Ollama (langsung bebas 4 GB → ±8–10 container), atau
- upgrade VPS.

### 3.3 Opsi upgrade VPS Hostinger (perkiraan harga 2026, cek halaman harga saat eksekusi)

| Plan (KVM) | vCPU / RAM | Muat container tenant (512M/unit, setelah overhead tetap ±4G tanpa Ollama di 8G) | Perkiraan biaya/bln |
|---|---|---|---|
| KVM 2 (sekarang) | 2 / 8 GB | pool + 3–5 dedicated | ~Rp 100–150 rb (sudah berjalan) |
| KVM 4 | 4 / 16 GB | pool + **±15–20** dedicated | ~Rp 150–250 rb |
| KVM 8 | 8 / 32 GB | pool + **±40–50** dedicated | ~Rp 300–500 rb |
| VPS ke-2 (pisahkan Ollama/WAHA/n8n) | 2 / 8 GB | membebaskan ±5 GB di VPS utama | ~Rp 100–150 rb |

Catatan: upgrade Hostinger KVM biasanya in-place (resize) — downtime menit-an, snapshot dulu. Alternatif murah pertama: **pindahkan Ollama ke VPS terpisah** sebelum upgrade plan utama.

### 3.4 Rumus biaya per tenant (untuk pricing owner)

`biaya_infra_per_tenant_dedicated ≈ (harga VPS / jumlah slot container)` → di KVM 4 ±Rp 10–15 rb/tenant/bulan. Tenant dedicated layak dihargai premium (paket white-label T3 #343).

---

## 4. Routing Caddy → upstream per-Host

Hari ini Caddy embedded di container app (Dockerfile `CMD frankenphp run`) dan blok `:443` catch-all langsung `php_server`. Untuk T2, pola yang sudah terbukti di repo **sudah ada**: `asy-syifaa.com` di-reverse-proxy ke container lain (`website-nginx`). Kita generalisasi.

### 4.1 Arsitektur target

```
Internet ──► Caddy (tetap di container pesantren-app, TLS on-demand tetap)
   ├─ Host = tenantbesar.pesantren.online  ──► reverse_proxy app-tenantbesar:80
   ├─ Host = sistem.asy-syifaa.sch.id (custom domain tenant dedicated) ─► reverse_proxy app-asysyifaa:80
   └─ selain itu (catch-all :443)          ──► php_server (pool = proses lokal, tenant kecil + central)
```

Keputusan desain: **Caddy TIDAK dipisah jadi container sendiri dulu.** Memisahkan Caddy lebih bersih tapi menambah 1 langkah migrasi TLS/volume (`caddy_data`); cukup dilakukan nanti jika pool utama sendiri mau bisa di-restart tanpa memutus TLS. PoC memakai Caddy existing.

### 4.2 Sketsa Caddyfile (per tenant dedicated, di ATAS blok catch-all)

```caddyfile
# ── Tenant dedicated: Asy-Syifaa ──
# Subdomain platform + custom domain diarahkan ke container khusus.
asysyifaa.pesantren.online, sistem.asy-syifaa.sch.id {
    tls {
        on_demand   # custom domain tetap lolos ssl-check yang sama
    }
    encode zstd br gzip
    reverse_proxy app-asysyifaa:80
}
```

- Host-match eksplisit **selalu menang** atas blok `:443` catch-all → tenant lain tidak terpengaruh.
- **Custom domain**: mekanisme existing tidak berubah — `on_demand_tls { ask http://localhost/caddy/ssl-check }` tetap memvalidasi ke Laravel pool (tabel domains central). Yang perlu dijaga: endpoint `/caddy/ssl-check` HARUS tetap dilayani pool (localhost), bukan container tenant.
- Peta domain→container dirawat sebagai **file include per-tenant**: `caddy/tenants/asysyifaa.caddy`, dan Caddyfile utama memakai `import /etc/caddy/tenants/*.caddy`. Reload tanpa restart: `docker exec pesantren-app caddy reload --config /etc/caddy/Caddyfile` (zero-downtime).

### 4.3 Sketsa service compose per tenant dedicated

```yaml
  app-asysyifaa:
    image: pesantren-platform-app:latest   # image SAMA dengan pool
    container_name: pesantren-app-asysyifaa
    restart: unless-stopped
    command: ["frankenphp", "run", "--config", "/etc/caddy/Caddyfile.tenant"]  # Caddyfile minimal :80 php_server, TANPA on-demand TLS
    environment:
      - APP_ENV=production
      - SERVER_NAME=:80
    env_file: [.env]            # env sama; resolusi tenant tetap by Host (stancl)
    mem_limit: 512m             # ← pagar RAM per tenant (pelajaran insiden 30 Juni)
    volumes:
      - app_storage:/app/storage    # storage bersama (perlu review saat T3)
      - ./.env:/app/.env:ro
    networks: [internal]
```

Tenancy tetap resolve by `Host` header — tidak perlu perubahan kode; container dedicated "kebetulan" hanya menerima Host tenant itu.

---

## 5. Deploy rolling & provisioning otomatis

### 5.1 Deploy rolling per container

`/opt/deploy.sh` hari ini: build image → recreate `pesantren-app` → migrate tenant. Perluasan T2 (tetap 1 image untuk semua):

1. `docker compose build app` (image baru `pesantren-platform-app:latest`).
2. **Rolling**: untuk tiap container tenant dedicated (urut satu-satu): `docker compose up -d app-<tenant>` → tunggu healthcheck hijau (T1 #341) → lanjut berikutnya. Tenant lain tetap dilayani versi lama beberapa detik–menit.
3. Terakhir recreate pool `app` + `worker*`.
4. `tenants:migrate` tetap SEKALI dari satu container saja (DB per-tenant, bukan per-container) — **jangan** migrate dari tiap container (race).

Catatan jujur: karena kode masih satu image, "deploy 1 tenant saja" belum benar-benar ada — yang rolling adalah **urutan restart**, bukan versi kode per tenant. Versi per-tenant = T3.

### 5.2 Provisioning tenant baru (selaras Wizard Instalasi Tenant)

Default tenant baru = **masuk pool** (tanpa langkah tambahan, wizard existing `/platform/instalasi-tenant` tidak berubah). Promosi ke dedicated = aksi eksplisit:

- Perintah baru `php artisan tenant:runtime {tenant} --dedicated`:
  1. Tulis `caddy/tenants/{tenant}.caddy` (template §4.2, domain diambil dari tabel domains).
  2. Tulis override compose `docker-compose.tenants.yml` (service §4.3).
  3. `docker compose up -d app-{tenant}` lalu `caddy reload`.
  4. Tambah cek ke `TenantHealthCheck` (cek ke-11: "runtime dedicated respons 200").
- Kebalikannya `--pool`: hapus route + `caddy reload` (trafik langsung jatuh ke catch-all/pool) → baru stop container. **Ini sekaligus prosedur rollback per tenant, tanpa downtime.**
- Halaman wizard cukup ditambah 1 kartu status "Runtime: Pool / Dedicated" + tombol (fase setelah PoC).

---

## 6. Rencana PoC — 1 tenant terpisah

Kandidat: **tenant demo** (bukan asy-syifaa/albakids) — kalau rusak tidak ada korban.

**Langkah:**
1. Snapshot VPS (Hostinger panel) + catat `docker stats` baseline 24 jam (bukti angka §3).
2. Buat `Caddyfile.tenant` minimal (`:80 { root * /app/public; php_server }`) — masuk repo.
3. Tambah service `app-demo` (mem_limit 512m) di file compose override baru `docker-compose.tenants.yml`; `up -d`.
4. Tambah blok Host `demo.pesantren.online → reverse_proxy app-demo:80` (file include) + `caddy reload`.
5. Uji: login panel demo, upload file, jalankan 1 job antrian, cek 2–3 tenant lain TIDAK terganggu.
6. **Uji isolasi (inti PoC):** bebani `app-demo` sampai OOM (mis. loop request berat) → verifikasi container demo di-restart Docker sendirian, tenant lain tetap 200 OK. Ini re-play insiden 30 Juni dengan hasil berbeda.
7. Ukur `docker stats app-demo` idle vs sibuk → koreksi angka §3 di dokumen ini.

**Kriteria sukses:** (a) demo dilayani container terpisah dengan TLS normal termasuk skenario custom-domain; (b) OOM demo tidak menyentuh tenant lain; (c) deploy rolling: restart `app-demo` tidak memutus tenant lain dan sebaliknya; (d) RAM terukur ≤ 512 MB idle-hangat.

**Rollback:** hapus file include route → `caddy reload` (≤ 1 detik, trafik kembali ke pool) → `docker rm -f pesantren-app-demo`. Tidak ada perubahan DB/kode; snapshot VPS sebagai jaring terakhir.

---

## 7. Risiko & apa yang TIDAK diselesaikan T2

**Tidak diselesaikan (sengaja — ranah T3 #343):**
- **Kode tetap satu image**: bug kode/regresi deploy tetap menimpa semua tenant serentak. T2 hanya mengisolasi crash runtime (OOM, proses hang, lonjakan beban).
- Versi/tema per-tenant, white-label penuh, jadwal rilis per-tenant → T3.
- `storage` masih volume bersama; Redis & Postgres masih instance bersama (DB-nya saja yang terpisah) — Redis penuh / Postgres down tetap kena semua. Bisa jadi T2.5 bila terbukti perlu.

**Risiko utama:**

| Risiko | Mitigasi |
|---|---|
| RAM 8GB jebol saat menambah container (mengulang insiden 30 Juni dalam bentuk baru) | `mem_limit` WAJIB di tiap container tenant; tambah dedicated hanya sesuai slot §3.2; alarm memori (T1) |
| Peta route Caddy salah → tenant nyasar ke container lain | Route hanya lewat file include yang digenerate perintah artisan (bukan edit manual); `caddy validate` sebelum reload |
| `ssl-check` tak terjangkau bila pool down → custom domain baru gagal TLS | Endpoint tetap di pool + T1 autorestart menjaga pool; sertifikat existing tetap tersimpan di `caddy_data` |
| Scheduler/queue jalan ganda (tiap container punya cron/worker?) | Worker & scheduler TETAP hanya di service `worker*` existing; container tenant = HTTP saja |
| Deploy lupa salah satu container → versi kode beda antar container terhadap DB yang sudah termigrasi | deploy.sh loop dari daftar compose (bukan hardcode); healthcheck gate per container |
| Drift dokumen vs harga/plan Hostinger | Angka §3.3 ditandai perkiraan; cek ulang saat eksekusi |

---

## 8. Keputusan owner yang dibutuhkan sebelum eksekusi

| # | Keputusan | Opsi | Rekomendasi |
|---|---|---|---|
| 1 | Model penempatan | (a) per-tenant / (b) per-grup / (c) hybrid | **(c) hybrid** |
| 2 | Kriteria promosi tenant ke dedicated | trafik / berbayar / manual owner | **Manual owner dulu** (jumlah tenant masih kecil) |
| 3 | Anggaran server | tetap 8GB (maks 3–5 dedicated) / pindah Ollama ke VPS ke-2 / upgrade KVM 4 (±Rp150–250 rb/bln) | **Tetap 8GB untuk PoC**; putuskan upgrade SETELAH angka PoC |
| 4 | Tenant PoC | demo / lainnya | **demo** |
| 5 | Kapan mulai PoC | sekarang / setelah T1 (#341) terverifikasi jalan | **Setelah T1 hidup** — autorestart adalah jaring pengaman PoC |
| 6 | Ollama tetap di VPS utama? (4GB!) | tetap / pindah / matikan saat butuh slot | Putuskan saat butuh slot ke-3+ |
| 7 | Harga jual paket "runtime dedicated" ke pesantren | — (input pricing T3) | Sinkronkan dengan paket white-label #343 |

---

*Dokumen ini deliverable Issue #342 tahap "blueprint + estimasi". Setelah owner mengisi tabel §8, langkah berikutnya = PoC §6 (issue tetap terbuka sampai PoC selesai).*
