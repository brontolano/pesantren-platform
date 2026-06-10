# SOP — Delegasi Subdomain Pesantren ke Platform

Skenario: pesantren punya website sendiri (mis. `pondokku.sch.id`, dikelola tim
mereka) dan ingin **subdomain tertentu** menunjuk ke platform, mis.:

- `ppdb.pondokku.sch.id` → form/portal PPDB platform
- `portal.pondokku.sch.id` / `sistem.pondokku.sch.id` → panel sistem (launcher)

Website utama TIDAK pindah; hanya subdomain yang didelegasikan.

## Cara kerja (mekanisme platform — sudah live)

1. **Resolusi tenant by-domain**: middleware tenancy mencocokkan `Host` request
   dengan tabel `domains` (DB central). Domain apa pun yang terdaftar di sana
   otomatis beroperasi pada data tenant pemiliknya.
2. **SSL otomatis (on-demand TLS)**: Caddy memanggil `/caddy/ssl-check?domain=`
   yang hanya mengizinkan sertifikat untuk central domain + baris di tabel
   `domains` → subdomain baru dapat sertifikat Let's Encrypt **saat akses
   pertama**, tanpa konfigurasi manual. (Catatan: kuota LE ±50 sertifikat/minggu.)
3. **Tipe domain** (kolom `domains.type`) menentukan tampilan root `/`:
   - `subdomain` (default) → website CMS tenant (landing).
   - `sistem` → halaman launcher "Portal Sistem" (grid ikon).
   - `website` → dipakai bila domain itu adalah website utama tenant.
   Jalur fungsional (`/ppdb`, `/spmb`, `/wali`, `/admin`, `/api/v1/...`) tersedia
   di SEMUA tipe.

## Langkah delegasi (per subdomain)

### A. Sisi pesantren (tim IT/web mereka) — 1 record DNS

| Tipe | Nama | Nilai | TTL |
|---|---|---|---|
| CNAME | `ppdb` (atau `portal`/`sistem`) | `pesantren.online.` | 3600 |

> Alternatif bila registrar tak mendukung CNAME ke apex eksternal:
> A record → `187.77.116.167` (IP VPS). CNAME lebih disarankan (tahan ganti IP).

### B. Sisi platform (admin/owner) — daftarkan domain ke tenant

Via panel: **Platform → Tenants → Edit tenant → tambah domain** (field
`sistem_domain`/`website` sesuai tipe), ATAU via tinker:

```php
$t = \App\Models\Tenant::find('pondokku');
$t->domains()->firstOrCreate(
    ['domain' => 'ppdb.pondokku.sch.id'],
    ['type' => 'subdomain'] // atau 'sistem' utk launcher
);
```

### C. Verifikasi (urutan WAJIB: DNS dulu, baru akses)

```bash
# 1) DNS sudah mengarah? (tunggu propagasi bila belum)
nslookup ppdb.pondokku.sch.id        # harus resolve ke IP VPS

# 2) Akses pertama memicu penerbitan SSL on-demand (bisa 5-15 dtk)
curl -sI https://ppdb.pondokku.sch.id/ppdb | head -3   # 200/302, sertifikat valid

# 3) Cek resolusi tenant benar (judul halaman = branding tenant ybs)
```

Checklist kelulusan:
- [ ] `https://<subdomain>/ppdb` memuat form PPDB ber-branding tenant.
- [ ] `https://<subdomain>/spmb/login` & `/wali/login` 200.
- [ ] `https://<subdomain>/api/v1/posts` balas `{"ok":true,...}` (CORS subdomain
      apex otomatis tercakup `CORS_APEX_DOMAINS` bila apex didaftarkan; bila
      tidak, tambahkan ke `CORS_ALLOWED_ORIGINS`).
- [ ] Domain TIDAK menampilkan data tenant lain (cek nama pesantren di halaman).

## Pencabutan (rollback)

1. Hapus baris dari tabel `domains` → SSL check menolak → domain berhenti
   dilayani (404 ringan utk host tak dikenal, lihat hardening host).
2. Pesantren menghapus record DNS-nya.

## Hal yang TIDAK perlu dilakukan

- Tidak perlu sentuh Caddyfile/nginx — on-demand TLS menangani sertifikat.
- Tidak perlu deploy/restart — tabel `domains` dibaca runtime.
- Tidak perlu wildcard DNS di sisi pesantren.

## Kasus nyata terpasang

- `pesantren.asy-syifaa.com` (type `sistem`) → launcher Portal Sistem; website
  utama `asy-syifaa.com` tetap PHP standalone milik pesantren. Pola yang sama
  berlaku untuk klien berikutnya.
