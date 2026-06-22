# Asrama — Peta Modul (AGENTS.md)
> Orientasi cepat. Untuk tugas di modul ini, muat: folder Modules/Asrama/ + .d.ts terkait + resources/js/Components/Ui/README.md. Acuan: docs/KONVENSI.md, docs/PLAYBOOK-IRISAN.md.

**Domain:** Hunian santri — Blok → Kamar (kapasitas) → Penempatan santri (riwayat, satu aktif) → Biaya asrama (sinkron ke Tagihan Keuangan). Penempatan otomatis sadar gender + kapasitas (G3-22).

**Model inti:** (Modules/Asrama/app/Models/) `BlokAsrama` (tabel `blok_asrama`, kolom `gender` putra|putri|campur|null), `KamarAsrama` (`kamar_asrama`, `kapasitas`, relasi `penempatanAktif` is_current), `PenempatanAsrama` (`penempatan_asrama`, `is_current`, status), `BiayaAsrama` (`biaya_asrama`, `keuangan_tagihan_id` → Keuangan\Tagihan). Semua pakai `BelongsToTenant`.

**Service/Event inti:** (app/Services/) `AsramaService` = gerbang tulis tunggal (`assignSantriToKamar`, `createBiayaForSantri`, `getOccupancySummary`); `PenempatanAsramaService::tempatkan` (guard kapasitas + tutup penempatan lama dalam transaksi); `PenempatanOtomatisService::tempatkanOtomatis` (G3-22: gender-match, anti-overflow, idempoten, grouping seangkatan); `BiayaAsramaService::sinkronkan` → `Keuangan\TagihanService`; `AsramaImportService` (impor xlsx/csv). EventServiceProvider kosong (auto-discover; tak ada listener).

**UI Filament:** 4 resource di Modules/Asrama/app/Filament/Resources/{BlokAsrama,KamarAsrama,PenempatanAsrama,BiayaAsrama}/{*Resource.php + Pages/} (sudah pindah ke modul, vertical slice). (PresensiAsrama ada di Kepengasuhan, BUKAN modul ini.)

**Endpoint/portal terkait:** routes/web.php + api.php modul = `Route::resource('asramas', AsramaController)` placeholder (nama `asrama.*`, controller scaffold). Tak ada route Asrama khusus di routes/tenant.php. CLI: `app/Console/Commands/AsramaImportCommand.php`.

**Migrasi & seeder:** Modules/Asrama/database/migrations/ — `2026_06_04_180000_create_asrama_tables`, `..._add_gender_to_blok_asrama`, `..._add_current_unique_to_penempatan_asrama` (partial unique index: maks SATU is_current per santri). Seeder `AsramaDatabaseSeeder` = stub kosong.

**Jebakan modul ini:**
- UANG: `BiayaAsrama` TAK posting Ledger langsung — selalu lewat `BiayaAsramaService`→`TagihanService` (jangan buat Tagihan manual; jangan tulis `keuangan_tagihan_id` sembarangan).
- Filament v5: 4 resource Asrama; jalankan TenantPanelAuthedSmokeTest tiap ubah Resource/grup nav (grup+item berikon → panel authed 500).
- Tenancy: semua model `BelongsToTenant`, DB-per-tenant; import pakai `withoutGlobalScopes()->where('tenant_id', …)` — pertahankan filter tenant eksplisit.
- Penempatan: jangan tulis `penempatan_asrama` langsung; lewat `AsramaService`/`PenempatanAsramaService` (jaga guard kapasitas + invarian satu is_current).

**Status rollout 5-lapisan:** Gelombang G3 (NON-UANG inti; sentuh uang HANYA via Keuangan Tagihan). Ubah Filament → WAJIB smoke test authed.
