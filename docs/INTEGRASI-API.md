# Integrasi Website / API — Kontrak Publik

Dokumen ini menjelaskan API publik platform pesantren untuk dikonsumsi website
tenant (mis. `asy-syifaa.com`) atau aplikasi pihak ketiga.

- **Tenant di-resolve dari domain** yang dipanggil. Tiap pesantren punya domain
  sendiri (mis. `https://pesantren.asy-syifaa.com`); request ke domain itu otomatis
  beroperasi pada data tenant tersebut. Tidak ada parameter `tenant_id` di URL.
- **Bentuk respons konsisten**: `{ "ok": true, "data": ... }` saat sukses,
  `{ "ok": false, "message": "..." }` saat gagal. Endpoint render JSON untuk semua
  error (path `api/*`).
- Semua contoh memakai placeholder `https://<domain-tenant>` — ganti dengan domain
  pesantren yang dituju.

---

## 1. Autentikasi (X-Api-Key)

Sebagian endpoint **read** bersifat privat dan butuh kunci API per tenant.

### Mendapatkan kunci

1. Login panel admin tenant → menu **Pengaturan → Integrasi & API**
   (butuh izin `manage_tenant_settings`).
2. Klik **Buat Kunci API**, beri label (mis. "Website utama").
3. Kunci plaintext (`psa_…`, 44 karakter) **hanya ditampilkan sekali**. Salin &
   simpan di tempat aman. Bila hilang → **Cabut** lalu buat baru.

> Server hanya menyimpan hash SHA-256 kunci, tidak pernah plaintext. Verifikasi
> memakai perbandingan timing-safe. Mencabut kunci (set `revoked_at`) membuatnya
> langsung ditolak.

### Memakai kunci

Kirim pada setiap request privat, salah satu:

```
X-Api-Key: psa_xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx
```
atau
```
Authorization: Bearer psa_xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx
```

Kunci harus **milik tenant** yang domainnya dipanggil — kunci pesantren A tidak
bisa dipakai pada domain pesantren B (isolasi antar tenant). Bila tidak valid →
`401 { "ok": false, "message": "API key tidak valid." }`.

---

## 2. Rate limit

| Lingkup | Batas default | Kunci hitung | Override |
|---|---|---|---|
| Endpoint privat (`throttle:public-api`) | 60 / menit | per API-key (atau tenant+IP) | `config services.public_api.rate_per_minute` |
| Submit PPDB (`throttle:ppdb-submit`) | 5 / menit | per tenant + IP | `config services.public_api.ppdb_submit_per_minute` |

Melebihi batas → `429 Too Many Requests` (header `Retry-After`).

---

## 3. CORS

API (`api/*`) mengizinkan pemanggilan lintas-origin dari:

- Daftar origin eksplisit (`CORS_ALLOWED_ORIGINS`, default `https://asy-syifaa.com`,
  `https://www.asy-syifaa.com`).
- **Semua subdomain di bawah apex platform** (`CORS_APEX_DOMAINS`, default
  `asy-syifaa.com`, `pesantren.online`) — domain tenant baru otomatis tercakup.

Origin non-HTTPS, lookalike (`evilasy-syifaa.com`), dan suffix-spoof
(`asy-syifaa.com.evil.com`) ditolak. `supports_credentials = false` (jangan andalkan
cookie lintas-origin; pakai header X-Api-Key).

---

## 4. Endpoint publik — Konten CMS (tanpa auth)

Bentuk: `{ "ok": true, "data": ... }`. Bila tenant tak ter-resolve, list
mengembalikan `data: []` (bukan error) agar website tetap aman dirender.

| Method | Path | Keterangan |
|---|---|---|
| GET | `/api/v1/posts?category=&limit=` | Daftar artikel terbit (limit 1–50, default 12) |
| GET | `/api/v1/posts/{slug}` | Detail satu artikel |
| GET | `/api/public/content/gallery?limit=` | Daftar galeri terbit (limit 1–200) |
| GET | `/api/public/content/gallery/{slug}` | Detail galeri + `images[]` |
| GET | `/api/public/content/profile` | Section profil website (urut `sort`) |
| GET | `/api/public/content/settings` | Map `key → value` pengaturan website |

Contoh item `posts`:

```json
{
  "id": "12", "slug": "selamat-datang", "title": "Selamat Datang",
  "excerpt": "...", "content": "...", "content_html": "...",
  "featured_image": "https://.../img.jpg",
  "published_at": "2026-06-01T08:00:00+07:00",
  "category": { "name": "pengumuman" }, "author": { "full_name": null }, "tags": []
}
```

---

## 5. Endpoint privat — Read tenant (butuh X-Api-Key)

Middleware: `api.key` + `throttle:public-api`.

### GET `/api/v1/profil`
Profil ringkas + branding tenant.
```json
{ "ok": true, "data": {
  "nama": "Pondok Pesantren Asy-Syifaa",
  "branding": { "nama": "...", "tagline": "...", "warna_primer": "#0ea5e9",
                "warna_sekunder": "#c2410c", "logo": "https://.../logo.png" }
}}
```

### GET `/api/v1/statistik`
Statistik ringkas (hanya modul yang aktif yang muncul).
```json
{ "ok": true, "data": {
  "santri_aktif": 320, "total_santri": 350,
  "pendaftar": 145, "pendaftar_lulus": 90
}}
```

### GET `/api/v1/jadwal-ppdb`
Jadwal ujian PPDB (urut tanggal lalu jam).
```json
{ "ok": true, "data": [
  { "kategori": "Tulis", "tanggal": "2026-07-10",
    "waktu_mulai": "08:00", "waktu_selesai": "10:00", "ruang": "Aula 1" }
]}
```

Contoh pemanggilan:
```bash
curl -s https://<domain-tenant>/api/v1/statistik \
  -H "X-Api-Key: psa_xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx"
```

---

## 6. Endpoint publik — Submit PPDB

### POST `/api/v1/spmb/register`
Terima pendaftaran dari form website. **Tanpa X-Api-Key**, tetapi dilindungi
honeypot + rate-limit (`throttle:ppdb-submit`, 5/menit per tenant+IP).

**Anti-spam (honeypot):** sertakan field tersembunyi bernama `website` yang
dikosongkan (sembunyikan via CSS). Bot pengisi-otomatis akan mengisinya →
request ditolak `422`. Jangan tampilkan field ini ke pengguna.

```html
<!-- contoh di form -->
<input type="text" name="website" tabindex="-1" autocomplete="off"
       style="position:absolute;left:-9999px" aria-hidden="true">
```

**Field body** (form-data / JSON):

| Field | Wajib | Catatan |
|---|---|---|
| `student_name` | ✓ | nama calon santri |
| `gender` | ✓ | `L` atau `P` |
| `birth_date` | ✓ | tanggal (YYYY-MM-DD) |
| `nik`, `nisn` | – | identitas |
| `birth_place`, `origin_school`, `address`, `village_code` | – | data tambahan |
| `parent_name`, `parent_phone`, `guardian_phone`, `participant_phone` | – | kontak (≥1 disarankan) |
| `academic_year`, `source`, `notes` | – | meta |
| `website` | – | **honeypot — biarkan kosong** |

Sukses → `201`:
```json
{ "ok": true, "success": true,
  "data": { "id": 123, "eticket_no": "PPDB-2026-0001",
            "registration_number": "PPDB-2026-0001", "username": "62812xxxx",
            "tracking_token": "..." },
  "account": { "username": "62812xxxx" } }
```

Username portal = no. HP peserta (fallback nomor pendaftaran). Notifikasi WhatsApp
e-tiket dikirim otomatis (n8n→WAHA, fire-and-forget). Penerbitan kredensial login
dilakukan staf dari panel (aksi "Kirim Kredensial").

Contoh:
```bash
curl -s -X POST https://<domain-tenant>/api/v1/spmb/register \
  -H "Content-Type: application/json" \
  -d '{"student_name":"Ahmad","gender":"L","birth_date":"2012-05-01",
       "guardian_phone":"081234567890","website":""}'
```

```js
await fetch(`https://<domain-tenant>/api/v1/spmb/register`, {
  method: 'POST',
  headers: { 'Content-Type': 'application/json' },
  body: JSON.stringify({ student_name, gender, birth_date, guardian_phone, website: '' }),
});
```

---

## 7. Kode status

| Kode | Arti |
|---|---|
| 200 / 201 | Sukses |
| 401 | API key tidak ada / salah / bukan milik tenant ini |
| 404 | Tenant tak ter-resolve, atau resource tidak ditemukan |
| 422 | Validasi gagal / honeypot terpicu |
| 429 | Melebihi rate limit |

---

## 8. Sisa langkah manual (di luar kode — keputusan/akses Hamdan)

Belum dikerjakan otomatis; perlu DNS / keputusan pemilik:

- **Subdomain delegation & verifikasi domain** — SOP saat onboarding tenant baru.
- **Repoint `api.asy-syifaa.com`** (DNS) bila ingin host API di subdomain khusus.
- **Plugin WordPress** (publish) untuk konsumsi endpoint dari tema WP.
- **Uji coba end-to-end** dengan client Asy-Syifaa di produksi.
