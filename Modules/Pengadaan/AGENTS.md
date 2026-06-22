# Pengadaan — Peta Modul (AGENTS.md)
> Orientasi cepat. Untuk tugas di modul ini, muat: folder Modules/Pengadaan/ + .d.ts terkait + resources/js/Components/Ui/README.md. Acuan: docs/KONVENSI.md, docs/PLAYBOOK-IRISAN.md.

**Domain:** Procurement chain — Supplier → PO → GRN (3-way qty match) → Belanja (posting Ledger + pajak). Belanja juga jalur langsung dari PWA keuangan lapangan.

**Model inti:** (`Modules\Pengadaan\Models`, path `Modules/Pengadaan/app/Models/`)
- `Belanja` (tabel `belanja`, SoftDeletes, Approvable+Auditable, BelongsToTenant) — uang + pajak
- `PurchaseOrder` (`purchase_order`) + `PurchaseOrderItem` (`purchase_order_item`)
- `Supplier` (`supplier`, master vendor + kehalalan/is_pkp)
- `LaporanJasaDanBarang` (`laporan_jasa_dan_barang`) — bukti foto nota/barang/sebelum-sedang-sesudah + GPS

**Service/Event inti:** `BelanjaService` (buat → hitung pajak via `MesinPajakService` → posting auto-jurnal idempoten via `JurnalService`, guard `AnggaranGuardService`, F1 `pastikanBolehLanjut`), `PurchaseOrderService` (buat/ajukan/setujui/terima→buat Belanja, client_ref `PO:{id}`). Event: `BelanjaDisetujui`.

**UI Filament:** sudah di `Modules/Pengadaan/app/Filament/Resources/` (vertical slice):
- `BelanjaResource.php` (grup nav "Akuntansi & Pajak"; create/edit dilarang—immutable, koreksi via jurnal pembalik; aksi Setujui/Tolak/Cetak BKK) + Pages/{List,Create}Belanja
- `PurchaseOrder/PurchaseOrderResource.php` (grup "Kelembagaan & Komunikasi") + Pages/{List,Create,Edit}
- `Supplier/SupplierResource.php` (grup "Kelembagaan & Komunikasi") + Pages/{List,Create,Edit}

**Endpoint/portal terkait:** `routes/tenant.php` — `tenant.pengadaan.bkk.print` (cetak BKK, `PengadaanExportController`); jalur catat belanja PWA: `tenant.keuangan.pwa.belanja` + API `tenant.api.keuangan.belanja`. Routes modul (`routes/web.php`, `routes/api.php`) kosong (admin via Filament; API PWA reserved).

**Migrasi & seeder:** `Modules/Pengadaan/database/migrations/` — `*_create_belanja_table`, `*_create_laporan_jasa_dan_barang_table`, `*_create_procurement_tables` (supplier+purchase_order+purchase_order_item). Tidak ada seeder di modul.

**Jebakan modul ini:**
- UANG: semua operasi WAJIB lewat `JurnalService`/`BelanjaService` (double-entry beban+PPN+PPh+kas). Posting idempoten via ref `Belanja` / `client_ref` (`PO:{id}`) — JANGAN biarkan ref null.
- File tenant: foto di `LaporanJasaDanBarang` serve via route `/_tenant-storage`, BUKAN `Storage::url` (404 di Docker).
- Filament v5: grup+item berikon → panel authed 500; WAJIB `TenantPanelAuthedSmokeTest` tiap ubah Resource/Page.
- Tenancy: model BelongsToTenant, DB-per-tenant fisik; service melempar bila tenant context belum aktif.

**Status rollout 5-lapisan:** Gelombang G2 (G2-09) — modul UANG, akhir/uang-ringan. WAJIB smoke authed Filament + jaga math pajak/jurnal teruji (jangan ubah).
