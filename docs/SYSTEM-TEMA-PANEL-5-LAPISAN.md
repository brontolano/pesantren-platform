# Sistem Tema Panel Admin — Arsitektur 5 Lapisan

**Status:** ✅ LIVE Production (2026-06-28, commit d2b9ff7)

Redesign 2026-06-28: dari form radio generic → card-based theme selector profesional dengan live preview, metadata informatif, dan state management Livewire yang robust.

---

## 📋 Breakdown 5 Lapisan

### 1. **BACKEND** — Model, DB, Logic
**File:** `app/Support/PanelTheme.php`

Perubahan:
- ✅ Extended THEMES registry dengan metadata per tema:
  - `category`: sidebar | topnav | utility
  - `icon`, `thumbnail`, `recommended_roles`, `features`, `efficiency`
- ✅ Extended COLORS registry (sama)
- ✅ 8 helper methods baru:
  - `themeWithMetadata()`, `allThemesWithMetadata()`
  - `themesByCategory()`, `recommendedThemeForRole()`
  - `categories()`, `categoryLabel()`
  - `colorWithMetadata()`, `applicableColors()`

DB Storage:
- Tabel `settings` (existing)
  - Key: `panel_theme` → value: `{text: 'pixio'}`
  - Key: `panel_color_preset` → value: `{text: 'bawaan'}`

Security:
- Whitelist tema/warna di PanelTheme::THEMES/COLORS
- Validasi combo tema-berpalet-penuh lock warna

---

### 2. **API** — Endpoint Get/Set Theme
**Files:**
- `app/Http/Controllers/Api/ThemeApiController.php` (NEW)
- `routes/api.php` (updated)

Endpoints:
```
GET /api/tenant/themes
  Response: {current_theme, current_color, themes[], categories[], applicable_colors}

GET /api/tenant/themes/{themeKey}
  Response: {theme, applicable_colors, css_vars}

PUT /api/tenant/theme-settings
  Payload: {theme: 'pixio', color: 'bawaan'}
  Response: {success, current_theme, current_color, css_vars}
```

Security:
- Middleware: `auth:sanctum` + `manage_tenant_settings` gate
- Validasi: tema/warna whitelist, combo lockdown

---

### 3. **UI TEMPLATE** — Halaman Tampilan & Tema
**Files:**
- `app/Filament/Pages/PengaturanTampilan.php` (redesigned)
- `resources/views/filament/pages/pengaturan-tampilan.blade.php` (redesigned)

Layout:
```
┌─ Header Tips (💡 panduan memilih tema)
├─ Section per Category (sidebar / topnav / utility)
│  └─ Grid 3-kolom tema card
│     └─ Card: icon, thumbnail, desc, fitur, roles, checkmark
├─ Section Palet Warna (conditional: locked jika tema punya palet)
│  └─ Grid swatch warna + radio
├─ Live Preview iFrame (500h)
└─ Action: Batal (conditional) + Simpan Tampilan (conditional disable)
```

State Management:
- `$selectedTheme` / `$selectedColor` — nilai saved (DB)
- `$previewTheme` / `$previewColor` — nilai preview (live, unsaved)
- Computed properties: `themes()`, `themesByCategory()`, `applicableColors()`, `previewCssVars()`

Warna Bawaan:
- Tema card aktif: border emerald + bg emerald-50 + checkmark
- Tombol Simpan aktif: emerald-600 (disabled jika preview == saved)

---

### 4. **FRONTEND LOGIC** — Livewire + Real-time Preview
**Files:**
- `app/Filament/Pages/PengaturanTampilan.php` (methods)
- `resources/views/filament/pages/pengaturan-tampilan.blade.php` (JS)

Methods di PengaturanTampilan:
- `selectTheme(string)` — update $previewTheme (+ auto-lock warna jika tema berpalet)
- `selectColor(string)` — update $previewColor (gated: hanya jika tidak locked)
- `resetToSaved()` — revert preview ke saved state
- `saveThemeSettings()` — persist ke DB, update $selectedTheme/$selectedColor
- `generateCssVars()` — generate CSS var string (private)
- `generatePreviewHtml()` — render HTML iFrame + inline CSS (new)

Real-time Preview:
- iFrame srcdoc dengan HTML preview (sidebar + content)
- CSS var di-embed inline: `:root { --tema-sidebar-header: #fff; ... }`
- Livewire listener: `livewire:updated` → inject CSS var ke iFrame contentDocument
- No reload needed — instant visual feedback

---

### 5. **INTEGRASI** — CSS Theme System + Filament Binding
**Files:**
- `app/Http/Middleware/ApplyPanelTheme.php` (existing, verified)
- `app/Providers/Filament/TenantPanelProvider.php` (existing, verified)
- `resources/css/filament/pesantren/theme.css` (existing, compatible)

Middleware (`ApplyPanelTheme`):
- Read `PanelTheme::primaryHex()` → register Filament color
- Check `PanelTheme::topNavigation()` → mutasi panel jadi top-nav (jika true)

Panel Provider:
- Register middleware di tenant panel
- Set default theme (per AlbaKids vs non-AlbaKids)

CSS Theme Engine:
- 8 tema CSS blocks (`.tema-pixio`, `.tema-baitul-maal`, etc) di theme.css
- CSS var: `--tema-sidebar-header`, `--tema-active-bg`, `--tema-active-fg`, dst
- Fallback bawaan (WhatsApp hijau) jika tema tidak dipilih

Runtime Flow:
1. User navigate ke `/pengaturan/tampilan`
2. PengaturanTampilan mount() load current theme
3. Blade render card grid + iFrame preview
4. User click tema → wire:click="selectTheme" → state update → livewire:updated → JS inject CSS
5. User click Simpan → saveThemeSettings() → DB update → notif
6. Page reload → middleware apply new theme → terapkan

---

## 🎨 8 Tema Tersedia

| Tema | Category | Warna | TopNav | Recommended For |
|------|----------|-------|--------|-----------------|
| **Klasik Sidebar (bawaan)** | sidebar | WhatsApp | No | Familiar, entry-level |
| **Pixio** | sidebar | Emerald | No | Admin, bendahara, akuntan |
| **Baitul Maal** | sidebar | Hijau-emas | No | Keuangan specialists |
| **Pimpinan Biru** | topnav | Navy | Yes | Kepala, dewan |
| **Merah Putih** | sidebar | Merah | No | Admin, tu, kepala |
| **Hijau Malam** | topnav | Hijau-emas | Yes | Kepala (formal) |
| **Navigasi Atas** | topnav | Bawaan | Yes | Layar lebar |
| **Kompak** | sidebar | Bawaan | No | Data-dense, entry |

---

## 🔧 Maintenance & Extension

### Menambah Tema Baru
1. Extend `PanelTheme::THEMES` di `app/Support/PanelTheme.php`
2. Add CSS block `.tema-{key}` di `resources/css/filament/pesantren/theme.css`
3. Extend `PanelTheme::COLORS` jika ada palet baru

### Menambah Preset Warna
1. Extend `PanelTheme::COLORS` di `app/Support/PanelTheme.php`
2. Pastikan tema tanpa palet bawaan (primary = null)
3. Blade otomatis render di warna section

### Testing
- Test per role: admin, bendahara, operator, dst
- Verifikasi preview CSS injection instant
- Test save + reload (tema persisted)
- Test color lock (tema berpalet → color disabled)

---

## 📊 Metriks & Notes

**Files Changed:** 5 core files
- `app/Support/PanelTheme.php` (+100 lines, 8 methods)
- `app/Http/Controllers/Api/ThemeApiController.php` (NEW, 140 lines)
- `app/Filament/Pages/PengaturanTampilan.php` (+130 lines, 6 methods)
- `resources/views/filament/pages/pengaturan-tampilan.blade.php` (rewrite, ~180 lines)
- `routes/api.php` (+3 routes)

**Architecture Decisions:**
- Livewire state (preview ≠ saved) → UX freedom, undo flow
- CSS var injection (not full reload) → snappy feedback
- iFrame preview (not live page) → isolation, no side effects
- Metadata-rich THEMES → guided UI, self-documenting registry
- Granular role recommendation → personalized experience

**Known Limitations:**
- TopNav mode implemented di Filament config tapi CSS belum optimal (untuk future refinement)
- Dark mode tema belum customize per tema (fallback Filament default)
- API response besar (allThemes) — OK untuk skala ini, cache-able nanti

---

## 🚀 Deployment Checklist

- [x] Backend metadata extended
- [x] API endpoints created + secured
- [x] UI redesigned (card grid + preview)
- [x] Frontend logic real-time CSS injection
- [x] Middleware integration verified
- [x] All PHP syntax valid
- [x] Push ke remote
- [x] Deploy live (2026-06-28 17:19:25)

Siap produksi. Next: user feedback + optional refinement (dark mode per tema, topnav CSS polish, etc).
