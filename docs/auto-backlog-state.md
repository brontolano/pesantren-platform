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

## FASE 4 — Selesaikan 11 task PWA Peserta SPMB (keputusan Hamdan final)
Keputusan: nilai=keputusan-only · daftar ulang=PENUH(→Santri+Wali) · kartu ujian=LENGKAP(model JadwalUjian+QR) · #103/#69=prep saja.
- #20 **Notif WA #61**: PpdbWhatsappNotifier + method + wiring verifikasi dokumen/pembayaran.
- #21 **Notif WA #64**: `sendPengumuman` ter-wire di `PpdbAdmissionDecisionService::decide()` (kirim WA saat keputusan lulus/cadangan/ditolak dipublikasikan).
- #22 **#63a**: model+migrasi `jadwal_ujian` + Filament `JadwalUjianResource`.
- #23 **#63b**: kartu peserta `/spmb/kartu-ujian` (controller+route+Vue, identitas+jadwal+QR API publik+cetak, taut Dashboard). **#63 LENGKAP.**
- #24 **#65a**: `DaftarUlangService` (lulus→Santri aktif + wali_user_id + nis dari nomor pendaftaran; transaksional, idempoten via status-guard→'daftar_ulang'). Skema Santri: nis NOT NULL+unique, tanggal_lahir/jenis_kelamin wajib.
- #25 **#65b**: endpoint `daftarUlang`/`prosesDaftarUlang` + route GET/POST `/spmb/daftar-ulang` + Vue DaftarUlang (konfirmasi → service) + CTA dashboard (prop `bolehDaftarUlang`). **#65 LENGKAP.**
- #26 **#66**: pengingat WA — `sendPengingatDaftarUlang` + aksi Filament "Ingatkan Daftar Ulang" (CalonSantriResource, visible saat lulus). Timeline aktivitas = sudah tercakup stepper 6-tahap di Dashboard. **#66 SELESAI.**
- Sisa: #62/#67 test · #68 offline · #69 checklist · #70 smoke · #103 TWA config.

## FASE 3 — Selesaikan Modul PPDB + PWA PPDB (lalu deploy)
- Berikutnya: verifikasi BUKTI BAYAR (panitia) → lalu authoring bank soal → sisa feasible → finalisasi (merge+deploy+migrate).
- Log FASE 3:
  - F2 upload peserta (dokumen + bukti bayar) — committed.
  - #16 ERP **verifikasi dokumen**: aksi Verifikasi/Tolak + kolom status di CalonSantriDokumenResource (set verified/rejected + verified_at/by + catatan).
  - #17 ERP **verifikasi bukti bayar**: `TagihanPpdbResource` baru (panel tenant, group PPDB) — list tagihan + aksi Konfirmasi Lunas / Tolak Bukti.
  - #18 **serving file aman**: `PpdbFileController` (stream dokumen/bukti dari disk privat + cek auth: panitia semua, peserta miliknya) + route `ppdb.file.*` + link "Lihat" di TagihanPpdbResource, CalonSantriDokumenResource & halaman Berkas peserta.
  - #19 ERP **authoring bank soal**: `SoalUjianResource` Filament (CRUD SoalUjian: kategori, pertanyaan, tipe pg/essay, pilihan KeyValue, kunci, bobot, aktif) + Pages List/Create/Edit. Gate manage_santri.
  - **FASE 3 SELESAI (feasible)** → finalisasi: merge auto/backlog→main + deploy + tenants:migrate.
  - SISA butuh-keputusan Hamdan: tampilkan nilai ujian ke peserta · model jadwal/kartu ujian · daftar-ulang→Santri · fitur PWA Wali baru.

## FASE 2 — PWA front-end — **LOOP BERHENTI @ iterasi #15**
- 4 increment PWA Peserta selesai (Berkas, Pengumuman, Tagihan, + taut Dashboard). Build hijau.
- Semua fitur PWA tersisa butuh **model/data baru ATAU keputusan desain/kebijakan Hamdan** → STOP.

### Sisa PWA (butuh arahan Hamdan)
- **Hasil Ujian/Nilai (PWA Peserta):** data `HasilUjian` ADA & relasinya ada, TAPI menampilkan
  skor ke calon santri = keputusan kebijakan (umumkan nilai mentah, atau cukup keputusan via Pengumuman?). → tunggu keputusan.
- **Kartu Ujian / jadwal ujian:** belum ada model jadwal ujian (perlu dibuat dulu).
- **Daftar Ulang:** flow besar + konversi peserta lulus → Santri (lihat project "App Santri").
- **PWA Wali Santri:** sudah sangat lengkap; fitur baru perlu desain spesifik.
- Rotasi (interval 60 dtk): **PWA Peserta SPMB ↔ PWA Wali Santri**
- **Verifikasi:** `npm run build` (Vue compile ~5s) + `php -l`. BUKAN unit test.
- **Bias AMAN:** utamakan FILE BARU (halaman/komponen/endpoint/route untuk fitur yang
  BELUM ada). HINDARI merombak logika di file matang (controller terstruktur, Vue padat,
  PWA Wali 23 halaman) — cukup tambah method/route/section aditif. Kedua area MATANG &
  hot → risiko bentrok merge diterima Hamdan.
- Branch `auto/backlog`, TANPA push/deploy. Build harus hijau sebelum commit.

## (Fase 1 backend SELESAI — 11 increment, log di bawah)

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
| 12 | PWA Peserta (Fase 2) | Halaman **Berkas** `/spmb/berkas` (Vue + route + `PesertaPortalController::berkas`) — checklist dokumen via PpdbDocumentTypes + status verifikasi CalonSantriDokumen; build hijau | (lihat git log) | ✅ |
| 13 | PWA Peserta (Fase 2) | Halaman **Pengumuman** `/spmb/pengumuman` (Vue + route + `pengumuman()` reuse helper status) — keputusan lulus/cadangan/ditolak atau status proses; build hijau | (lihat git log) | ✅ |
| 14 | PWA Peserta (Fase 2) | **Tautkan** Berkas & Pengumuman dari Dashboard (section navigasi cepat + import `Link`) — keduanya kini terjangkau (bukan orphan); build hijau | (lihat git log) | ✅ |
| 15 | PWA Peserta (Fase 2) | Halaman **Tagihan** `/spmb/tagihan` (Vue + route + `tagihan()` pakai `TagihanPpdb` yg sudah ada) — daftar tagihan + ringkasan total/lunas/sisa (Rupiah); ditaut dari Dashboard (nav 3 kolom); build hijau | (lihat git log) | ✅ |

> **Mendekati exhaustion Fase 2:** PWA Peserta sisa kandidat = Kartu Ujian / Tagihan-view yang BUTUH model/data baru (jadwal ujian, tagihan PPDB) → keputusan desain Hamdan. PWA Wali sudah lengkap. Iterasi berikut kemungkinan BERHENTI & rekomendasikan merge.

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
