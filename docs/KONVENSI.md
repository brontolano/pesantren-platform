# Konvensi Kerja — pesantren.online

> Tujuan: tiap tugas cukup memuat **satu irisan kecil** konteks. Aturan di bawah
> menjaga batas itu tetap rapi. Lihat juga `../../BLUEPRINT-ARSITEKTUR-5-LAPISAN.md`.

## 1. Lima lapisan & rumahnya
| Lapisan | Rumah | Tahu soal UI? |
|---|---|---|
| Backend (Model/Service/Event) | `Modules/{X}/app/` | TIDAK |
| Kontrak (DTO + route) | `Modules/{X}/app/Data/*Data.php` + `routes/tenant/{portal}.php` | jembatan |
| UI template | `Modules/{X}/app/Filament/*` (admin) · `resources/js/Pages/*.vue` (portal) | YA, hanya tampil |
| Frontend logic | `resources/js/composables/*` + `resources/js/services/*` | YA |
| Integrasi | `Modules/{Notifikasi,AI}` + `integrasi/` | adapter |

## 2. Aturan keras (gampang dicek)
- **`.vue` tidak boleh `fetch`/`axios` langsung.** Page → composable → service → endpoint.
- **Satu file route < 150 baris.** Kalau lewat, pecah per-portal/per-domain.
- **Filament tinggal di modulnya**, bukan `app/Filament/` (target; migrasi bertahap).
- **Payload lintas backend↔frontend = DTO** (`spatie/laravel-data`), bukan array bebas.
- **Operasi uang lewat `JurnalService`/Ledger**, ref TIDAK boleh null (idempotensi).
- **File tenant via `/_tenant-storage`**, bukan `Storage::url` (cegah 404 Docker).

## 3. Penamaan
- JS: `camelCase` · DB kolom/tabel: `snake_case` · Komponen Vue & DTO: `PascalCase`
- Route name: `{portal}.{domain}.{aksi}` → `wali.tagihan.index`, `guru.setoran.store`
- Service FE: `services/{portal}.js` · Composable: `composables/{portal}/use{Fitur}.js`

## 4. Urutan baku tiap fitur (contract-first)
1. **Sepakati kontrak**: DTO + route (kecil, cepat direview).
2. **Backend**: Service + Controller yang mengembalikan DTO. `php artisan typescript:transform`.
3. **Frontend**: service → composable → page, ditip oleh `resources/js/types/generated.d.ts`.

Karena (1) memutus kebergantungan, langkah 2 & 3 boleh dikerjakan di sesi/agent terpisah
dengan konteks masing-masing kecil.

## 5. Konteks minimum untuk satu tugas AI
Muat **hanya**: folder `Modules/{X}/` + `resources/js/types/generated.d.ts` +
`resources/js/Components/Ui/README.md` + `Modules/{X}/AGENTS.md`. **Bukan** seluruh repo.

## 5b. Ukur kepatuhan (otomatis)
Jalankan `php scripts/check-konvensi.php` untuk mengukur "konvensi debt" (route gemuk,
`.vue` fetch langsung, `Intl.NumberFormat` manual, Filament flat). Baseline tersimpan di
`docs/KONVENSI-BASELINE.md`. Tiap gelombang rollout: re-ukur, pastikan angka **turun**.
CI ratchet: `php scripts/check-konvensi.php --strict` (exit 1 bila ada debt pada cakupan yang sudah dibereskan).

## 6. Sebelum deploy
`php artisan test` lokal · render-test authed `TenantPanelAuthedSmokeTest` ·
smoke test `memory_limit=2G`. CI `test.yml` merah karena DB-per-tenant — bukan gate.
