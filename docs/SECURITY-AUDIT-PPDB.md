# Checklist Audit Keamanan — Modul PPDB / PWA SPMB

Status per 2026-06-07 (FASE 4). ✅ = terpasang & terverifikasi · ⚠️ = perlu cek berkala/manual.

## 1. Isolasi tenant
- [✅] **Database-per-tenant** (stancl/tenancy, Blueprint §3) — data PPDB fisik terpisah per pesantren.
- [✅] Tenancy di-resolve `InitializeTenancyByDomain` sebelum auth pada grup `/spmb` & serving file.
- [✅] `PreventAccessFromCentralDomains` aktif pada grup tenant.
- [⚠️] Uji lintas-tenant berkala: pastikan peserta tenant A tak bisa akses data tenant B (manual/otomatis).

## 2. Kepemilikan & akses data
- [✅] Peserta hanya akses pendaftarannya sendiri: `resolveOwnedCandidate(user)` (where user_id), bukan by route id.
- [✅] Serving file (`PpdbFileController`): panitia (`manage_santri`) = semua; peserta = hanya file pendaftarannya (cek `calonSantri->user_id`).
- [✅] Tagihan/dokumen via relasi `calon->...` (firstOrFail) → kepemilikan + isolasi.
- [✅] Middleware `peserta` (EnsureUserIsPeserta): guest→login, non-peserta→403.

## 3. Upload berkas
- [✅] Validasi server: `mimes:pdf,jpg,jpeg,png,webp`, `max:5120` (5 MB) di controller + `PpdbDocumentUploadService` (double-check).
- [✅] File disimpan di disk **privat** (`local`), TIDAK web-public; akses hanya via route ber-auth `ppdb.file.*`.
- [✅] Nama file UUID (anti path-traversal / tebak nama).
- [⚠️] Pertimbangkan scan antivirus / batas kuota per peserta (belum).

## 4. Autentikasi & rate-limit
- [✅] Satu sistem auth (web guard + Spatie) — Hukum #2 (tak ada guard baru).
- [✅] OTP aktivasi/reset: rate-limit per-nomor + cooldown (`PesertaOtpService`), throttle route per IP.
- [✅] Login peserta dibatasi per identifier+IP.
- [✅] `must_reset_password` memaksa ganti password sementara saat login pertama.

## 5. Alur verifikasi & keputusan
- [✅] Status verifikasi dokumen (pending/verified/rejected) + jejak `verified_by`/`verified_at`/catatan.
- [✅] Bukti bayar → `menunggu_verifikasi` → panitia konfirmasi `lunas` (TagihanPpdbResource).
- [✅] Keputusan lulus/cadangan butuh ≥1 hasil ujian (`PpdbAdmissionDecisionService`).
- [✅] Daftar ulang → Santri **transaksional + idempoten** (hanya status lulus; guard NIS).

## 6. API key publik (headless)
- [✅] `TenantApiKey`: simpan hash sha256, verifikasi timing-safe, prefix non-rahasia, `revoked_at`.
- [✅] Middleware `AuthenticateTenantApiKey` + rate-limit `PublicApiThrottle` (per key/tenant+IP).

## Sisa MANUAL (belum/diluar lingkup otomatis)
- ⚠️ **Uji beban musiman** (#69): jalankan load-test ke staging, BUKAN produksi live. Belum dieksekusi.
- ⚠️ Audit dependensi (composer/npm) berkala.
