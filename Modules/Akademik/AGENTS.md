# Akademik — Peta Modul (AGENTS.md)
> Orientasi cepat. Untuk tugas di modul ini, muat: folder Modules/Akademik/ + .d.ts terkait + resources/js/Components/Ui/README.md. Acuan: docs/KONVENSI.md, docs/PLAYBOOK-IRISAN.md.

**Domain:** Lembaga/jenjang, Periode & Kalender akademik, Kurikulum & Pelajaran, Jadwal, Presensi, Jurnal mengajar, Nilai & Rapor (umum + diniyah), Ujian akademik, Kenaikan kelas & kelulusan. Non-uang.

**Model inti:** (Modules/Akademik/app/Models/) Lembaga, PeriodeAkademik, Kurikulum + KurikulumMapel, Pelajaran, JadwalPelajaran, KomponenNilai, Nilai, Rapor, RaporDiniyah, SesiUjianAkademik, PresensiSantri, JurnalMengajar, KeputusanKenaikan, ProgramStudi/ProgramOutcome, Kitab/KajianKitab/CapaianKitab, Rubrik(Kriteria/Skor) — 23 model.

**Service/Event inti:** (Modules/Akademik/app/Services/) NilaiService + NilaiGridService, RaporService + RaporDiniyahService, KurikulumService, UjianAkademikService, KenaikanKelasService + KelulusanService, TranskripService, PresensiService + PresensiSantriService, JurnalKbmService, KalenderService, PeriodeAkademikService, WaliKelasService, RubrikService, CapaianKitabService, JadwalPelajaranService. Tak ada Event terdaftar (EventServiceProvider $listen kosong, discovery on).

**UI Filament:** SEKARANG di app/Filament/Resources/Akademik/ (15 resource: Lembaga, PeriodeAkademik, Kurikulum, Pelajaran, JadwalPelajaran, Nilai, Rapor, RaporDiniyah, SesiUjianAkademik, PaketSoalAkademik, PresensiSantri, Program/ProgramStudi, Rubrik/RubrikKriteria, Kitab, KalenderAkademik) + 3 Page di app/Filament/Pages/ (GridNilai, SidangKenaikan, RekapJurnalKbm). NavGroup 'Akademik & Tahfidz'. → target Modules/Akademik/app/Filament/. CATATAN: Resources/Kelas → grup 'Kesantrian' (BUKAN modul ini).

**Endpoint/portal terkait:** routes/tenant.php — Portal Guru (prefix `guru`, name `tenant.portal-guru.*`, gate `akses_pwa_guru`): presensi/nilai(grid)/jurnal/wali-kelas/ujian(koreksi,pengawas,tarik-nilai). App Santri ujian (prefix `app`, `tenant.app-santri.ujian.*`). Cetak: `tenant.akademik.export.*` (rapor.print, rapor-diniyah.print, syahadah.print). Portal Wali: academic, rapor.download, rapor-diniyah.download.

**Migrasi & seeder:** Modules/Akademik/database/migrations/ (akademik_tables, kpi_tables, program_tables, jurnal_mengajar, kajian_kitab, komponen_nilai, lembaga, kalender_akademik, kurikulum_tables, keputusan_kenaikan, rapor_diniyah, ujian_akademik_context). Seeder: AkademikDatabaseSeeder, LembagaSeeder, KomponenNilaiSeeder, KalenderLiburNasionalSeeder.

**Jebakan modul ini:**
- File tenant: cetak/unduh rapor & syahadah serve via route /_tenant-storage / controller print, BUKAN Storage::url (404 di Docker).
- Filament v5: grup+item berikon → panel authed 500; WAJIB TenantPanelAuthedSmokeTest tiap ubah Resources/Pages Akademik.
- Policy-first: aturan (KKM, bobot komponen, gate kenaikan, blok rapor diniyah) via KebijakanService/Setting tenant, JANGAN hardcode.
- Tenancy: model BelongsToTenant, DB-per-tenant fisik; upsert nilai/jurnal idempoten (autosave grid).

**Status rollout 5-lapisan:** Gelombang G4 (non-uang, ukuran L, ~57 file Filament). Non-uang → tak butuh Ledger/JurnalService. WAJIB smoke authed (TenantPanelAuthedSmokeTest) tiap ubah Filament; PWA Guru nilai/jurnal/ujian sudah LIVE.
