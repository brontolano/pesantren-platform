# Pembiayaan — Peta Modul (AGENTS.md)
> Orientasi cepat. Untuk tugas di modul ini, muat: folder Modules/Pembiayaan/ + .d.ts terkait + resources/js/Components/Ui/README.md. Acuan: docs/KONVENSI.md, docs/PLAYBOOK-IRISAN.md.

**Domain:** Aset tetap + penyusutan/mutasi/disposal, pengajuan pembiayaan syariah (DSCR), serta Sarpras (lapor kerusakan + piket scan QR via PWA Guru, WP-33).

**Model inti:** (Modules/Pembiayaan/app/Models/) `Aset` (METODE penyusutan, invarian wakaf), `JadwalPenyusutan`, `MutasiAset` (Approvable F1), `PengajuanPembiayaan`, `LaporanKerusakan`, `AreaPiket`, `CeklisPiket`. Semua `BelongsToTenant`; aset/jadwal/mutasi/kerusakan juga `Auditable`.

**Service/Event inti:** (app/Services/) `DepresiasiService` (garis lurus/saldo menurun, posting D 5-7000 / K 1-2900), `AsetMovementService` (mutasi lokasi + disposal P/L), `PembiayaanService` + `DscrService` (ambang 1,25 via FiturKeuangan), `SarprasService` (kerusakan/piket). Tidak ada folder Events.

**UI Filament:** (sudah di modul, Modules/Pembiayaan/app/Filament/) `Resources/`: AsetResource, MutasiAsetResource, PembiayaanResource (+`Resources/Pages/`). `Resources/Sarpras/`: AreaPiketResource, LaporanKerusakanResource (+`Resources/Sarpras/*/Pages/`). `Pages/PiketHariIni.php`. Grup nav: "Kelembagaan & Komunikasi".

**Endpoint/portal terkait:** PWA Guru Sarpras (Modules/Pembiayaan/routes/api.php): `pwa.guru.sarpras.lapor-kerusakan|laporan-saya|piket.scan` (auth:sanctum, controller PwaGuruSarprasController). Cetak paket kredit: `tenant.pembiayaan.paket.print` (routes/tenant.php:609, PembiayaanExportController). web.php kosong (admin via Filament).

**Migrasi & seeder:** Modules/Pembiayaan/database/migrations/ — aset, pengajuan_pembiayaan, add_depresiasi_to_aset, jadwal_penyusutan, mutasi_aset, sarpras (laporan_kerusakan, area_piket, ceklis_piket). Tidak ada seeder modul (akun Ledger di-seed LedgerSeeder).

**Jebakan modul ini:**
- UANG: posting WAJIB lewat Ledger `JurnalService::postJurnal` dengan ref_type/ref_id terisi (JadwalPenyusutan/MutasiAsset) — jangan null. Akun 5-7000/1-2900/4-6000/5-8000/kas-bank harus di-seed dulu. Biaya LaporanKerusakan info saja (TIDAK auto-posting; pengeluaran manual via Keuangan).
- Wakaf: `is_wakaf` paksa metode 'none' (UU 41/2004) — tak disusutkan, disposal tanpa P/L, tak boleh jadi agunan.
- File tenant: foto kerusakan di disk public dir `kerusakan` → serve via /_tenant-storage (gated login), BUKAN Storage::url; dir terdaftar sensitif di routes/tenant.php.
- Filament v5: jalankan TenantPanelAuthedSmokeTest tiap ubah Resource/Page (grup+item berikon → panel authed 500).
- Tenancy: semua tulis perlu konteks tenant (service melempar bila tenant null); DB-per-tenant fisik.

**Status rollout 5-lapisan:** gelombang akhir (uang ringan) — G2-13 aset/penyusutan/disposal + WP-33 Sarpras. Modul UANG → wajib smoke authed Filament + uji posting ledger sebelum deploy.
