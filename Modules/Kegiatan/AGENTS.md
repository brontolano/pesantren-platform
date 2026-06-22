# Kegiatan — Peta Modul (AGENTS.md)
> Orientasi cepat. Untuk tugas di modul ini, muat: folder Modules/Kegiatan/ + .d.ts terkait + resources/js/Components/Ui/README.md. Acuan: docs/KONVENSI.md, docs/PLAYBOOK-IRISAN.md.

**Domain:** Kegiatan & presensi, Izin santri (pulang), Kunjungan/Penjengukan wali (slot jenguk), Pos Gerbang (log keluar-masuk). Template/recurring kegiatan (G3-19). Non-uang.

**Model inti:** (Modules/Kegiatan/app/Models/) Kegiatan, PresensiKegiatan, IzinSantri, KunjunganWali, PengajuanJenguk, LogGerbang, KegiatanTemplate, KegiatanRecurring (+ KegiatanPhase). Semua pakai `BelongsToTenant`; relasi Santri dari Modules\Kesantrian.

**Service/Event inti:** (app/Services/) KegiatanService, KegiatanLanjutService (template/clone/recurring/gantt), IzinSantriService (ajukan/setujui/tolak/tandaiKembali), GerbangService (statusSantri/catat), PenjengukanService (jadwal/ajukan/tandaiHadir/walkIn/putuskan/batal), SuratIzinSantriService (suratPdf). Event: tidak ada listener terdaftar (EventServiceProvider->$listen kosong, auto-discover).

**UI Filament:** sudah pindah ke modul (vertical slice) di Modules/Kegiatan/app/Filament/. Resource: KegiatanResource, IzinSantriResource, KunjunganWaliResource, Recurring/KegiatanRecurringResource, Template/KegiatanTemplateResource (Modules/Kegiatan/app/Filament/Resources/**, masing-masing dengan subfolder Pages/ List·Create·Edit). Page: LogGerbangPanel, PengajuanJengukPanel (Modules/Kegiatan/app/Filament/Pages/). Semua grup nav `Kesantrian`, gate `moduleVisible('kegiatan')`.

**Endpoint/portal terkait:** routes/tenant.php — PortalGuru: `tenant.portal-guru.izin.*`, `gerbang.index/catat/izin-aktif`, `gerbang.jenguk.hadir/walk-in`, `presensi.show/store`; PortalWali: `izin`/`izin.store`, `jenguk`/`jenguk.store`/`jenguk.batal`, santri `kegiatan`; AppSantri pengurus `pengurus.kegiatan`+`pengurus.presensi.simpan`. Modul routes/api.php: `pwa.wali.izin.*`, `pwa.guru.izin.*` (auth:sanctum) + apiResource kegiatans.

**Migrasi & seeder:** Modules/Kegiatan/database/migrations/ → tabel izin_santri, kegiatan, kegiatan_phase/_recurring/_template, kunjungan_wali, log_gerbang, pengajuan_jenguk, presensi_kegiatan (+ client_ref izin, peserta_luar/qism kegiatan, gerbang_penjengukan). Seeder: database/seeders/KegiatanDatabaseSeeder.php.

**Jebakan modul ini:**
- Filament v5: grup berikon + item berikon → panel authed 500; WAJIB jalankan TenantPanelAuthedSmokeTest tiap ubah Resource/Page modul ini.
- Tenancy: semua model `BelongsToTenant`, DB-per-tenant fisik; jangan tulis tabel central dari panel tenant tanpa tenancy()->central().
- Policy-first: aturan kuota jenguk/izin via KebijakanService (registry key), JANGAN hardcode aturan pesantren.
- client_ref idempoten (IzinSantri, PengajuanJenguk): scope ke pemilik, sertakan di updateOrCreate.

**Status rollout 5-lapisan:** Gelombang G3 (WP-11/17/19/21/29/30 + G3-19). NON-UANG (tak ada JurnalService/Ledger), tak butuh adversarial uang; smoke authed Filament WAJIB karena ada Resource+Page bergrup di panel tenant.
