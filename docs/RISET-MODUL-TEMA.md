# Riset: Modul Tema Admin Panel

Tanggal: 2026-06-11 · Status: BLUEPRINT (belum dibangun)

Tujuan: pesantren bisa memilih **tema tampilan panel admin** dari Pengaturan, dan
owner platform bisa **menambah tema baru** dengan mudah di kemudian hari.

---

## 1. Kondisi sekarang (hasil eksplorasi kode)

| Komponen | Lokasi | Kondisi |
|---|---|---|
| Warna panel | `TenantPanelProvider.php:178` | Statis: `Color::Green` + `Color::Slate` — sama untuk SEMUA tenant |
| CSS tema | `resources/css/filament/pesantren/theme.css` (353 baris) | Satu file: light ala WhatsApp, dark ala TikTok, hex hardcoded + `!important` |
| Branding tenant | `PengaturanBranding.php` + `Setting` (key `branding_*`) | Sudah per-tenant: logo, warna primer/sekunder, font — tapi **belum dipakai panel admin**, hanya website publik & portal |
| Pola toggle | `app/Support/UiMode.php` + render hook `USER_MENU_BEFORE` | Pola siap-tiru untuk pengaturan tampilan |
| Pola halaman pengaturan | `PengaturanBranding` / `PengaturanAppSantri` | Pola siap-tiru (Setting JSON per-tenant) |

Masalah inti: konfigurasi panel Filament di-cache saat boot, dan `viteTheme()` butuh
build Vite — **tidak bisa** ganti file CSS per tenant saat runtime.

## 2. Temuan riset eksternal (Filament v3–v5)

1. **Warna runtime per tenant = middleware.** `FilamentColor::register([...])` dipanggil
   dari middleware panel → palet `primary/gray/danger/...` berubah per request tanpa
   rebuild. Ini cara resmi yang dipakai komunitas untuk tema per-tenant.
2. **Filament merender warna sebagai CSS custom properties** (`--primary-50..950`) —
   override variabel = seluruh komponen ikut, tanpa kompilasi ulang Tailwind.
3. **Plugin Hasnayeen/Themes** memvalidasi arsitektur yang sama (middleware `SetTheme`
   + kelas tema terdaftar), tapi menarik dependensi & gaya sendiri — lebih aman
   bangun ringan sendiri mengikuti pola `UiMode`/`TenantBranding` yang sudah ada.
4. Ganti **file** theme.css per tenant runtime = tidak praktis (butuh build per tema,
   cache panel). Pendekatan benar: **satu theme.css berisi semua preset**, di-scope
   per kelas body (`.tema-klasik`, `.tema-laut`, …) + variabel CSS.

## 3. Arsitektur yang direkomendasikan

**Registry preset di kode + pilihan tersimpan di Setting per tenant. Tanpa tabel baru.**

```
app/Support/PanelTheme.php           ← registry preset + resolusi tema aktif
app/Http/Middleware/ApplyPanelTheme.php ← FilamentColor::register(palet preset)
app/Filament/Pages/PengaturanTampilan.php ← pilih tema (radio + swatch preview)
resources/css/filament/pesantren/theme.css ← refactor jadi variabel + blok per preset
resources/views/filament/theme-vars.blade.php ← <style> variabel via render hook HEAD
```

### 3.1 PanelTheme (registry — menambah tema baru = tambah 1 entry)

```php
class PanelTheme
{
    public const SETTING_KEY = 'panel_theme';

    /** Tambahkan tema baru cukup di array ini (+ blok CSS opsional). */
    public const PRESETS = [
        'bawaan'  => ['label' => 'Hijau Pesantren (bawaan)', 'primary' => '#16a34a', 'gray' => 'slate',
                      'sidebar' => '#ffffff', 'sidebarHeader' => '#008069', 'accent' => '#e7f7ee'],
        'laut'    => ['label' => 'Biru Laut',   'primary' => '#0284c7', ...],
        'senja'   => ['label' => 'Jingga Senja','primary' => '#ea580c', ...],
        'royal'   => ['label' => 'Ungu Royal',  'primary' => '#7c3aed', ...],
        'klasik'  => ['label' => 'Hijau Klasik Kitab', 'primary' => '#14532d', ...],
    ];

    public static function current(): string  // Setting → fallback 'bawaan'
    public static function palette(): array   // preset aktif
    public static function cssVars(): string  // ":root{--tema-sidebar:...;}" utk render hook
}
```

### 3.2 Aliran runtime (tanpa rebuild saat ganti tema)

1. Middleware `ApplyPanelTheme` (dipasang di `TenantPanelProvider->middleware()`):
   `FilamentColor::register(['primary' => Color::hex($preset['primary']), ...])`
   → seluruh tombol/badge/link Filament ikut palet tema.
2. Render hook `HEAD` menyuntik `<style>` berisi variabel CSS preset
   (`--tema-sidebar-bg`, `--tema-sidebar-header`, `--tema-active`) + kelas
   `tema-{nama}` di body (render hook `BODY_START` atau atribut via style).
3. `theme.css` direfactor: hex WhatsApp/TikTok yang hardcoded → `var(--tema-*, fallback)`.
   Tampilan **default tetap persis seperti sekarang** (fallback = nilai lama) —
   zero perubahan visual untuk tenant yang tidak memilih tema.
4. Tema BARU yang butuh gaya lebih dari sekadar warna → blok `body.tema-x { ... }`
   di theme.css (sekali build saat owner menambah tema — wajar, karena menambah
   tema memang lewat rilis kode).

### 3.3 Halaman pengaturan

`PengaturanTampilan` (grup Pengaturan, `manage_tenant_settings`, tampil di kedua
UI mode) — radio bergambar swatch warna per preset + tombol Simpan; preview
langsung terlihat setelah simpan (refresh halaman). Pola form & save meniru
`PengaturanAppSantri`.

### 3.4 Opsi yang sengaja TIDAK diambil

- **Tabel `themes` di DB / editor warna bebas per tenant** — fleksibel tapi rawan
  kombinasi warna tak terbaca (kontras a11y); preset terkurasi lebih aman untuk
  pengguna gaptek. Color picker bebas bisa jadi "tema kustom" v2.
- **Plugin Hasnayeen/Themes** — dependensi eksternal, gaya tak sesuai desain
  WhatsApp/TikTok yang sudah dipoles.
- **viteTheme per tema** — butuh rebuild + cache panel per tenant, tidak runtime.

## 4. Keputusan desain & guardrail

- Tenant-safe & additif: tanpa migrasi DB (pakai tabel `settings` yang ada),
  default `bawaan` = tampilan sekarang → tenant LIVE (AlbaKids, demo) tidak berubah.
- Per-TENANT dulu (keputusan pengurus), bukan per-user. Per-user bisa menyusul
  (kolom `users.panel_theme` ala `ui_mode`) bila diminta.
- Dark mode tetap toggle bawaan Filament; tiap preset wajib definisikan varian
  dark minimal (fallback ke dark sekarang).
- WAJIB jalankan `TenantPanelAuthedSmokeTest` sebelum deploy (perubahan panel).
- Kontras teks per preset dicek WCAG AA sebelum masuk registry.

## 5. Estimasi pengerjaan (bila disetujui)

| Tahap | Isi | Perkiraan |
|---|---|---|
| T1 | `PanelTheme` + middleware + render hook + refactor theme.css ke variabel | inti |
| T2 | Halaman `PengaturanTampilan` + 4–5 preset awal | inti |
| T3 | Test (PanelTheme unit + render authed smoke) + build assets + deploy | inti |
| v2 (opsional) | Tema kustom (color picker), per-user, tema utk Portal Wali/App Santri | nanti |

Sumber eksternal: diskusi resmi Filament tentang tema per tenant, dokumentasi tema
Filament, plugin Hasnayeen Themes (lihat tautan di laporan riset sesi).
