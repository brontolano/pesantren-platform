# Inventaris Kustomisasi Tenant Hardcoded + Desain Registry (Refs #343)

> Langkah pertama epik white-label T3 (#343). Dokumen ini: (1) inventaris seluruh
> titik kustomisasi per-tenant yang hardcoded di kode, (2) desain mekanisme
> `TenantCustomizationRegistry`, (3) catatan slice refactor pertama (landing).
> Tanggal: 2026-07-02. Baseline: origin/main `fe85767`.

## 1. Ringkasan

Kustomisasi tenant saat ini hidup sebagai cabang `if` hardcoded — hampir semuanya
lewat `App\Support\AlbaKidsTenant::isActive()` (cek host `albakids.pesantren.online`
atau tenant key `albakids`), sisanya cek slug/host langsung atau default konten
asy-syifaa. Total **±40 titik kustomisasi** di 8 kategori:

| Kategori | Titik | Status |
|---|---|---|
| Landing / halaman publik | 4 | 1 titik SUDAH di-registry (slice ini) |
| Panel Filament (resources/pages/widgets/nav) | 3 blok besar | belum |
| Label & gating resource Filament (PPDB, Notifikasi) | ~25 pemanggilan di 4 resource | belum |
| Tema panel (PanelTheme) | 1 | belum (pola registry SUDAH ada, tinggal entri) |
| Guard Pixio body-class di blade | 6 blade | belum — JANGAN disentuh dulu (keputusan sprint Pixio) |
| Perilaku login/launcher | 3 | belum |
| Default konten asy-syifaa (AppShell/Branding) | 2 file | sudah overridable via Setting; default saja yang hardcoded |
| Data/tipe hardcoded (`pendaftaran_albakids`, template `albakids_%`) | 4 | belum; butuh migrasi data hati-hati |

`app/Console/Commands/*` (importer per-tenant) dan komentar/dokumentasi TIDAK
dihitung — bukan hot path runtime.

## 2. Tabel inventaris

Jenis: **landing** / **tema** / **panel** / **nav** / **perilaku** / **data**.
Risiko: L (kosmetik), M (fitur tenant salah tampil), H (perilaku/uang/keamanan).

| # | File:baris | Jenis | Apa yang di-hardcode | Risiko | Usulan pengganti |
|---|---|---|---|---|---|
| 1 | `Modules/CMS/.../TenantLandingPageController.php:23` | landing | Pilih komponen `CMS/AlbaKidsLandingV2` vs `CMS/LandingPage` | M | ✅ **SELESAI slice ini** → `TenantCustomizationRegistry::landingComponent()` |
| 2 | `app/Http/Controllers/LauncherController.php:38,58` | perilaku | AlbaKids: tamu & jemaah dipaksa ke form login (bukan landing) | M | kunci registry `guest_home` = `landing`\|`login` |
| 3 | `app/Http/Controllers/Auth/StaffLoginController.php:50` | landing | prop `isAlbaKids` ke view login (branding form) | L | kunci registry `login_variant` / branding Setting |
| 4 | `Modules/CMS/app/Services/TenantWebsiteService.php:17-27,45,130-131,602-646` | landing | Default branding, warna, font, seksi default, payload admisi khusus AlbaKids + `isAlbaKidsTenant()` sendiri (duplikat logika host/key!) | M | seed Setting per-tenant + kunci registry `website_defaults`; hapus duplikat resolver |
| 5 | `app/Providers/Filament/TenantPanelProvider.php:189-195,341,638` | panel | Set resources/pages/widgets panel TERPISAH utk AlbaKids (`albaKidsResources()`) | **H** | kunci registry `panel_profile` = `default`\|`albakids` → provider baca profil dari registry; jangka panjang: modul aktif per-tenant (Pusat Modul) |
| 6 | `app/Support/PanelTheme.php:244` | tema | AlbaKids dipaksa default tema `bawaan` | L | entri registry `panel_theme_default`; PanelTheme::THEMES = acuan pola |
| 7 | `Modules/PPDB/.../CalonSantriResource.php` (±15 titik: 106,125,217,236-284,312,346,364,406-469,487-502) | panel+nav | Label, opsi program, status default, aksi WA khusus, grup nav 'PPDB Custom' | M | kunci registry `ppdb_variant` + label map; jangka panjang: konfigurasi PPDB per-tenant di DB |
| 8 | `Modules/PPDB/.../TagihanPpdbResource.php:60-70,141-142,157-172` | panel+data | Label + filter `tipe = 'pendaftaran_albakids'` | M | kunci registry `ppdb_variant`; tipe → kolom konfigurasi |
| 9 | `Modules/PPDB/.../LandingRegistrationController.php:145,184` | data+perilaku | Tulis `tipe='pendaftaran_albakids'`; cek key `albakids` utk notif | M | registry `ppdb_variant` + service notifikasi via binding |
| 10 | `Modules/PPDB/app/Services/AlbaKidsRegistrationNotificationService.php` | perilaku | Service notif WA khusus, kode template `albakids_*` | M | interface `RegistrationNotifier` + binding per registry |
| 11 | `Modules/Notifikasi/.../BroadcastWaResource.php:67,146-179` | panel+nav+data | Filter template `albakids_%`, label & grup nav custom | M | registry `notif_template_prefix` + label map |
| 12 | `Modules/Notifikasi/.../TemplateNotifikasiResource.php:141-165` | panel+nav+data | idem #11 | M | idem #11 |
| 13 | `resources/views/tenant/login.blade.php:51-73` | tema | Guard Pixio: `body.tenant-albakids` pulihkan gaya tombol asli | L | **JANGAN sentuh dulu** — catat saja; nanti kelas body dari registry (`body_class`) |
| 14 | `resources/views/tenant/ppdb-form.blade.php:16-61` | tema | Guard Pixio body-class + variabel warna forest asli | L | idem #13 |
| 15 | `resources/views/tenant/donasi/index.blade.php:9,23` | tema | Guard Pixio body-class | L | idem #13 |
| 16 | `resources/views/tenant/hapus-akun.blade.php:10,26` | tema | Guard Pixio body-class | L | idem #13 |
| 17 | `resources/views/tenant/kebijakan-privasi.blade.php:9,19` | tema | Guard Pixio body-class | L | idem #13 |
| 18 | `resources/views/tenant/suspended.blade.php:62-68` | tema | Guard Pixio body-class | L | idem #13 |
| 19 | `resources/views/albakids/landing.blade.php` (seluruh file) | landing | Landing blade legacy penuh konten AlbaKids (rute lama) | L | arsip/hapus setelah dipastikan tak dirujuk rute |
| 20 | `resources/js/Pages/CMS/AlbaKidsLanding.vue`, `AlbaKidsLandingV2.vue` | landing | Konten, SEO, URL `albakids.pesantren.online` hardcoded di komponen | L | wajar utk komponen custom; jangka panjang: props dari CMS |
| 21 | `Modules/CMS/.../AppShellController.php:28-36,101` | landing | Default URL konten asy-syifaa (fallback bila Setting kosong) | L | pindahkan default ke seeder Setting per-tenant |
| 22 | `app/Filament/Pages/PengaturanBranding.php:97-105` | panel | Default URL asy-syifaa yg sama (duplikat #21) | L | satu sumber default (registry/seeder) |
| 23 | `app/Support/TenantBranding.php:18` | perilaku | Koordinat default = Asy-Syifaa Padalarang | L | default netral + seeder per-tenant |
| 24 | `app/Support/AlbaKidsTenant.php` | (resolver) | Host + tenant key hardcoded | — | dipertahankan sementara; jangka panjang semua pemanggil pindah ke registry lalu kelas ini dihapus |
| 25 | `Modules/AI/config/config.php:701` | perilaku | Placeholder default tenant AI (komentar; nilai kosong) | L | sudah config-driven, biarkan |
| 26 | `config/cors.php:19-25` | perilaku | Apex domain asy-syifaa di default env CORS | L | sudah env-driven; biarkan |
| 27 | `resources/views/tenant/launcher.blade.php:80` | landing | URL website asy-syifaa hardcoded | L | Setting `website_url` |
| 28 | `resources/views/landing.blade.php:260,430`, `app-shell.blade.php:203-209` | landing | Testimoni/label asy-syifaa di landing central & app-shell | L | konten CMS/Setting |

Duplikasi resolver perlu perhatian: `TenantWebsiteService::isAlbaKidsTenant()` (#4)
mengulang logika `AlbaKidsTenant::isActive()` + query domain sendiri — kandidat
konsolidasi pertama setelah slice ini.

## 3. Desain mekanisme: `TenantCustomizationRegistry`

Acuan pola: `App\Support\PanelTheme` (registry tema panel — array konstanta +
resolver Setting). Registry ini generalisasi pola itu untuk kustomisasi tenant.

**Komponen (sudah ada di slice ini):**

- `config/tenant-customization.php` — peta deklaratif:
  - `default.*` — nilai fallback semua tenant (mis. `landing_component: CMS/LandingPage`).
  - `tenants.{tenant_key}.*` — override per tenant + `hosts: []` alias host penuh
    (agar resolusi identik dengan `AlbaKidsTenant::isActive()` yang juga cocok by host).
- `App\Support\TenantCustomizationRegistry` — resolver statis:
  - `value(string $key): mixed` — entri tenant saat ini (by tenant key → by host) → fallback default.
  - Helper bertipe per kunci, mis. `landingComponent(): string`.

**Evolusi bertahap (tidak di slice ini):**

1. Kunci baru per kategori: `guest_home`, `login_variant`, `panel_profile`,
   `ppdb_variant`, `notif_template_prefix`, `body_class`, `website_defaults`.
2. Lapisan Setting per-tenant di atas config (urutan: Setting → config tenant →
   config default), sehingga white-label paket-tema bisa dijual self-service —
   ikuti pola `PanelTheme::resolve()` (Setting per tenant, tanpa migrasi DB).
   Perhatian: baca Setting di panel tenant utk data central wajib
   `tenancy()->central()` (pelajaran Pusat Modul).
3. Setelah semua pemanggil pindah → hapus `AlbaKidsTenant` dan
   `TenantWebsiteService::isAlbaKidsTenant()`.
4. Data hardcoded (`pendaftaran_albakids`, prefix template `albakids_%`) dimigrasi
   TERAKHIR — menyentuh data produksi, perlu rencana migrasi terpisah.

**Prinsip:** setiap slice = refactor murni, perilaku identik, dilindungi feature
test (landing albakids tetap V2, tenant lain tetap default) + smoke panel authed
(`TenantPanelAuthedSmokeTest`).

## 4. Slice pertama (dikerjakan di PR ini)

- Baru: `config/tenant-customization.php`, `app/Support/TenantCustomizationRegistry.php`.
- Refactor: `TenantLandingPageController` memilih komponen via
  `TenantCustomizationRegistry::landingComponent()` (bukan `AlbaKidsTenant::isActive()`).
  Default `CMS/LandingPage`; entri `albakids` → `CMS/AlbaKidsLandingV2`. Perilaku identik.
- Test: `tests/Feature/TenantLandingComponentTest.php` (baru, 3 test: default,
  alias host albakids → V2, resolusi by tenant-key) + `TenantPanelAuthedSmokeTest`
  (216 assertion hijau). Catatan: `CmsLandingPageTest` baseline-red di harness
  lokal DB-per-tenant (pola `createTestTenant` tanpa DB tenant fisik) — gagal
  identik pada origin/main tanpa perubahan; bukan regresi slice ini.
- TIDAK disentuh: guard Pixio body-class (#13–#18), LauncherController (#2),
  panel provider (#5), PPDB/Notifikasi (#7–#12).
