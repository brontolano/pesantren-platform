# Plan Pengembangan — Module File Storage (Penyimpanan Media per Tenant)

> Status: **Fase 1 DIBANGUN** · Dibuat 2026-06-05 · Progres di papan Manajemen Proyek → "File Storage".

## 1. Konsep
Tiap tenant punya "disk" terisolasi (seperti OS) untuk upload media, dengan kuota bertingkat yang bisa di-upgrade (freemium SaaS).
- **Tersedia (disk lokal):** 8 / 16 / 32 GB
- **Soon (butuh object storage):** 64 / 128 / 256 GB

## 2. Manual vs Otomatis — keputusan
**Hybrid (seperti OS):**
- **Otomatis:** tiap tenant baru dapat **8 GB default** saat dibuat (via `TenantProvisioner`). Tanpa langkah manual.
- **Manual/Billing:** super_admin upgrade tier, atau ikut paket langganan.
- **Demo:** di-set **2 GB** (sandbox).

## 3. Realita kapasitas (kritis)
VPS = **96 GB total, ~78 GB free**. Di disk lokal: ~8 tenant @8GB, ~2 @32GB. **64-256 GB mustahil di VPS** → wajib **object storage** dulu → itulah gerbang "Soon".

## 4. Arsitektur (Fase 1, terbangun)
| Komponen | Lokasi |
|---|---|
| `tenant_storage` (tenant_id, quota_gb) | CENTRAL |
| `media_files` (path, size_bytes, mime, …) | DB tenant |
| `StorageQuotaService` (quota/used/remaining/canUpload) | app/Services |
| `MediaFileResource` + `StorageQuotaWidget` | panel tenant |
| Modul `file_storage` (status development → hanya demo dulu) | module_registry |
| Default 8 GB auto | TenantProvisioner |

Fondasi `FilesystemTenancyBootstrapper` (sudah aktif) → file otomatis tersimpan di storage terisolasi per tenant.

## 5. Fase
| Fase | Isi | Status |
|---|---|---|
| **1** | Module + upload + media_files + kuota default + enforcement | ✅ selesai |
| **2** | Menu kelola kuota per tenant (super_admin) | ✅ selesai |
| **3** | Object storage (S3) → unlock tier 64/128/256 GB | 🟡 **siap** (config + gating); butuh akun S3 |
| **4** | Thumbnail, folder, trash/restore, filter, bulk | ✅ selesai |
| **4b** | Kompresi gambar + audit log | ⬜ opsional |

## 6. Mengaktifkan tier besar (64/128/256 GB) — Fase 3
Sudah disiapkan via `config/media.php`. Saat punya object storage (Hostinger Object Storage / Wasabi / Backblaze B2 / DO Spaces):
1. Tambah disk `s3` di `config/filesystems.php` + kredensial di `.env` (AWS_*).
2. Set env: `MEDIA_DISK=s3` dan `MEDIA_OBJECT_STORAGE=true`.
3. Tier 64/128/256 GB otomatis aktif di menu **Kuota Penyimpanan** (Radio tidak lagi disabled).
4. (Opsional) migrasikan file lama dari disk lokal ke S3.

## 7. Catatan
- Modul `file_storage` = **development** → hanya tampil di **demo** (2 GB) untuk uji coba. Set **Released** di Modul Platform saat siap untuk tenant nyata.
- Enforcement: upload diblokir bila `pemakaian + file > kuota`. Maks 100 MB/file (`MEDIA_MAX_FILE_MB`).
- Soft-delete: file masuk **trash** (masih makan kuota); **kosongkan** (force-delete) untuk benar-benar hapus file & reklaim ruang.
