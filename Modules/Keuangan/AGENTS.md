# Keuangan — Peta Modul (AGENTS.md)
> Orientasi cepat. Untuk tugas di modul ini, muat: folder Modules/Keuangan/ + .d.ts terkait + resources/js/Components/Ui/README.md. Acuan: docs/KONVENSI.md, docs/PLAYBOOK-IRISAN.md.

**Domain:** Tagihan & Pembayaran (SPP/syahriyyah, skema biaya, fund-aware), Tabungan santri (topup, transaksi, batas belanja), POS Kantin & Setor Keliling, Inventory (item/batch/lokasi/mutasi), Baitul Maal (donasi/beasiswa), rekonsiliasi bank & laporan keuangan (RKAM/BOS-BOP/Kehalalan/EMIS).

**Model inti:** Tagihan, Pembayaran, Tabungan, TabunganTransaksi, PosTransaksi, InventoryItem/InventoryBatch, Donasi/Beasiswa, SkemaBiaya, SetorKelilingSesi — di Modules/Keuangan/app/Models/ (semua pakai BelongsToTenant).

**Service/Event inti:** Services/ → PembayaranService, TabunganService, BayarDariSaldoService, FundAwareBillingService, TagihanService, KantinService/PosKasService, InventoryService, SetorKelilingService, BankReconService, LaporanKeuanganService. Events/ → PembayaranDiverifikasi, TabunganTransaksiDicatat (loose-coupling; modul Ledger mendengarkan untuk auto-jurnal, tak ada panggilan JurnalService langsung).

**UI Filament:** Sudah pindah ke modul (vertical slice). Resource di Modules/Keuangan/app/Filament/Resources/{Tagihan,Pembayaran,Tabungan,TabunganTopup,Pos,Inventory,SkemaBiaya,MetodePembayaran,SetorKeliling,BaitulMaal,LaporanKeuangan,Rekonsiliasi}/ (namespace Modules\Keuangan\Filament\…); Page di Modules/Keuangan/app/Filament/Pages/{KasirKantin,LoketKas,RekonKantin,TunggakanAlumni}.php. Pengecualian TETAP app-level (by design): PengaturanKeuangan (halaman setelan keuangan) & PusatLaporan (hub laporan lintas-domain) di app/Filament/Pages/. Catatan: halaman laporan dana LaporanRkam/LaporanKehalalan/LaporanDanaBosBop/LaporanDanaTerikat/LaporanDanaKebajikan/LaporanKeuanganPokok kini milik modul Ledger (Modules/Ledger/app/Filament/Pages/), bukan modul ini.

**Endpoint/portal terkait:** routes/tenant.php → Portal Wali tenant.wali.pembayaran.* & tenant.wali.tabungan.* (bayar-saldo, topup, batas-belanja); ekspor tenant.keuangan.{tagihan,pembayaran}.{csv,print}; API kepala-keuangan verifikasi/tolak pembayaran; PWA setor-keliling.*; pos-kantin.{kasir,tabungan}.

**Migrasi & seeder:** Modules/Keuangan/database/migrations/ (tagihan/pembayaran/tabungan, pos_kas, inventory + lokasi/batch, bank_statement, skema_biaya, fund-aware, setor_keliling+client_ref, baitul_maal, kantin); seeder PosKategoriSeeder.

**Jebakan modul ini:**
- UANG: semua operasi uang lewat event auto-jurnal ke Ledger; posting ref TAK BOLEH null (idempotency_key NULL tak dedup). BayarDariSaldoService pernah bypass Ledger (fix PR#225) — jangan ulangi.
- Filament v5: grup+item berikon → panel authed 500; WAJIB jalankan TenantPanelAuthedSmokeTest tiap ubah Filament.
- Policy-first: aturan keuangan via KebijakanService (registry key), jangan hardcode.
- Tenancy: model BelongsToTenant, DB-per-tenant fisik; tulis tabel central (tenant_modules) dari panel tenant WAJIB tenancy()->central(). POS tergerbang ke modul koperasi.
- Notifikasi WA: lewat 1 workflow n8n → WAHA; cek field 'sent'/'gateway_status', BUKAN status eksekusi (selalu hijau walau gagal).

**Status rollout 5-lapisan:** G6 — UANG. Smoke authed + adversarial WAJIB sebelum deploy tiap perubahan modul ini.
