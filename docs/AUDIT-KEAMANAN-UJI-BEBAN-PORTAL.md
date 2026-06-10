# Audit Keamanan & Uji Beban — Portal PWA Peserta & Wali

Tanggal: 2026-06-10 · Lingkup: tugas papan R-3/R-5 (PWA Peserta) & W4.1–W4.4 (PWA Wali)

## 1. Audit keamanan isolasi

Bukti otomatis (suite test, semua hijau):

| Permukaan | Test | Hasil |
|---|---|---|
| Attempt ujian lintas-peserta (GET/jawab/selesai) | `PortalUjianSecurityTest` (3 test) | 404 untuk penyusup; attempt pemilik tak berubah |
| Kebocoran kunci jawaban ke client | `PortalUjianSecurityTest` | field `"kunci"` tidak ada di payload Inertia |
| Gate guest portal ujian | `PortalUjianSecurityTest` | redirect/ditolak |
| Dokumen/bukti hanya pemilik + staf `manage_santri` | `PpdbFileSecurityTest` (3 test) | 403 penyusup, signed-URL ditolak tanpa tanda tangan |
| Wali: non-wali ditolak, anak lain ditolak, relasi sebatas tenant | `PortalWaliAccessTest` (3 test) | hijau |
| Isolasi tenant lintas-modul | invarian I-2 (Sprint Stabilisasi) | hijau |
| Biodata lintas-peserta | `PesertaPortalDashboardTest` | error auth |

Arsitektur pendukung: DB fisik per tenant (isolasi data keras), kepemilikan
baris via `user_id` (`resolveOwnedCandidate` / `attemptMilik`), RBAC
spatie per peran, CSP `frame-ancestors` untuk embed.

## 2. Uji beban (loopback VPS, Host: demo.pesantren.online)

Baseline sekuensial (30 req/endpoint): `/` 119ms · `/spmb/login` 108ms ·
`/spmb/manifest.json` 110ms · `/wali/manifest.json` 104ms — 0 gagal.

Konkuren 200 request, concurrency 10:

| Endpoint | Throughput | Rata-rata | p95 | Gagal |
|---|---|---|---|---|
| `/spmb/login` | ~14 req/s | 644 ms | 729 ms | 0/200 |
| `/wali/manifest.json` | ~15 req/s | 624 ms | 700 ms | 0/200 |

Load average puncak 1.03 (mesin tetap longgar). Estimasi kapasitas
musiman: ~50rb request/jam per endpoint — jauh di atas kebutuhan PPDB
pesantren tunggal (ratusan pengguna/hari).

## 3. Rekomendasi lanjutan (tidak memblokir rilis)

- Rate-limit khusus endpoint login portal (throttle bawaan Laravel sudah aktif; pertimbangkan per-tenant).
- Jika tenant > 20 aktif bersamaan, tambah worker FPM/octane atau naikkan vCPU.
- Jalankan ulang uji ini menjelang puncak musim PPDB (Mei–Juli).
