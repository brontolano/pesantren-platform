# Mutu — Peta Modul (AGENTS.md)
> Orientasi cepat. Untuk tugas di modul ini, muat: folder Modules/Mutu/ + .d.ts terkait + resources/js/Components/Ui/README.md. Acuan: docs/KONVENSI.md, docs/PLAYBOOK-IRISAN.md.

**Domain:** SPMI/Penjaminan Mutu (KMA 939, G3-17) — Inspeksi/audit, Sasaran/KPI, SKM (survei kepuasan), Dokumen mutu (approval+versioning), Temuan (Non-Conformance), serta Lampu (cache lampu kuning Dasbor Mutu 2a).

**Model inti:** (Modules/Mutu/app/Models/) `MutuInspeksi`, `MutuSasaran` (capaian()), `MutuTemuan` (state-machine + Approvable F1), `MutuSkm`, `DokumenMutu` + `DokumenMutuVersi` (snapshot), `MutuLampu` (cache 4 jenis: hafalan_macet/alpa/poin_disiplin/nilai_turun).

**Service/Event inti:** `MutuService` (approval dokumen draft→diajukan→disetujui|ditolak + revisi/versi), `MutuKpiService` (realisasi, temuan terbuka→tindakan→verifikasi→tutup, SKM, ringkasan). Command `mutu:hitung-lampu` (HitungLampuMutuCommand) hitung lampu harian + notif. Tak ada Event. CATATAN: Dasbor Mutu 2a pakai `App\Services\Mutu\MutuService` (BERBEDA, di app/, bukan modul ini).

**UI Filament:** di `Modules/Mutu/app/Filament/Resources/` (DokumenMutuResource, MutuInspeksiResource, MutuSasaranResource, MutuSkmResource, MutuTemuanResource + Pages masing-masing) & `Modules/Mutu/app/Filament/Pages/` (DashboardMutu, DosirAsesmen, MutuKelas, MutuLembaga, ProfilMutuSantri). Sudah pindah penuh ke modul (vertical slice); tak ada sisa di `app/Filament/` untuk domain ini.

**Endpoint/portal terkait:** routes/web.php & routes/api.php KOSONG (admin via Filament). Tak ada route di routes/tenant.php.

**Migrasi & seeder:** Modules/Mutu/database/migrations/ (dokumen_mutu, dokumen_mutu_versi, mutu_lampu, mutu_kpi_tables → mutu_sasaran/mutu_inspeksi/mutu_temuan/mutu_skm). TIDAK ada Seeders.

**Jebakan modul ini:**
- File tenant: DokumenMutu.berkas_url → serve via route /_tenant-storage, BUKAN Storage::url (404 di Docker).
- Filament v5: grup/item berikon → panel authed 500; WAJIB TenantPanelAuthedSmokeTest tiap ubah Resource/Page Mutu.
- Tenancy: semua model BelongsToTenant, DB-per-tenant fisik; service WAJIB ensureTenantContext().
- F1 (non-uang): MutuTemuan Approvable — tutup() lewat pastikanBolehLanjut(); severity TAK BOLEH diubah setelah keluar 'terbuka' (cegah bypass downgrade kritis→minor).
- Notifikasi WA: HitungLampuMutuCommand kirim ke wali (no_hp_wali) via NotifDispatcher → workflow n8n → WAHA; cek field 'sent'/'gateway_status', BUKAN status eksekusi (selalu hijau walau gagal).

**Status rollout 5-lapisan:** Gelombang G3 (G3-17) — NON-UANG. Butuh smoke authed (banyak Filament Resource/Page). Tak ada operasi Ledger/JurnalService.
