# Tahfidz — Peta Modul (AGENTS.md)
> Orientasi cepat. Untuk tugas di modul ini, muat: folder Modules/Tahfidz/ + .d.ts terkait + resources/js/Components/Ui/README.md. Acuan: docs/KONVENSI.md, docs/PLAYBOOK-IRISAN.md.

**Domain:** Hafalan, Halaqah (+ keanggotaan riwayat soft-close), Penilaian, Setoran (PWA Guru), Sima'an, Munaqasyah (gerbang wisuda), Target/KPI. Non-uang.
**Model inti:** (Modules/Tahfidz/app/Models/) HafalanTahfidz (tahfidz_hafalan), PenilaianTahfidz (tahfidz_penilaian), Halaqah (halaqah) + HalaqahSantri (halaqah_santri, soft-close via left_at — JANGAN hapus), SimaAn (sima_an), Munaqasyah (munaqasyah), TahfidzTarget (tahfidz_target). Semua BelongsToTenant; relasi ke Kesantrian\Santri & App\Models\User.
**Service/Event inti:** TahfidzService (setor() idempoten transaksional via client_ref), TahfidzKpiService (target vs realisasi/capaian per periode, ensureTenantContext). EventServiceProvider kosong ($listen = []); tak ada event domain.
**UI Filament:** di Modules/Tahfidz/app/Filament/Resources/ → HafalanTahfidzResource, PenilaianTahfidzResource, Halaqah/HalaqahResource, Munaqasyah/MunaqasyahResource, TahfidzTarget/TahfidzTargetResource (+ Pages CRUD masing-masing). Belum ada Page khusus. Nav group 'Akademik & Tahfidz', gate ModuleGate moduleVisible('tahfidz') + UiMode::advanced(). Pemindahan vertical-slice dari app/Filament/ SUDAH selesai (tak ada sisa Tahfidz di app/Filament/).
**Endpoint/portal terkait:** PWA Guru (routes/tenant.php, name tenant.portal-guru.): setoran.index/form/store, simaan.form/store, munaqasyah.form/store (controller PortalGuruSetoranController & PortalGuruTahfidzLanjutanController). Portal Wali: tenant.portal-wali.hafalan. Cetak wisuda: tenant.tahfidz.export.wisuda.print (TahfidzExportController). Resource bawaan modul (routes/web.php, api.php) cuma stub TahfidzController.
**Migrasi & seeder:** Modules/Tahfidz/database/migrations/ (hafalan, penilaian, halaqah_tables, add_client_ref, simaan_munaqasyah). CHECK constraint hanya pgsql (halaqah.jenis, munaqasyah.hasil ∈ lulus|mengulang, nilai sima'an 0-100). TahfidzDatabaseSeeder kosong. Factory: HalaqahFactory.
**Jebakan modul ini:**
- Idempotensi: tulisan PWA Guru WAJIB client_ref (uuid); unique (tenant_id, client_ref) NULL-safe pgsql (panel admin tanpa client_ref boleh banyak NULL). setor() menangani race QueryException → ambil baris pemenang.
- Filament v5: grup/item berikon → panel authed 500; WAJIB jalankan TenantPanelAuthedSmokeTest tiap ubah resource.
- Tenancy: semua model BelongsToTenant, DB-per-tenant; TahfidzKpiService butuh tenancy aktif.
- Halaqah keanggotaan: keluar = soft-close (isi left_at), JANGAN delete; satu keanggotaan aktif/santri/halaqah (unique index pgsql WHERE left_at IS NULL).
- Periode KPI: realisasi difilter whereYear(tanggal_selesai) dari prefix tahun periode — jangan kumulatif lintas tahun.
**Status rollout 5-lapisan:** Gelombang G3 (WP-11 Sima'an/Munaqasyah, G3-18 KPI). NON-UANG: tak ada JurnalService/Ledger. Butuh smoke authed karena ada UI Filament.
