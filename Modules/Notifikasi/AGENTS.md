# Notifikasi — Peta Modul (AGENTS.md)
> Orientasi cepat. Untuk tugas di modul ini, muat: folder Modules/Notifikasi/ + .d.ts terkait + resources/js/Components/Ui/README.md. Acuan: docs/KONVENSI.md, docs/PLAYBOOK-IRISAN.md.

**Domain:** Notifikasi keluar pesantren (WhatsApp via WAHA + email). Template per-event, broadcast WA massal berantai, dan log audit per pengiriman. Satu titik masuk event→template→kanal untuk modul lain (F1/Keuangan/PPDB/Kegiatan/Disiplin/Mutu).

**Model inti:** (Modules/Notifikasi/app/Models/) `TemplateNotifikasi` (kode_template+judul+isi+variable_dinamis+channel+is_active; unik tenant_id+kode_template), `BroadcastWa` (target_group, filter_criteria, status, attempt_count, last_error_message), `LogNotifikasi` (jenis/channel/target/status/error_message/payload; timestamps=false, created_at manual).

**Service/Event inti:** `NotifDispatcher` (titik masuk `kirim($event,$payload)`, registry EVENTS event→kode_template, fire-and-forget TAK pernah lempar exception, ensureTenantContext). `BroadcastWaService` (create/queue/execute/retryFailed) + `BroadcastWaJob` (queue 'broadcast' terpisah, tries=1, throttle sleep antar-pesan). `WaNotificationService`→`WaGatewayService` (kirim ke webhook n8n/WAHA), `EmailNotificationService`+`GenericNotificationMail`, `RecipientResolver`, `TemplateRenderer` ({{var}}), `LogNotifikasiService`. Settings: `Support\NotifikasiSettings` (toggle WA/email, tanda tangan, interval, webhook per-tenant via tabel settings). Tak ada Event class.

**UI Filament:** `Modules/Notifikasi/app/Filament/Resources/TemplateNotifikasi/` (TemplateNotifikasiResource+Pages) & `Modules/Notifikasi/app/Filament/Resources/BroadcastWa/` (BroadcastWaResource+Pages) — keduanya SUDAH di modul. `app/Filament/Pages/PengaturanNotifikasi.php` (sumber kebenaran NotifikasiSettings) → TETAP app-level (by design): halaman SETELAN/Pengaturan* tetap di host. Nav grup "Kelembagaan & Komunikasi" (AlbaKids: "Notifikasi Custom"); ModerasiPesanWali ada di grup Kesantrian (`Modules/Kesantrian/app/Filament/Pages/`, bukan modul ini).

**Endpoint/portal terkait:** routes/web.php → `tenant.notifikasi.health`; routes/api.php → `notifikasi.health` (keduanya stub /health). Konsumsi wali: routes/tenant.php `tenant.portal-wali.notifications` (PortalWaliNotificationController). Admin via Filament.

**Migrasi & seeder:** Modules/Notifikasi/database/migrations/ (template_notifikasi, broadcast_wa→FK template, log_notifikasi→FK broadcast/template nullable; index tenant_id+status). Seeder `database/seeders/TemplateNotifikasiSeeder` (WAJIB konteks tenant; ~25 template WA default updateOrCreate per tenant_id+kode_template).

**Jebakan modul ini:**
- Notifikasi WA: lewat 1 workflow n8n generik (default config = pipa ppdb-wa) → WAHA; node "Send WAHA" telan error → eksekusi SELALU hijau walau gagal. Cek field 'sent'/'gateway_status' di payload log, BUKAN status broadcast/eksekusi.
- Filament v5: grup berikon + item berikon → panel authed 500; WAJIB TenantPanelAuthedSmokeTest tiap ubah Resource/Page (3 entri panel: 2 resource + PengaturanNotifikasi).
- Tenancy: semua model BelongsToTenant, DB-per-tenant fisik; BroadcastWaJob WAJIB tenancy()->initialize($tenantId) sebelum kerja; NotifikasiSettings baca tabel settings per-tenant (Setting::getTenantValue).
- Throttle/queue: broadcast pakai queue 'broadcast' terpisah dari 'notifikasi' (OTP) + sleep(interval) agar tak men-starve OTP & hindari blokir WA; tries=1 (retry = duplikat).

**Status rollout 5-lapisan:** Modul terpisah (integrasi) — NON-UANG, hanya kirim pesan (jangan posting Ledger di sini). Dipanggil lintas modul via NotifDispatcher::kirim. Butuh smoke authed (Filament Resource/Page). Toggle WA PPDB & per-tenant webhook live.
