# Auto-Backlog Loop — State

Pekerja otonom backlog (interval 10 menit, bergiliran). **Pagar pengaman:**
branch `auto/backlog` saja · TIDAK push ke `main` · TIDAK deploy · 1 increment + test
per iterasi · berhenti bila area tuntas atau menemui blocker yang butuh keputusan Hamdan.

## Rotasi area (urut)
1. **File Storage** — driver S3-compatible (unlock tier 64/128/256), kompresi gambar + audit log
2. **PPDB upload dokumen & ujian** — endpoint+UI unggah dokumen peserta, ujian online (bank soal, bertimer, grading)
3. **Integrasi Website / API** — API publik headless, token per-tenant, CORS, rate-limit

> Hindari menyentuh file milik kerja tim (PWA Peserta SPMB `/spmb`, PesertaAccountService,
> PesertaOtpService, PesertaAuthController, PortalWali). Tambahan bersifat aditif.

## Pointer rotasi
- **LOOP DIHENTIKAN @ iterasi #11** — target backend aditif-aman di kedua area
  tersisa (PPDB ujian & Integrasi API) sudah tuntas. Sisa pekerjaan = UI/Filament/
  wiring yang butuh arahan desain Hamdan & tes Livewire+DB (di luar disiplin loop
  "increment kecil + unit test tanpa DB"). Lihat "Sisa & langkah lanjut" di bawah.

## Sisa & langkah lanjut (butuh keputusan/arahan)
- **PPDB ujian:** backend lengkap (SoalUjian + UjianScoringService + SesiUjian). Sisa:
  Filament resource authoring bank soal + UI ujian bertimer (front-end; UI peserta = /spmb tim).
- **Integrasi API:** TenantApiKey + middleware + rate-limit helper siap. Sisa: daftarkan
  alias middleware & named limiter `public-api` di provider, CORS per-domain tenant,
  endpoint publik statistik, Filament page 'Integrasi & API' (kelola key).
- **Rekomendasi:** review & **merge `auto/backlog` → main**, jalankan migrasi
  (`tenant_api_keys` central + `*_dokumen` verifikasi/`soal_ujian`/`sesi_ujian` tenant),
  deploy, lalu garap UI di atas fondasi ini.

> Area habis target aditif aman (LEWATI): **File Storage** (3/3 ✅) · **PPDB upload dokumen** (sisanya milik tim /spmb). Rotasi efektif kini: PPDB ujian ↔ Integrasi Website/API.

## Log iterasi
| # | Area | Increment | Commit | Status |
|---|------|-----------|--------|--------|
| 1 | File Storage | Gerbang tier S3 di StorageQuotaService (`isTierAvailable` + validasi `setQuota`) + unit test | (lihat git log) | ✅ |
| 2 | PPDB upload dokumen | Status verifikasi dokumen (migrasi kolom `status/verified_at/verified_by/catatan` + helper model + scopes) + unit test | (lihat git log) | ✅ |
| 3 | PPDB ujian | Bank soal `SoalUjian` (migrasi `soal_ujian` + model PG/essay + auto-grade `nilaiUntukJawaban`) + unit test | (lihat git log) | ✅ |
| 4 | Integrasi Website/API | API key per tenant `TenantApiKey` (migrasi central + generate/hash sha256/verify timing-safe + scope active) + unit test | (lihat git log) | ✅ |
| 5 | File Storage | `diskName()` auto-resolve ke disk S3 saat object storage aktif (+ config `object_storage_disk`) + unit test | (lihat git log) | ✅ |
| 6 | PPDB upload dokumen | Registry tipe dokumen `PpdbDocumentTypes` (label + wajib/opsional + `missingRequired()` checklist) + unit test | (lihat git log) | ✅ |
| 7 | PPDB ujian | `UjianScoringService` (agregasi auto-grade PG + pisah essay manual + persen) atas `SoalUjian` + unit test | (lihat git log) | ✅ |
| 8 | Integrasi Website/API | Middleware `AuthenticateTenantApiKey` (X-Api-Key/Bearer → TenantApiKey, isolasi tenant, last_used) + `ApiKeyExtractor` pure + unit test | (lihat git log) | ✅ |
| 9 | File Storage | Test coverage `MediaImageService.compress` (guard mime/file + resize GD-guarded); konfirmasi kompresi+audit sudah ada → area File Storage 3/3 | (lihat git log) | ✅ |
| 10 | PPDB ujian | Persistensi `SesiUjian` (migrasi `sesi_ujian` + model: jawaban/auto_score/butuh_manual, `isiDariSkor` dari UjianScoringService, persenAuto, status) + unit test | (lihat git log) | ✅ |
| 11 | Integrasi Website/API | Rate-limit API publik `PublicApiThrottle` (kunci per api-key/tenant+IP/IP, limit dari config `services.public_api`) + unit test | (lihat git log) | ✅ |

## Checklist target per area
### File Storage
- [x] Gerbang tier S3 server-side (`setQuota` tolak 64/128/256 bila object storage belum siap)
- [x] Resolusi disk media otomatis ke `s3` saat `media.object_storage` aktif (`diskName()` + `object_storage_disk`)
- [x] Kompresi gambar + audit log unggah/hapus *(sudah ada: MediaImageService + MediaAudit/MediaFile; ditambah test coverage `compress`)*

### PPDB upload dokumen & ujian
- [~] Endpoint upload dokumen peserta + signed URL + status verifikasi *(status verifikasi: kolom + helper model DONE; endpoint/signed-URL = milik tim PWA)*
- [~] UI unggah dokumen (kamera/galeri) + checklist wajib *(registry `PpdbDocumentTypes` + `missingRequired()` DONE; UI kamera/galeri = milik tim PWA)*

> Sisa area PPDB upload (endpoint/signed-URL/UI) adalah milik tim /spmb — tak ada lagi target aditif yang aman di sini.
- [~] Ujian: bank soal + kunci jawaban *(data layer `SoalUjian` + migrasi DONE; admin authoring UI pending)*
- [~] Ujian online bertimer + auto-grading PG + nilai manual essay *(auto-grade + `UjianScoringService` + persistensi `SesiUjian` DONE; backend lengkap — sisa: Filament resource authoring bank soal & UI ujian bertimer/timer = front-end)*

### Integrasi Website / API
- [~] API publik read: profil tenant, artikel/berita, galeri *(sebagian sudah ada: CmsPublicApiController; statistik/jadwal PPDB pending)*
- [~] Token/API key per tenant *(model+migrasi `TenantApiKey` + middleware `AuthenticateTenantApiKey` DONE; wiring alias + halaman 'Integrasi & API' pending)*
- [~] CORS per domain tenant + rate-limit *(rate-limit `PublicApiThrottle` + config DONE; CORS per-domain + wiring limiter pending)*
