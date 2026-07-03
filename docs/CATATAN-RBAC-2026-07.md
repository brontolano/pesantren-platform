# Catatan RBAC — tindak lanjut audit 2026-07

Ringkasan keputusan dari audit RBAC panel tenant (P0/P1 diperbaiki di PR `fix/rbac-audit-p0p1`).

## Prinsip gerbang resource
- `view_<domain>` = **baca-saja**. Mutasi (create/edit/delete) WAJIB `manage_<domain>`
  via `canCreate()/canEdit()/canDelete()/canDeleteAny()` di resource (pola `BelanjaResource`).
- Role pengawasan (`kepala`, `dewan_pembina`) hanya memegang `view_*` → read-only by design.
- Role pelaksana harian tetap bisa input: `pengelola_uks` (`manage_kesehatan`),
  `konselor` (`manage_konseling`), `tu` (`manage_kegiatan`/`manage_asrama`),
  `pengelola_asrama` (`manage_asrama`), `guru`/`guru_tahfidz`/`asatidz`
  (`manage_akademik`/`manage_tahfidz`), `pengelola_sarpras` (`manage_sarpras`).

## Rollout permission ke tenant lama
Katalog role/permission = `RolesAndPermissionsSeeder` (idempoten: `firstOrCreate`
permission + `syncPermissions` per role). Pasca-deploy jalankan:

```
php artisan tenant:reprovision
```

(sudah bagian checklist deploy #17 — memastikan permission baru/berubah tersinkron
ke semua DB tenant).

## Menu yang SENGAJA tersembunyi (bukan bug)
- `MediaFileResource` / `MediaAuditResource` — modul `file_storage` berstatus
  `development` di registry → hanya tampil di tenant `demo`. BY DESIGN sampai modul dirilis.
- `KebijakanKehalalanResource` + page `LaporanDanaKebajikan` — butuh feature-flag
  `FiturKeuangan::pemisahanKehalalanAktif()` (default OFF). BY DESIGN; aktifkan
  flag per-tenant bila pesantren memakai pemisahan kehalalan dana.

## Persetujuan (Approval)
- Menu "Persetujuan Saya" hanya tampil untuk approver potensial (pemegang
  `kelola_approval`, atau role yang terdaftar sebagai `approver_role` pada aturan
  aktif / permintaan menunggu).
- Aksi Setujui/Tolak bergerbang eksplisit: hanya pemegang role `approver_role`
  permintaan ybs (atau super_admin).

## Panel staf /admin
`User::isTenantStaff()` mengecualikan role eksternal: `peserta`, `wali`, `jemaah`,
dan `santri` (App Santri /app; mode pengurus-santri di /app/pengurus, bukan /admin).

## Payroll
Permission kepegawaian/penggajian yang SAH = `run_payroll`. `manage_payroll` tidak
pernah ada di katalog — semua pemakaian lama sudah diganti (audit P1-2).
