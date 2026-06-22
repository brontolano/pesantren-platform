# Kesantrian — Peta Modul (AGENTS.md)
> Orientasi cepat. Untuk tugas di modul ini, muat: folder Modules/Kesantrian/ + .d.ts terkait + resources/js/Components/Ui/README.md. Acuan: docs/KONVENSI.md, docs/PLAYBOOK-IRISAN.md.

**Domain:** Santri (inti), Kelas & penempatan, Alumni & status keluar, Disiplin (pelanggaran/sanksi/eskalasi SP), Presensi Asrama, Pesan Wali, Pengurus Santri, Enrollment, NIS & ID Card.

**Model inti:** (Modules/Kesantrian/app/Models/) Santri, Kelas, PenempatanKelas, StatusSantri, Alumni, PelanggaranSantri, JenisPelanggaran, SanksiSantri, EskalasiDisiplin, PresensiAsrama, PesanWali, PengurusSantri, SantriEnrollment, HandwritingSample. Santri pakai BelongsToTenant + HasDocuments + SoftDeletes.

**Service/Event inti:** (app/Services/) SantriService, SantriImportService, SantriAccountService, NisGeneratorService, IdCardService, PenempatanKelasService, EnrollmentService, AlumniService, DisiplinService, DisiplinNotifikasiService (lewat Notifikasi\NotifDispatcher), PresensiAsramaService, PesanWaliService (idempoten client_ref). EventServiceProvider $listen kosong. Console: ProvisionAkunSantriCommand, AuditPenempatanCommand.

**UI Filament:** Resource & Page domain sudah pindah ke Modules/Kesantrian/app/Filament/ (vertical slice), semua navigationGroup 'Kesantrian'. Pengecualian app-level disebut tegas di bawah:
- Resources (di Modules/Kesantrian/app/Filament/Resources/): Kelas/KelasResource.php, Alumni/AlumniResource.php, PenempatanKelas/PenempatanKelasResource.php, PelanggaranSantri/PelanggaranSantriResource.php, JenisPelanggaran/JenisPelanggaranResource.php, PresensiAsrama/PresensiAsramaResource.php (masing-masing dengan subfolder Pages/). SantriResource.php TETAP app-level (by design): app/Filament/Resources/Santri/SantriResource.php — resource Core/host terpusat, tidak digerbang per-tenant.
- Pages (di Modules/Kesantrian/app/Filament/Pages/): RekapDisiplin.php, ModerasiPesanWali.php, StasiunPendaftaran.php. PengaturanAppSantri.php TETAP app-level (by design): app/Filament/Pages/PengaturanAppSantri.php — halaman SETELAN. (TunggakanAlumni.php bukan milik modul ini; berada di Modules/Keuangan/app/Filament/Pages/.)

**Endpoint/portal terkait:** API (Modules/Kesantrian/routes/api.php) prefix `pwa/guru/disiplin` name `pwa.guru.disiplin.*` (daftar/catat/{id}/verifikasi/poin), gate `can:akses_pwa_guru`. Portal di routes/tenant.php: PWA Guru `tenant...disiplin.{daftar,catat,poin}` + wali-kelas + pesan wali; App Santri pengurus `tenant.app-santri...pengurus.pelanggaran.*` & presensi; Portal Wali `...santri/{santri}/*`. web.php kosong (Filament).

**Migrasi & seeder:** Modules/Kesantrian/database/migrations/ (kelas, santri, penempatan_kelas, status_santri, alumni, disiplin_tables, presensi_asrama, enrollment_tables, pesan_wali, pengurus_santri + tambah kolom EMIS/alamat wali/ayah-ibu/is_mukim). Seeder: KesantrianDatabaseSeeder (butuh konteks tenant), JenisPelanggaranSeeder.

**Jebakan modul ini:**
- File tenant: serve foto/ID Card via route /_tenant-storage, BUKAN Storage::url (404 di Docker).
- Filament v5: grup+item berikon → panel authed 500; WAJIB jalankan TenantPanelAuthedSmokeTest tiap ubah Filament.
- Policy-first: ambang poin/SP & aturan disiplin via KebijakanService (registry key), JANGAN hardcode.
- Tenancy: model BelongsToTenant, DB-per-tenant fisik; provisioning akun santri lewat central bila tulis tabel central.
- Notifikasi WA (disiplin/eskalasi → wali) lewat NotifDispatcher → 1 workflow n8n generik → WAHA; cek field 'sent'/'gateway_status', BUKAN status eksekusi (selalu hijau walau gagal). Idempoten: PesanWali pakai client_ref.

**Status rollout 5-lapisan:** Modul rilis berbasis gelombang lapangan (WP-08 alumni/status, WP-24 PWA Guru Disiplin). BUKAN modul uang (tak ada Ledger). Disiplin/Presensi/PWA Guru WAJIB smoke authed (Filament + portal). Santri Resource TETAP terpusat (Core), tidak digerbang per-tenant.
