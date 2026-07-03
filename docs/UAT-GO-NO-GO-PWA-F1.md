# UAT Go/No-Go — PWA All-in-One Shell Root (Fase F1)

> Catatan: dokumen `docs/UAT-GO-NO-GO-PWA.md` yang sudah ada membahas UAT lama
> (PWA Peserta SPMB & PWA Wali, 2026-06-10, scope berbeda). Dokumen INI khusus
> untuk fase F1 rencana "PWA All-in-One Berbasis Peran" (shell root satu-pintu,
> flag `pwa.root_sw_enabled`) — dibuat terpisah agar tidak menimpa riwayat UAT
> lama.

Checklist verifikasi HP fisik owner sebelum rollout produksi shell root PWA
(flag `pwa.root_sw_enabled`). Diturunkan dari 8 poin UAT di rencana final
"PWA All-in-One Berbasis Peran" (§Verifikasi).

Status flag saat ini: **OFF secara global** (default aman, kill-switch F0
aktif). F1 hanya menyalakan flag di **tenant DEMO** untuk keperluan demo ke
owner. Tenant produksi (asy-syifaa, albakids, dst) TIDAK disentuh sampai UAT
di bawah lulus dan owner mengizinkan rollout bertahap (demo → UAT HP owner →
asy-syifaa → jeda ≥1 minggu → albakids).

## Cara uji
1. Buka tenant demo (host demo yang flag-nya sudah ON) di HP Android/iOS
   fisik milik owner — bukan emulator, bukan DevTools desktop.
2. Untuk tiap poin, catat hasil: **LULUS** / **GAGAL** / **N/A (belum
   berlaku di F1)** beserta catatan (model HP, versi browser, screenshot
   bila gagal).
3. Semua poin bertanda **[F1 — WAJIB SEKARANG]** harus LULUS sebelum flag
   dinyalakan di tenant produksi mana pun.
4. Poin bertanda **[Nanti — fase lanjut]** dicatat sebagai referensi, belum
   jadi syarat lulus F1 (diuji ulang saat fase terkait berjalan: F0 sudah
   selesai, F2/F3/F5/F6 menyusul).

## Daftar 8 poin (dari rencana final §Verifikasi)

| # | Poin verifikasi | Cakupan fase | Cara uji ringkas |
|---|---|---|---|
| 1 | **Install banner muncul** — Chrome/Edge Android menampilkan prompt "Tambahkan ke layar Utama" / ikon install di address bar untuk tenant demo, dan hasil install menampilkan 1 ikon (bukan ikon lama /guru /wali /spmb terpisah). | **[F1 — WAJIB SEKARANG]** | Buka beranda tenant demo (flag ON) → tunggu beforeinstallprompt / cek menu browser → install → cek home screen: harus 1 ikon baru dgn branding tenant. |
| 2 | **Offline tidak blank** — matikan data/WiFi setelah app ter-install & sempat dibuka sekali (sudah precache), buka lagi app dari ikon home screen → harus tampil halaman `/offline` (bukan layar putih/blank/error Chrome dino). | **[F1 — WAJIB SEKARANG]** | Mode pesawat aktif → buka app dari ikon → harus lihat kartu "Anda Sedang Offline" dengan nama & warna tenant, tombol "Coba Lagi" berfungsi saat online kembali. |
| 3 | **HP bersama tidak bocor data** — login user A (wali/guru) → logout → login user B di HP yang sama → pastikan TIDAK ada data/halaman milik A yang tersisa (termasuk saat offline). | **[Nanti — fase lanjut]**, verifikasi ringan F1 dilakukan (root shell F1 HANYA meng-cache shell statis manifest/ikon/offline; risiko utama ada di SW /wali /guru yang sudah difix di F0). | Cek Cache Storage via `chrome://inspect` (Android) atau Web Inspector (iOS Safari) pada cache bernama `root-shell-{tenantId}-...` → pastikan HANYA berisi manifest/ikon/offline, tidak ada HTML dashboard/nilai/keuangan. |
| 4 | **PWA lama tetap jalan** — instalasi lama /guru dan /wali (jika sudah ter-install sebelumnya di HP yang sama) TETAP bisa dibuka & berfungsi normal setelah root SW diaktifkan (scope terpisah, tidak saling mengganggu). | **[F1 — WAJIB SEKARANG]** | Di HP yang sudah pernah install PWA /guru atau /wali, aktifkan root SW demo → buka lagi ikon /guru atau /wali lama → pastikan tetap berfungsi normal. |
| 5 | **TWA /spmb tetap fullscreen** — aplikasi Android (TWA) dari Play Store/APK yang membungkus `/spmb` tetap tampil fullscreen (bukan browser chrome/address bar) setelah deploy F1. | **[F1 — WAJIB SEKARANG]** (regresi paling kritis — kontrak TWA `/spmb/manifest.json` BEKU; dual-manifest guard sudah diuji otomatis via `RootPwaTest::test_halaman_spmb_tidak_memuat_manifest_root_dobel`) | Buka TWA /spmb existing di HP → pastikan tampilan fullscreen tidak berubah pasca deploy F1; cek DOM via remote-debug: hanya SATU `<link rel="manifest">` aktif (href `/spmb/manifest.json`). |
| 6 | **Sync sekali** — aksi offline (presensi/setoran/dsb) yang di-antrekan saat offline, tersinkron TEPAT SEKALI saat online kembali (tidak dobel, tidak hilang). | **[Nanti — fase lanjut]** — di luar cakupan F1 (shell root F1 tidak menangani sinkronisasi data; logika idempoten `client_ref` per portal tidak disentuh F1). | Uji manual per portal saat F2/F3: airplane mode → submit form → online kembali → cek data hanya masuk 1x. |
| 7 | **iOS add-to-homescreen wali tetap jalan** — pengguna iOS yang sudah "Add to Home Screen" untuk /wali tetap bisa membuka & memakai app normal pasca F1. | **[Nanti — fase lanjut]**, verifikasi ringan F1 dilakukan (root shell F1 tidak mengubah `/wali/manifest.json` maupun `/wali/sw.js` — diff PR ini tidak menyentuh file tersebut). | Di iPhone yang sudah add-to-homescreen /wali → buka lagi → pastikan berfungsi seperti sebelumnya. |
| 8 | **Terima push Android (F6)** — notifikasi push VAPID diterima di Android saat app tertutup/background. | **[Nanti — fase lanjut]** — Web Push adalah Fase F6, belum diimplementasi; tidak berlaku untuk F1. | Tidak diuji di F1. |

## Ringkasan gate F1

- **Wajib lulus sebelum rollout ke tenant produksi**: poin 1, 2, 4, 5.
- **Dicek ringan (otomatis + spot-check manual) tapi bukan gate penuh F1**: poin 3, 7.
- **Belum berlaku / di luar cakupan F1**: poin 6, 8.
- Jika ada satu saja dari poin wajib GAGAL → flag `pwa.root_sw_enabled` di
  tenant demo dimatikan kembali (kill-switch F0 otomatis membersihkan cache
  & unregister SW di kunjungan berikutnya), perbaikan dilakukan, lalu UAT
  diulang dari poin 1.

## Setelah UAT lulus

Lanjutkan ke rollout bertahap sesuai prinsip rencana final:
demo (sudah) → **owner memutuskan lanjut ke asy-syifaa** → jeda ≥1 minggu
→ albakids. Setiap tahap rollout wajib diawali smoke test
(`scripts/smoke-pwa.sh`) hijau + `TenantPanelAuthedSmokeTest` +
`PlatformPanelAuthedSmokeTest` hijau.
