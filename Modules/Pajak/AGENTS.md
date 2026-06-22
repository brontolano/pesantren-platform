# Pajak — Peta Modul (AGENTS.md)
> Orientasi cepat. Untuk tugas di modul ini, muat: folder Modules/Pajak/ + .d.ts terkait + resources/js/Components/Ui/README.md. Acuan: docs/KONVENSI.md, docs/PLAYBOOK-IRISAN.md.

**Domain:** Mesin pajak otomatis Baitul Maal (PMK 59/2022): PPN/PPh belanja BOS/BOP, PPh21 payroll (TER PP 58/2023 + progresif Desember), SPT Masa/Badan, e-Bupot Coretax, sisa-lebih yayasan (PMK 68/2020), laporan ISAK 35 & PSAK 109. Tarif configurable per-tenant, bukan konstanta.

**Model inti:** (Modules/Pajak/app/Models/) `TarifPajak` (tabel tarif_pajak, sumber tarif PPN/PPh), `TerPph21` (tabel ter_pph21, lookup kategori A/B/C + `cariTarif`), `SisaLebihYayasan` (+`AlokasiReinvestasi` hasMany, batas 4 thn). Semua pakai BelongsToTenant; Auditable kecuali TerPph21.

**Service/Event inti:** (app/Services/) `MesinPajakService` (PPN/PPh22/23/sewa, gate kehalalan), `PajakPenghasilanService` (PPh21 TER+progresif), `SisaLebihService`, `SptMasaService`, `SptBadanService`, `EBupotService` (CSV Coretax), `LaporanIsak35Service`, `LaporanPsak109Service`; Support `KebijakanPajakKehalalan` (titik keputusan tunggal: syubhat/non_halal → pajak negara = 0). Tak ada Event.

**UI Filament:** (sudah pindah ke modul) Resource `Modules\Pajak\Filament\Resources\TarifPajakResource` (+`Resources\Pages\ListTarifPajak`) di Modules/Pajak/app/Filament/Resources/; Page `Modules\Pajak\Filament\Pages\KalkulatorPajak`, `KalkulatorPph21` di Modules/Pajak/app/Filament/Pages/. Page `BukuPembantuPajak` ada di modul Ledger (`Modules\Ledger\Filament\Pages\BukuPembantuPajak`), bukan Pajak. Grup nav: "Akuntansi & Pajak" (PPh21 di "Kepegawaian").

**Endpoint/portal terkait:** routes/tenant.php (BUKAN Modules/Pajak/routes/* yang masih kosong/reserved) → `tenant.kepala-keuangan.pajak.index`; API `tenant.kepala-keuangan.api.pajak.{spt-masa, spt-masa-tahunan, ebupot, ebupot.csv, ebupot-tahunan, spt-badan, sisa-lebih}` via KepalaKeuanganController/ApiController.

**Migrasi & seeder:** (Modules/Pajak/database/) migrasi tarif_pajak, ter_pph21, sisa_lebih_yayasan+alokasi_reinvestasi, add_is_komersial_to_sumber_dana (kolom di tabel Ledger); seeder `PajakSeeder` (tarif PMK 59/2022, idempoten) + `TerPph21Seeder` (PP 58/2023, updateOrCreate).

**Jebakan modul ini:**
- UANG: operasi uang WAJIB lewat JurnalService/Ledger; posting ref TAK BOLEH null (idempotency_key NULL tak dedup). Service Pajak di sini KALKULATOR MURNI (tak memutasi slip/ledger) — penjurnalan pajak terutang ada di sisi Ledger/Payroll.
- Policy-first: tarif & kehalalan via tabel tarif_pajak/`KebijakanPajakKehalalan`, JANGAN hardcode (PTKP/BRACKETS PPh21 memang konstanta UU). Gate kepatuhan Q3 (`PERLU_REVIEW_PENASIHAT_PAJAK`): aturan WAJIB ditinjau penasihat pajak sebelum rilis.
- Tenancy: model pakai BelongsToTenant; DB-per-tenant fisik.
- Filament v5: grup berikon + item berikon → panel authed 500; WAJIB TenantPanelAuthedSmokeTest tiap ubah Filament.

**Status rollout 5-lapisan:** G8 — UANG. Modul uang → smoke authed wajib; tiap perubahan tarif/kalkulasi sertakan uji unit (tiap aturan punya test) + adversarial uang sebelum deploy.
