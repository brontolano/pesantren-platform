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
- Iterasi berikutnya: **area 2 — PPDB upload dokumen (putaran 2)**

## Log iterasi
| # | Area | Increment | Commit | Status |
|---|------|-----------|--------|--------|
| 1 | File Storage | Gerbang tier S3 di StorageQuotaService (`isTierAvailable` + validasi `setQuota`) + unit test | (lihat git log) | ✅ |
| 2 | PPDB upload dokumen | Status verifikasi dokumen (migrasi kolom `status/verified_at/verified_by/catatan` + helper model + scopes) + unit test | (lihat git log) | ✅ |
| 3 | PPDB ujian | Bank soal `SoalUjian` (migrasi `soal_ujian` + model PG/essay + auto-grade `nilaiUntukJawaban`) + unit test | (lihat git log) | ✅ |
| 4 | Integrasi Website/API | API key per tenant `TenantApiKey` (migrasi central + generate/hash sha256/verify timing-safe + scope active) + unit test | (lihat git log) | ✅ |
| 5 | File Storage | `diskName()` auto-resolve ke disk S3 saat object storage aktif (+ config `object_storage_disk`) + unit test | (lihat git log) | ✅ |

## Checklist target per area
### File Storage
- [x] Gerbang tier S3 server-side (`setQuota` tolak 64/128/256 bila object storage belum siap)
- [x] Resolusi disk media otomatis ke `s3` saat `media.object_storage` aktif (`diskName()` + `object_storage_disk`)
- [ ] Kompresi gambar (lanjutan) + audit log unggah/hapus

### PPDB upload dokumen & ujian
- [~] Endpoint upload dokumen peserta + signed URL + status verifikasi *(status verifikasi: kolom + helper model DONE; endpoint/signed-URL = milik tim PWA)*
- [ ] UI unggah dokumen (kamera/galeri) + checklist wajib
- [~] Ujian: bank soal + kunci jawaban *(data layer `SoalUjian` + migrasi DONE; admin authoring UI pending)*
- [~] Ujian online bertimer + auto-grading PG + nilai manual essay *(logika auto-grade `nilaiUntukJawaban` DONE; timer + UI ujian pending)*

### Integrasi Website / API
- [~] API publik read: profil tenant, artikel/berita, galeri *(sebagian sudah ada: CmsPublicApiController; statistik/jadwal PPDB pending)*
- [~] Token/API key per tenant *(model+migrasi `TenantApiKey` + generate/verify DONE; middleware auth + halaman 'Integrasi & API' pending)*
- [ ] CORS per domain tenant + rate-limit
