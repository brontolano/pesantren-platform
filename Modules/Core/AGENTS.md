# Core — Peta Modul (AGENTS.md)
> Orientasi cepat. Untuk tugas di modul ini, muat: folder Modules/Core/ + .d.ts terkait + resources/js/Components/Ui/README.md. Acuan: docs/KONVENSI.md, docs/PLAYBOOK-IRISAN.md.

**Domain:** Fondasi platform — Approval (F1), Audit (F2), Dokumen (F5), Surat/Administrasi TU. Engine lintas-modul; modul lain memakai lewat trait, tak menyalin logika.

**Model inti:** (Modules/Core/app/Models/) ApprovalRequest, ApprovalRule (registry APPROVABLE + FIELD_OPTIONS), AuditLog (append-only, UPDATED_AT=null), Document (getUrlAttribute → /_tenant-storage), Surat. Trait (app/Concerns/): Approvable, Auditable, HasDocuments.

**Service/Event inti:** (app/Services/) ApprovalService (ajukan idempoten, setujui/tolak, pastikanBolehLanjut), SuratService (penomoran race-safe, retry 23505/23000), DocumentService (versioning per documentable+kategori), ReportExportService. Event: ApprovalDiputuskan(request, disetujui). Exception: PersetujuanBelumLengkapException, ReportPdfUnavailableException.

**UI Filament:** TETAP app-level (by design) di app/Filament/Resources/ — Core/host TIDAK direlokasi ke modul (vertical-slice tak berlaku untuk fondasi; lihat "Status rollout"). Resource: Approval/ApprovalRequestResource ("Persetujuan Saya"), Approval/ApprovalRuleResource ("Aturan Persetujuan"), Audit/AuditLogResource ("Jejak Audit"), Document/DocumentResource ("Repositori Dokumen") — grup "Pengaturan & Mutu"; Surat/SuratResource ("Surat & Administrasi") — grup "Kelembagaan & Komunikasi" (canCreate=false, lewat aksi "Catat Surat").

**Endpoint/portal terkait:** routes/tenant.php — `/_tenant-storage/{path}` (serve file Document); approval pimpinan PWA `tenant.pimpinan.approval.setujui|tolak`; approval kepala-keuangan `...approval.setujui|tolak` (semua HANYA via ApprovalService).

**Migrasi & seeder:** Modules/Core/database/migrations/ — create_approval_tables, create_audit_log_table, create_document_table, create_surat_table. CoreDatabaseSeeder kosong (no-op).

**Jebakan modul ini:**
- UANG (tak langsung): Approval adalah gerbang transaksi uang modul lain — F1 WAJIB lewat ajukanPersetujuan/pastikanBolehLanjut; posting final modul consumer panggil pastikanBolehLanjut() dulu.
- File tenant: Document serve via /_tenant-storage (sudah di getUrlAttribute), BUKAN Storage::url (404 di Docker).
- Filament v5: 5 resource berikon + grup → JALANKAN TenantPanelAuthedSmokeTest tiap ubah Filament (panel authed 500).
- Tenancy: semua model BelongsToTenant (Surat pakai Stancl); ensureTenantContext() di tiap service; DB-per-tenant fisik.
- Surat: penomoran race-safe (lockForUpdate + unique tenant/jenis/tahun/nomor_urut, retry sekali) — JANGAN edit nomor langsung.
- Audit: append-only, side-effect (guard hasTable, tak boleh memecah operasi domain).

**Status rollout 5-lapisan:** Fondasi — TETAP TERPUSAT, JANGAN relokasi resource/model domain. Tak menulis uang langsung tapi GERBANG operasi uang → setiap ubah Approval butuh smoke authed + verifikasi modul consumer (Belanja/PO/SlipGaji/Pembayaran/Jurnal/Cuti/MutasiAset/MutuTemuan).
