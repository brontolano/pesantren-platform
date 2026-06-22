# Baseline Konvensi 5-Lapisan

> Snapshot terukur "konvensi debt" untuk memantau rollout (G0–G8) turun ke 0.
> Re-ukur kapan saja: `php scripts/check-konvensi.php` (CI: `--strict` → exit 1 bila ada debt).
> Acuan: `KONVENSI.md` · `PLAYBOOK-IRISAN.md` · `../../ROLLOUT-5-LAPISAN-BACKLOG.md`.

## Baseline — 2026-06-22 (sebelum rollout)

| Lapisan | Metrik | Angka | Target |
|---|---|---|---|
| L2 | Route file > 150 baris | **2** (`tenant.php` 626, `web.php` 199) | 0 (pecah per-portal) |
| L3b/L4 | `.vue` Pages/Layouts fetch/mutasi langsung | **28** | 0 (pindah ke `services/`) |
| L5 | `Intl.NumberFormat` manual | **19** | 0 (pakai `MoneyText`/`Shared/format`) |
| L3a | Filament flat di `app/` | **484 file** | hanya Core tersisa; domain → `Modules/{X}/app/Filament/` |
| — | **Konvensi debt (route+vue+intl)** | **49** | **0** |

### Offender terbesar (titik mulai)
- **L3b fetch langsung:** `KeuanganPwa/SetorKeliling.vue` (5), `PortalGuru/Gerbang.vue` (3), `AppSantri/UjianKerjakan.vue` (2), `Pimpinan.vue` (2), `PortalGuru/Presensi.vue` (2).
- **L5 Intl:** terkonsentrasi di `PortalWali/*` (9 file) + `PosKantin/Kasir.vue`, `KeuanganPwa/*`.

> Cara baca: angka ini **bukan bug**, tapi utang struktur. Tiap gelombang rollout
> menyentuh sekelompok file → jalankan checker lagi, pastikan angka turun, tidak naik.

## Progress
- **2026-06-22 · G0/QW1 SELESAI** — `routes/tenant.php` 626→31 baris, dipecah jadi 7 file `routes/tenant/*.php` (semua <150). `route:list` identik (877 rute, 0 perubahan). **Debt 49→48** (sisa L2 = `web.php` 199, central domain, exempt per audit).
- **2026-06-22 · G0/QW2 SELESAI** — 15 file inline `Intl.NumberFormat` → `import { formatRupiah } from '@/Shared/format'` (output provably identik; `npm run build` HIJAU 2×). **Intl debt 19→4, total debt 48→33.** Sisa 4 (sengaja): `Financial.vue` (→G1 pilot), `PosKantin/{Kasir,Riwayat}` (pola `'Rp '+` nbsp≠spasi → pass MoneyText), `keuangan-pwa/format.js` (helper duplikat → konsolidasi ke Shared/format).
- **2026-06-23 · G1-backend SELESAI** — `PortalWaliService::getFinancialPayload` → DTO `Modules\Keuangan\Data\*` (kontrak lapisan-2 LIVE). Output JSON identik (terverifikasi: revert-1-file → outcome `PortalWaliFeatureTest` sama persis; 2 gagal = baseline-red pra-ada, bukan regresi). php -l + contract test + npm build HIJAU.
- **CATATAN harness:** test DB feature JALAN headless (pgsql; ~45s/file). 2 baseline-red di `PortalWaliFeatureTest`: nav label 'Tagihan'≠'Keuangan' (test basi) + perf >200ms (0.4s, environmental). QW3-5 + 3-tier .vue = pass VISUAL (butuh preview/review), bukan blind-batch.
