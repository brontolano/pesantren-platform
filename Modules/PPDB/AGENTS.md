# PPDB — Peta Modul (AGENTS.md)
> Orientasi cepat. Untuk tugas di modul ini, muat: folder Modules/PPDB/ + .d.ts terkait + resources/js/Components/Ui/README.md. Acuan: docs/KONVENSI.md, docs/PLAYBOOK-IRISAN.md.

**Domain:** Pendaftaran santri baru (SPMB) end-to-end: calon santri & biodata, dokumen, tagihan PPDB, ujian seleksi online (bank soal), keputusan kelulusan + daftar ulang → konversi ke Santri, CRM penawaran/aktivitas, portal peserta PWA `/spmb` + OTP WA.

**Model inti:** (Modules/PPDB/app/Models/) CalonSantri, CalonSantriDokumen, TagihanPpdb, HasilUjian, JadwalUjian, PaketSoal, Soal/SoalUjian, SesiUjian/UjianAttempt, CrmPenawaran/CrmAktivitas, UserOtp. Semua pakai `App\Traits\BelongsToTenant`.

**Service/Event inti:** PpdbBillingService (delegasi ke Keuangan\TagihanService), PpdbReregistrationService→Kesantrian\SantriService + Asrama, PpdbAdmissionDecisionService, UjianOnlineService + UjianScoringService/PpdbExamResultService, PpdbWhatsappNotifier, Peserta(Otp|Account|Branding)Service, CrmService. EventServiceProvider kosong (auto-discover); tak ada folder Events.

**UI Filament:** (lokasi SEKARANG app/Filament/… → target Modules/PPDB/app/Filament/) Resources/PPDB/: CalonSantriResource, CalonSantriDokumenResource, TagihanPpdbResource, HasilUjianResource, JadwalUjianResource, SoalUjianResource, PaketSoal/PaketSoalResource, UjianAttempt/UjianAttemptResource; Resources/Crm/CrmPenawaranResource; Pages/CrmDashboard.php + Pages/PpdbReport.php.

**Endpoint/portal terkait:** Modules/PPDB/routes/web.php — publik `tenant.ppdb.public.create|store|tracking`; portal peserta PWA prefix `spmb.*` (login/aktivasi/dashboard/ujian/daftar-ulang/tagihan/biodata, guard `web` + alias `peserta`); file privat `ppdb.file.dokumen|bukti`; resource `ppdb.*`. routes/tenant.php hanya showcase demo `tenant.ppdb-panitia.*` (PpdbPanitiaDemoController).

**Migrasi & seeder:** Modules/PPDB/database/migrations/ (calon_santri + dokumen + tagihan_ppdb + hasil/sesi/jadwal_ujian + bank_soal + crm_tables + biodata lengkap/ortu/consent). Seeder: PPDBDatabaseSeeder, PesertaRoleSeeder (role `peserta`).

**Jebakan modul ini:**
- UANG (ringan): tagihan PPDB WAJIB lewat PpdbBillingService → Keuangan\TagihanService (Ledger), JANGAN tulis jurnal/saldo langsung; bukti bayar di TagihanPpdb.
- File tenant: serve via route `/_tenant-storage` / `ppdb.file.*`, BUKAN Storage::url (404 di Docker) — lihat PesertaBrandingService.
- Filament v5: grup+item berikon → panel authed 500; jalankan TenantPanelAuthedSmokeTest tiap ubah Resource/Page PPDB.
- Tenancy: model BelongsToTenant (DB-per-tenant fisik); portal `/spmb` resolve tenancy by FULL DOMAIN.
- Notifikasi WA: PpdbWhatsappNotifier → 1 webhook n8n → WAHA; cek flag `sent`/`gateway_status`, BUKAN status HTTP (selalu 200 walau gagal).

**Status rollout 5-lapisan:** G5 (uang ringan via TagihanService). Wajib smoke authed Filament + jaga seam UANG lewat Ledger; portal peserta PWA + ujian online + OTP WA aktif.
