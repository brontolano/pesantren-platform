# Pengembangan Tema Panel (Theme Development)

Panduan developer untuk sistem **tema panel per-tenant**. Ringkas & akurat ke
kode; semua path relatif ke akar repo.

Sumber kebenaran:

- `app/Support/PanelTheme.php` — registry `THEMES` & `COLORS`, resolver aktif.
- `app/Http/Middleware/ApplyPanelTheme.php` — terapkan warna primer + top-nav saat runtime.
- `resources/views/filament/theme-vars.blade.php` — inject CSS var + bodyClass ke `<head>`.
- `resources/css/filament/pesantren/theme.css` — gaya CSS per tema (light & dark).
- `app/Filament/Pages/PengaturanTampilan.php` + `resources/views/filament/pages/pengaturan-tampilan.blade.php` — UI pemilih tema + pratinjau iFrame.
- `config/tenant-customization.php` — kunci `panel_theme_default` per tenant.
- `tests/Feature/PanelThemeTest.php` — kontrak resolver.

---

## 1. Tiga konsep terpisah

| Konsep | Apa | Di mana |
| --- | --- | --- |
| **TEMA** (layout) | Tata letak / susunan panel: sidebar klasik, navigasi atas (top-nav), kompak. Boleh membawa palet bawaan. | `PanelTheme::THEMES` |
| **WARNA** (preset) | Preset palet lepas (header sidebar, item aktif, warna primer) untuk tema yang **tidak** berpalet bawaan. | `PanelTheme::COLORS` |
| **PAKET** (bundel) | Kombinasi TEMA + WARNA siap-pilih (opsional, belum diimplementasikan di repo ini). Bila ditambahkan, letakkan sebagai registry `ThemePackage::PACKAGES` — lihat §6. | — |

Aturan pengikatan penting:

- Tema **berpalet penuh** (punya key `primary` di entri `THEMES`, mis. `pixio`,
  `baitul-maal`) **MENGIKAT** paletnya. Preset WARNA apa pun tidak akan menimpa —
  identitas desain tetap utuh. Lihat `PanelTheme::cssVars()` dan `applicableColors()`.
- Tema **tanpa palet** (mis. `bawaan`, `navigasi-atas`, `kompak`) menerima preset
  WARNA bebas dari `COLORS`.

---

## 2. Kontrak CSS variable

Setiap preset/tema berpalet mendeklarasikan variabel berikut (dipakai
`theme.css` untuk sidebar & item aktif):

| Variabel | Fungsi |
| --- | --- |
| `--tema-sidebar-header` | Warna latar header/sidebar. |
| `--tema-sidebar-header-border` | Garis batas sidebar. |
| `--tema-active-bg` | Latar item navigasi **aktif** (mode terang). |
| `--tema-active-fg` | Warna teks item aktif (mode terang). |
| `--tema-active-dark` | Latar item aktif untuk **mode gelap** (`html.dark`). |

Uji `test_registry_lengkap_dan_konsisten` mewajibkan setiap preset warna
non-`bawaan` memiliki keempat variabel inti + `-dark`. Tambahkan varian gelap
tiap kali menambah palet baru.

Injeksi ke halaman: `theme-vars.blade.php` menulis `:root { … }` dari
`PanelTheme::cssVars()` dan menambahkan `bodyClass` ke `<body>`. CSS var
**selalu dari server** (registry), tidak pernah dari input user.

---

## 3. Menambah TEMA baru

1. Tambah 1 entri ke `PanelTheme::THEMES` (`app/Support/PanelTheme.php`):

   ```php
   'ungu-senja' => [
       'label' => 'Ungu Senja',
       'desc' => 'Sidebar ungu gelap, aksen lavender.',
       'topNav' => false,            // true = navigasi atas
       'bodyClass' => 'tema-ungu-senja', // null bila tak butuh CSS ekstra
       'primary' => '#7c3aed',       // ADA = tema berpalet penuh (mengikat)
       'vars' => [
           '--tema-sidebar-header' => '#2e1065',
           '--tema-sidebar-header-border' => 'rgba(255,255,255,.1)',
           '--tema-active-bg' => 'rgba(196,181,253,.16)',
           '--tema-active-fg' => '#ffffff',
           '--tema-active-dark' => 'linear-gradient(90deg,#8b5cf6 0%,#6d28d9 100%)',
       ],
       'category' => 'sidebar',      // 'sidebar' | 'topnav' | 'utility'
       'icon' => 'heroicon-o-swatch',
       'thumbnail' => '/images/theme-thumbnails/ungu-senja.webp',
       'recommended_roles' => ['admin_pesantren'],
       'features' => ['Ungu elegan', 'Aksen lavender'],
       'efficiency' => 'normal',     // 'compact' | 'normal' | 'spacious'
   ],
   ```

2. Bila tema butuh gaya di luar variabel (spasi kompak, top-nav, dsb.), tambah
   blok CSS `body.tema-ungu-senja { … }` di
   `resources/css/filament/pesantren/theme.css` **dan** blok padanan di preview
   iFrame (`PengaturanTampilan::generatePreviewHtml()`), agar pratinjau
   mencerminkan layout sebenarnya.

3. Sediakan thumbnail di path `thumbnail` (mis. `public/images/theme-thumbnails/…`).
   Belum ada generator SVG otomatis di repo ini; sediakan gambar manual atau
   kartu tetap tampil tanpa thumbnail.

4. Jalankan `npm run build` bila menyentuh CSS/aset, lalu uji.

`category` menentukan grup di UI (dibaca `themesByCategory`). **Jangan** tambah
ikon grup navigasi baru — sesuai konvensi repo (grup berikon dapat memicu 500).

---

## 4. Menambah PRESET WARNA baru

Untuk tema tanpa palet bawaan. Tambah 1 entri ke `PanelTheme::COLORS`:

```php
'senja' => [
    'label' => 'Palet Senja',
    'primary' => '#7c3aed',
    'vars' => [
        '--tema-sidebar-header' => '#2e1065',
        '--tema-sidebar-header-border' => '#1e0a4a',
        '--tema-active-bg' => '#ede9fe',
        '--tema-active-fg' => '#6d28d9',
        '--tema-active-dark' => 'linear-gradient(90deg,#8b5cf6 0%,#6d28d9 100%)',
    ],
],
```

`primary` wajib `#rrggbb`; keempat var inti + `-dark` wajib (dijaga uji
`test_registry_lengkap_dan_konsisten`).

---

## 5. Default tema per tenant

`PanelTheme::theme()` resolusinya:

1. Setting tenant `panel_theme` (pilihan eksplisit tenant) → menang bila valid.
2. Bila kosong/tak dikenal → default `panel_theme_default` dari
   `config/tenant-customization.php`.
   - Tenant **umum** → `pixio`.
   - Tenant **custom** `albakids` → `bawaan` (perilaku lama dipertahankan).

Preset WARNA (`PanelTheme::color()`) resolver **terpisah** dan selalu fallback
ke `bawaan` bila kosong/tak dikenal. Tema berpalet penuh (mis. pixio) tetap
mengikat paletnya sekalipun warna = `bawaan`.

---

## 6. (Opsional) Menambah PAKET (ThemePackage)

Kelas `ThemePackage` **belum ada** di repo ini. Bila di masa depan dibuat,
polanya: registry `ThemePackage::PACKAGES` berisi bundel `['theme' => …,
'color' => …, 'label' => …, 'desc' => …]`, dipakai UI sebagai pilihan sekali-klik
yang mengeset `previewTheme` + `previewColor` sekaligus. Menambah paket = tambah
1 entri di `PACKAGES` (tanpa mengubah resolver TEMA/WARNA).

---

## 7. Pratinjau (preview) iFrame

`PengaturanTampilan` merender iFrame `srcdoc` dari `generatePreviewHtml()`.
Real-time saat memilih tema/warna, blade menginject via `livewire:updated`:

- **CSS var** → `<style data-theme-css>` (dari `previewCssVars()`).
- **bodyClass** → `body.className` (dari `previewBodyClass()`) → user melihat
  perubahan **layout** (kompak / top-nav), bukan hanya warna.

Kontrol tambahan (Alpine `x-data`):

- **Toggle mode gelap** — `documentElement.classList.toggle('dark')` di iFrame.
- **Mode responsif** — Desktop (`w-full`) / Tablet (`768px`) / Mobile (`375px`)
  mengubah lebar iFrame.

Keamanan: semua CSS var & bodyClass berasal dari registry server; tidak ada
`eval` atau nilai input user yang diinjeksikan.

---

## 8. Menjalankan test tema

```bash
# Semua uji tema (memory 2G, DB test terpisah)
TEST_DB_DATABASE=pesantren_test_temaprev php -d memory_limit=2G vendor/bin/phpunit \
    --filter 'PanelThemeTest'

# Smoke panel (render authed) — pastikan halaman tidak 500
php -d memory_limit=2G vendor/bin/phpunit \
    --filter 'TenantPanelAuthedSmokeTest|PlatformPanelAuthedSmokeTest'
```

Bila menyentuh CSS/aset panel, jalankan `npm run build` sebelum smoke authed.
