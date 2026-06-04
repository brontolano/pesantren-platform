# Panduan Onboarding Tenant Baru — Platform Pesantren

> **Untuk:** T5.3.1 — Langkah-langkah setup pesantren baru dari Portal PT
> **Target:** Onboarding selesai dalam < 1 jam

---

## Gambaran Besar

```
Portal PT (super_admin)
    ↓ Buat tenant
    ↓ Set domain + toggle modul
    ↓ Tentukan paket langganan
Pesantren
    ↓ Setup DNS CNAME (15–30 mnt propagasi)
    ↓ Terima kredensial admin
    ↓ Setup branding (logo, warna, tagline)
    ↓ Input data santri
    ↓ Aktif operasional
```

---

## Langkah 1 — Buat Tenant dari Portal PT

Login ke `https://pesantren-platform.com/admin` sebagai `super_admin`.

### A. Buat tenant baru

Masuk ke **Tenants → Buat Tenant Baru**:

| Field | Contoh | Keterangan |
|---|---|---|
| Tenant ID | `al-hikmah` | Huruf kecil, strip, tanpa spasi |
| Nama Pesantren | `Pondok Pesantren Al-Hikmah` | Nama lengkap |
| Subdomain | `al-hikmah` | Fallback: `al-hikmah.pesantren-platform.com` |
| Domain Website | `al-hikmah.sch.id` | Kosongkan jika belum punya domain |
| Domain Sistem | `sistem.al-hikmah.sch.id` | Otomatis terisi dari domain website |
| Aktif | ✅ | Centang untuk langsung aktif |

### B. Aktifkan via artisan (alternatif, lebih cepat)

```bash
docker compose exec app php artisan tenant:create "Al-Hikmah" al-hikmah
```

Command ini sekaligus membuat:
- Record tenant di database
- Domain subdomain default
- User `super_admin` awal untuk tenant

---

## Langkah 2 — Tentukan Paket & Toggle Modul

Masuk ke **Billing → Langganan Tenant → Buat Langganan**:

| Field | Nilai |
|---|---|
| Tenant | Al-Hikmah |
| Paket | Pilih paket yang sesuai (Standar / Premium) |
| Siklus | Bulanan / Tahunan |
| Override harga | Isi jika ada deal khusus, kosongkan untuk ikuti paket |

Modul akan otomatis aktif sesuai paket. Untuk toggle manual:
- **Billing → Langganan → Edit** → klik "Toggle Modul"
- Atau langsung via panel: **Tenants → Edit** → bagian modul

**Modul minimum untuk operasional:**
- ✅ Kesantrian (wajib)
- ✅ Keuangan (wajib)
- ✅ Notifikasi (wajib)
- ✅ CMS/Website (jika pakai website publik)
- ✅ PPDB (jika musim pendaftaran)

---

## Langkah 3 — Setup DNS (oleh Pesantren)

Kirimkan instruksi berikut ke pesantren (atau buka `docs/DNS-ONBOARDING.md`):

```
IP VPS: [ISI IP VPS]

Tambahkan DNS record berikut di panel domain Anda:
  A     @       → [IP VPS]
  CNAME sistem  → [IP VPS]

Setelah ditambahkan, informasikan ke kami.
Propagasi: 1–24 jam.
```

Jika pesantren tidak punya domain sendiri → **lewati langkah ini**, gunakan subdomain default.

---

## Langkah 4 — Kirim Kredensial ke Admin Pesantren

Setelah tenant aktif dan DNS propagasi selesai:

```
Kepada: [Nama Admin Pesantren]

Sistem Anda sudah aktif! Berikut informasi akses:

🌐 Website publik  : https://al-hikmah.sch.id
🔑 Panel admin     : https://sistem.al-hikmah.sch.id/admin
📧 Email login     : admin@al-hikmah.sch.id
🔐 Password awal   : [generate password acak]

Langkah pertama:
1. Login ke panel admin
2. Ganti password di menu Profil
3. Upload logo pesantren di Settings → Branding
4. Mulai input data santri
```

---

## Langkah 5 — Setup Branding (oleh Admin Pesantren)

Admin pesantren login ke panel → **Settings → Branding**:

- Upload logo (PNG/SVG, maks 2MB)
- Atur warna primer dan sekunder
- Isi tagline pesantren
- Preview website publik

---

## Langkah 6 — Import Data Santri

Lihat `docs/PANDUAN-IMPORT-SANTRI.md` untuk detail lengkap.

Ringkasan:
```bash
# Upload file Excel santri ke VPS
scp data-santri.xlsx user@VPS_IP:/opt/pesantren/imports/

# Dry-run (validasi dulu)
docker compose exec app php artisan santri:import al-hikmah \
    /opt/pesantren/imports/data-santri.xlsx --dry-run

# Import sesungguhnya
docker compose exec app php artisan santri:import al-hikmah \
    /opt/pesantren/imports/data-santri.xlsx
```

---

## Langkah 7 — Verifikasi Go-Live

Checklist sebelum menyatakan tenant aktif operasional:

- [ ] Website publik bisa diakses: `https://al-hikmah.sch.id`
- [ ] SSL valid (gembok hijau di browser)
- [ ] Panel admin bisa login: `https://sistem.al-hikmah.sch.id/admin`
- [ ] Data santri berhasil diimport dan muncul di panel
- [ ] Tagihan pertama berhasil dibuat (coba buat 1 tagihan test)
- [ ] Notifikasi WA berfungsi (kirim pesan test ke nomor sendiri)
- [ ] Isolasi data: login sebagai tenant lain, pastikan data Al-Hikmah tidak terlihat

---

## Estimasi Waktu per Langkah

| Langkah | Estimasi |
|---|---|
| Buat tenant + setup paket (langkah 1–2) | 5–10 menit |
| DNS propagasi (langkah 3) | 15 menit – 24 jam |
| Kirim kredensial & setup branding (langkah 4–5) | 15–30 menit |
| Import data santri (langkah 6) | 10–30 menit (tergantung jumlah santri) |
| Verifikasi go-live (langkah 7) | 10–15 menit |
| **Total (tanpa DNS)** | **~1 jam** |

---

## Troubleshooting Onboarding

| Masalah | Solusi |
|---|---|
| Website tidak bisa diakses setelah setup DNS | Tunggu propagasi, cek di whatsmydns.net |
| SSL error | Pastikan Cloudflare proxy dimatikan (DNS only) |
| Panel admin tidak bisa login | Cek apakah domain sistem sudah terdaftar di tabel `domains` |
| "Layanan Ditangguhkan" muncul | Cek `is_active = true` di Tenants → Edit |
| Data santri tidak muncul setelah import | Cek laporan import di `storage/app/imports/santri/` |
