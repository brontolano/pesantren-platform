# Panduan DNS Onboarding — Custom Domain untuk Pesantren

> Dokumen ini untuk pesantren yang ingin menggunakan domain sendiri (misal `asy-syifaa.sch.id`) sebagai alamat website dan panel admin sistem.

---

## Arsitektur Domain (Sesuai Blueprint §3b)

```
VPS PT Rojo Bronto Lano (1 server, IP: [IP_VPS])
│
├─ Portal PT: pesantren-platform.com
│
├─ Pesantren dengan domain sendiri:
│   ├─ asy-syifaa.sch.id            → Website publik (modul CMS)
│   └─ sistem.asy-syifaa.sch.id     → Panel admin
│
└─ Pesantren tanpa domain sendiri:
    ├─ asy-syifaa.pesantren-platform.com      → Website publik
    └─ sistem.asy-syifaa.pesantren-platform.com → Panel admin
```

---

## Langkah Setup Domain Custom

### 1. Dapatkan IP VPS

Hubungi admin platform untuk mendapatkan IP VPS yang digunakan.

### 2. Setup DNS Record

Masuk ke panel DNS domain Anda (Niagahoster, Domainesia, Cloudflare, dll) dan tambahkan:

| Type  | Name            | Value         | TTL  |
|-------|-----------------|---------------|------|
| A     | @               | [IP_VPS]      | 3600 |
| CNAME | sistem          | [IP_VPS]      | 3600 |

> **Catatan:** Jika menggunakan Cloudflare, nonaktifkan proxy (set ke DNS only / ☁️ abu-abu) agar SSL Traefik bisa bekerja.

### 3. Hubungi Admin Platform

Setelah DNS record ditambahkan, informasikan ke admin platform:
- **Domain website:** `asy-syifaa.sch.id`
- **Domain sistem:** `sistem.asy-syifaa.sch.id`

Admin akan mendaftarkan domain di panel PT dan memverifikasi SSL otomatis via Let's Encrypt.

### 4. Propagasi DNS

Propagasi DNS biasanya memakan waktu 1–24 jam. Setelah propagasi selesai:
- Website publik: `https://asy-syifaa.sch.id`
- Panel admin: `https://sistem.asy-syifaa.sch.id/admin`

---

## Troubleshooting

| Masalah | Kemungkinan Penyebab | Solusi |
|---|---|---|
| Website tidak bisa dibuka | DNS belum propagasi | Tunggu 1–24 jam, cek di [whatsmydns.net](https://whatsmydns.net) |
| SSL error / Not Secure | Cloudflare proxy aktif | Matikan proxy di Cloudflare |
| Panel admin redirect loop | Domain sistem belum terdaftar | Hubungi admin platform |
| Halaman "Layanan Ditangguhkan" | Pembayaran belum dikonfirmasi | Hubungi admin platform |

---

## Tenant tanpa Domain Sendiri (Default)

Jika pesantren tidak memiliki domain sendiri, platform menyediakan subdomain gratis:
- Website: `{nama-tenant}.pesantren-platform.com`
- Admin: `sistem.{nama-tenant}.pesantren-platform.com`

Tidak perlu setup DNS apapun dari sisi pesantren.
