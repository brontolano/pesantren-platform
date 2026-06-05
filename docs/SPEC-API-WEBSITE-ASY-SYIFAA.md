# Spec Kontrak API — Website Asy-Syifaa ↔ Platform

> Status: **REFERENSI KONTRAK** · Dibuat 2026-06-05 · Sumber: studi repo `asy-syifaa-website`
> Tujuan: dokumentasikan endpoint yang **sudah diharapkan** oleh website thin-client `asy-syifaa.com`,
> agar platform membangun API yang cocok lalu website tinggal di-repoint. (Pola C — API, roadmap Integrasi.)
> Catatan: pembangunan API dilakukan **bertahap oleh Hamdan**. Dokumen ini = kontraknya.

---

## 1. Konteks

Website `asy-syifaa.com` adalah **PHP native thin-client** (front-controller `router.php`). Semua bagian dinamis menarik data via API:
- **PHP layer**: `Website\Support\ApiClient` (GET, tanpa auth, base `ASY_SYIFAA_API_BASE_URL` = `https://api.asy-syifaa.com`) + proxy di `api/` (mis. `api/auth/register-spmb.php` → `ASF_API_BASE_URL` = `https://erp.asy-syifaa.com`).
- **JS layer**: `assets/js/api-config.js` → `ASF_API_BASE` (api.asy-syifaa.com), `ASF_ERP_BASE` (erp.asy-syifaa.com).

**Integrasi = arahkan base URL itu ke API platform + bangun endpoint di bawah ini.**

### Resolusi tenant
Website hanya melayani 1 pesantren. Opsi (pilih saat implementasi):
- **(A) by-domain** — daftarkan `api.asy-syifaa.com` sebagai domain tenant `asy-syifaa` → `InitializeTenancyByDomain` otomatis scope ke tenant. *(paling simpel)*
- (B) API key per tenant (header `X-Api-Key`).

### Standardisasi envelope
Website menerima dua pola (`{ok:true}` & `{success:true}`). **Standar baru: `{ "ok": true, "data": ... }`** (sertakan `success` sebagai alias bila perlu kompatibilitas).

---

## 2. Endpoint yang harus disediakan platform

| # | Method | Path | Modul platform | Prioritas |
|---|--------|------|----------------|-----------|
| 1 | POST | `/api/v1/spmb/register` | PPDB | 🔴 Tinggi |
| 2 | GET | `/api/v1/ppdb/selection-results?search={ticket}` | PPDB | 🟠 Sedang |
| 3 | GET | `/api/v1/posts?category=&limit=` · `/api/v1/posts/{slug}` | CMS | 🟠 Sedang |
| 4 | GET | `/api/public/content/gallery?limit=` · `/{slug}` | CMS (Gallery) | 🟢 Rendah |
| 5 | GET | `/api/public/staff?status=` | Kesantrian | 🟢 Rendah |
| 6 | GET | `/api/wilayah.php?level=&parent=` | (adapter wilayah) | 🟠 Sedang |
| 7 | GET | `/cek_raport_api.php?nis=` | Akademik | 🟢 Rendah |

---

### 2.1 — `POST /api/v1/spmb/register` (PPDB) — PRIORITAS UTAMA

**Request body (JSON):**
```json
{
  "student_name": "string", "nik": "string", "nisn": "string|null",
  "gender": "L|P", "birth_place": "string|null", "birth_date": "YYYY-MM-DD|null",
  "origin_school": "string|null", "address": "string|null", "village_code": "string|null",
  "parent_name": "string|null", "parent_phone": "62xxx", "guardian_phone": "62xxx",
  "participant_phone": "62xxx", "academic_year": "YYYY/YYYY",
  "source": "website", "notes": "key:value | key:value"
}
```
> Form website 6-langkah mengumpulkan data JAUH lebih lengkap (ayah/ibu/wali/KK/wilayah, gol darah, hobi, penghasilan, dll) dan menjejalkan ekstra ke `notes` ("key:value | ..."). Nomor telepon sudah dinormalisasi ke `62...` di sisi frontend.

**Response (200):**
```json
{
  "ok": true, "success": true,
  "data": { "eticket_no": "string", "registration_number": "string",
            "username": "string", "erp_username": "string", "id": 123 },
  "account": { "username": "string" }
}
```
**Catatan implementasi:**
- Map ke modul PPDB (`CalonSantri`). Field inti sudah ada; **ekstra** (NIK, NISN, data ayah/ibu/wali/KK/wilayah, gol darah) → simpan ke `data_ortu` (JSON) atau perluas skema.
- **WAJIB balas `eticket_no` + `registration_number` + `username`** → inilah PPDB v2 Fase 1 (E-tiket QR + akun). Website langsung men-generate QR dari `ETICKET:{ticket}|NAMA:{name}|WA:{phone}|USER:{username}`.
- Idempoten + anti-spam (rate-limit/captcha) karena endpoint publik.

### 2.2 — `GET /api/v1/ppdb/selection-results?search={eticket}`
```json
{ "ok": true, "data": [ {
  "registration_number": "string", "eticket_no": "string", "student_name": "string",
  "origin_school": "string", "guardian_name": "string", "academic_year": "string",
  "status": "accepted|lulus|waiting_list|cadangan|rejected" } ] }
```
> Map status platform (`lulus`/`cadangan`/`ditolak`) → nilai di atas. `search` opsional (filter by e-ticket).

### 2.3 — `GET /api/v1/posts` (artikel/pengumuman/prestasi) — CMS
- Query: `category` (`announcement` | `achievement` | umum), `limit` (default 12). Detail: `/api/v1/posts/{slug}`.
```json
{ "ok": true, "data": [ {
  "id": "string", "slug": "string", "title": "string", "excerpt": "string",
  "content": "string", "content_html": "string", "featured_image": "url|/path",
  "published_at": "ISO", "created_at": "ISO",
  "category": {"name": "string"}, "author": {"full_name": "string"}, "tags": [{"name": "string"}] } ] }
```

### 2.4 — `GET /api/public/content/gallery` — CMS (Gallery sudah ada)
- Query: `limit` (default 200). Detail: `/api/public/content/gallery/{slug}`.
```json
{ "ok": true, "data": [ { "id":"", "slug":"", "title":"", "description":"",
  "category":"", "date":"", "cover_url":"url", "folder_name":"" } ] }
```
> Website punya fallback `/assets/data/gallery-local.json` bila API kosong.

### 2.5 — `GET /api/public/staff?status=aktif` (direktori)
Dipakai `direktori-santri/pengajar/alumni`. Bentuk respons mengikuti pola `{ok, data:[...]}`. *(field final ditentukan saat implementasi — minimal: nama, foto, jabatan/kelas, status.)*

### 2.6 — `GET /api/wilayah.php?level=&parent=`
- `level`: `provinces|regencies|districts|villages`; `parent`: kode induk.
- Respons: array `[{ "code": "string", "name": "string", "postal_code": "string|null" }]`.
> Platform sudah punya endpoint wilayah (`/api/provinces`, `/api/cities/{id}`, dst) tapi **bentuk beda** → buat adapter `wilayah.php` atau sesuaikan frontend.

### 2.7 — `GET /cek_raport_api.php?nis={nis}` (e-raport) — Akademik
```json
{ "status": "success|error",
  "data": { "nama_lengkap":"", "nis":"", "kelas":"", "semester":"", "tahun_ajaran":"",
            "nilai_rata_rata":0, "ranking":0, "kelakuan":"", "kerajinan":"", "kerapihan":"",
            "sakit":0, "izin":0, "alpha":0, "catatan":"", "<kode_mapel>": 0 },
  "message": "string (jika error)" }
```
> ~30+ kode mapel (ulumu_tafsir, fiqih, nahwu, dll). Map ke modul Akademik (nilai/rapor).

---

## 3. Halaman STATIS (tanpa API — tinggal sesuaikan konten)
`alur-pendaftaran`, `visi-dan-misi`, `fasilitas-pesantren`, `struktur-organisasi`, `profil-pondok-pesantren`, `profil-guru`, `profil-kurikulum`, `profil-ekskul`, `profil-abuya`, `mahad`, `kegiatan-harian`, `kegiatan-mingguan`.

## 4. Legacy untuk DIBUANG
- `proses_daftar.php` + `koneksi.php` — insert MySQL langsung, **rawan SQL-injection**, tidak dipakai form baru. Hapus.

## 5. Urutan implementasi (saran)
1. **Endpoint #1 (PPDB register)** + resolusi tenant by-domain — paling bernilai, sekaligus mewujudkan PPDB v2 Fase 1.
2. #6 wilayah (form butuh) + #2 selection-results.
3. #3 posts + #4 gallery (CMS).
4. #5 staff + #7 raport.
5. Repoint `api.asy-syifaa.com` → platform, tes end-to-end.

> Keamanan: semua endpoint **tenant-scoped** (asy-syifaa ≠ tenant lain), CORS whitelist `asy-syifaa.com`, rate-limit endpoint publik.
