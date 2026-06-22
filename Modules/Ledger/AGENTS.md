# Ledger — Peta Modul (AGENTS.md)
> Orientasi cepat. Untuk tugas di modul ini, muat: folder Modules/Ledger/ + .d.ts terkait + resources/js/Components/Ui/README.md. Acuan: docs/KONVENSI.md, docs/PLAYBOOK-IRISAN.md.

**Domain:** Buku Besar (Baitul Maal) — COA, jurnal double-entry immutable, BKU/buku besar, periode & tutup buku, anggaran, dimensi Kehalalan (halal/syubhat), kurs valuta. Sumber kebenaran keuangan; UANG.

**Model inti:** (Modules/Ledger/app/Models/) `Jurnal`, `JurnalBaris`, `Akun` (COA), `PeriodeAkuntansi`, `Anggaran`, `KursValuta`, `KebijakanKehalalan`, `DivisiUnit`, `SumberDana`, `AlokasiBiaya` — semua pakai `BelongsToTenant`.

**Service/Event inti:** `JurnalService` (postJurnal/reverseJurnal — atomik, idempoten, immutable), `KehalalanGuard`/`KehalalanService`, `AnggaranGuardService`+`AnggaranVarianceService`, `KursService`, `BukuBesarService`, `LaporanService`, `PeriodeService`/`PeriodClosingService`, `PartyLedgerService`, `DanaKebajikanService`, `CoaTemplateService`, `AlokasiBiayaService`. Listener (loose-coupled ke modul Keuangan): `PostingPembayaranListener` (event `PembayaranDiverifikasi`), `PostingTabunganListener` (event `TabunganTransaksiDicatat`) → keduanya posting via `JurnalService`. Exceptions: JurnalTidakSeimbang, PeriodeTerkunci, PelanggaranKehalalan, AnggaranTerlampaui.

**UI Filament:** Resources (app/Filament/Resources/Ledger/ → target Modules/Ledger/app/Filament/Resources/): `AkunResource`, `JurnalResource`, `AnggaranResource`, `DivisiUnitResource`, `SumberDanaResource`, `PeriodeAkuntansiResource`, `KebijakanKehalalanResource`, `KursValuta/KursValutaResource`. Pages (app/Filament/Pages/): `BukuBesar`, `JurnalManual`, `VarianceAnggaran`, `LaporanKehalalan`, `LaporanKeuanganPokok`, `LaporanDanaBosBop`, `LaporanDanaKebajikan`, `LaporanDanaTerikat`, `LaporanRkam`, `RekapJurnalKbm`. Nav group: "Akuntansi & Pajak".

**Endpoint/portal terkait:** Portal Kepala Keuangan di routes/tenant.php — `tenant.kepala-keuangan.ledger.jurnal`, `...ledger.anggaran` (KepalaKeuanganCtrl), API JSON `tenant.kepala-keuangan.api.jurnal` & `...anggaran.realisasi` (KepalaKeuanganApiController). Tidak ada route web/api di Modules/Ledger/routes/ (Filament auto-route).

**Migrasi & seeder:** Modules/Ledger/database/migrations/ (divisi_unit, sumber_dana, akun, periode_akuntansi, jurnal, jurnal_baris, +kehalalan dimension, anggaran, +party ke jurnal_baris, akuntansi_matang_tables). Seeder `LedgerSeeder` (COA + dimensi); dipanggil TenantProvisioner.

**Jebakan modul ini:**
- UANG: semua operasi uang WAJIB lewat `JurnalService`; posting ref TAK BOLEH null — `idempotency_key` NULL tak dedup (SQL NULL≠NULL). Tanpa kunci ditolak kecuali `allow_duplicate=true` eksplisit. JANGAN ubah math teruji (validateBaris/EPSILON/generateNomor).
- Policy-first: Kehalalan aktif HANYA bila setting `keuangan.pemisahan_kehalalan_aktif`=true; aturan dari `KebijakanKehalalan` (registry), JANGAN hardcode.
- Tenancy: `BelongsToTenant`, DB-per-tenant fisik; `postJurnal` wajib tenant context aktif (ensureTenantContext melempar bila belum).
- Filament v5: grup+item berikon → panel authed 500; WAJIB jalankan TenantPanelAuthedSmokeTest tiap ubah Resource/Page.

**Status rollout 5-lapisan:** Gelombang G7 (ModuleRegistry key `ledger`, label "Buku Besar (Akuntansi)", status `released`, is_core=false, sort=7). UANG → butuh smoke authed + adversarial uang sebelum deploy.
