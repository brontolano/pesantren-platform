# Design System "Nur" — Mockup Terkunci (Light + Dark)

> Status: **LOCKED** 2026-07-06. Ini acuan visual kanonis untuk seluruh aplikasi pesantren.online.
> Mockup statis: `staf-app-preview.html`, `publik-preview.html`, `login-preview.html` (buka di browser).

## 1. Token Desain

### Warna — Light
| Token | Nilai | Pakai |
|---|---|---|
| `--bg` | `#fffaf3` | latar halaman (cream hangat) |
| `--card` | `#ffffff` | permukaan kartu |
| `--nur` | `#0b6e4f` | PRIMARY (CTA, aktif, tautan) |
| `--nur-strong` | `#085840` | hover/tekan |
| `--nur-tint` | `#ecfdf6` | chip ikon hijau |
| `--gold` `#c9a24a` / `--gold-soft` `#e7d3a1` | | ornamen, badge emas |
| `--terra` | `#c14f2a` | badge diskon/live/aksi |
| `--ink` `#111` / `--sub` `#64748b` / `--faint` `#94a3b8` | | teks kuat/muted/dekoratif |
| `--line` | `#efe7da` | border hairline hangat |

### Warna — Dark (`html.dark`)
`--bg:#0d1411; --card:#161e1a; --nur:#2fbf85; --nur-strong:#26a874; --nur-tint:#12271f; --nur-100:#1c3a2e; --ink:#eef2f0; --sub:#9fb0a8; --faint:#6b7a73; --line:#26312c; --terra-tint:#33180f; --goldb:#2a230f; --goldink:#e7c877`
+ override chip `.c-*` DAN `.tile[data-c]`/`.menu-item[data-c]` (dua jalur) ke tint gelap + `.nav` bg gelap.
Toggle: `document.documentElement.classList.toggle('dark')`; auto: `@media(prefers-color-scheme:dark)`.

### Tipografi
- Font: **Plus Jakarta Sans** (display 800 + body 400-700). Amiri (serif) untuk bismillah Arab.
- Skala: judul layar 20-22px, judul seksi 15px, body 14-15px, sekunder 12-12.5px. **JANGAN <11px**.

### Ikon
Set **Lucide** (outline, stroke 2px, inner-markup multi-path). Helper: `fill=none stroke=currentColor`.

### Bentuk & bayangan
Radius kartu 16px, tile 16px, pill 9999px. Shadow lembut berlapis `--sh` (light) / border halus (dark). Ornamen geometris Islami (Rub el Hizb) di hero/kartu unggulan, opacity ~.10.

## 2. Aturan WAJIB (a11y & dark-mode)
1. `button{color:inherit}` — TANPA ini label di dalam `<button>` jadi HITAM di dark mode (jebakan `.tile-label`).
2. Teks pakai `--sub` bukan `--faint` (kontras 4.5:1). `--faint` hanya ikon/placeholder.
3. Target sentuh ≥44px; aksi primer 48-56px.
4. `@media(prefers-reduced-motion:reduce){*{animation:none!important;transition:none!important}}`.
5. `aria-label` di tombol ikon-saja (bell, toggle tema, eye, back).

## 3. Komponen Kanonis
- **Hero**: gradient hijau + ornamen + hairline emas; avatar/logo, greeting/nama, aksi (bell/tema).
- **Strip 5 waktu sholat** (next disorot emas) + datestrip Masehi/Hijri.
- **Bottom-nav 4 tab** (pill aktif hijau + badge).
- **Grid ikon grup → daftar menu** (2 tingkat) + search flat.
- **Kartu**: quick-action, stat (delta ▲/▼), crow (list), featured (gradient+ornamen), product-card.
- **Login**: hero + kartu form (field ber-ikon, toggle sandi) + PPDB + bottom-nav.

## 4. Analisis Peran → Aplikasi (arsitektur per tenant)

| Peran | Aplikasi | Rute | Teknologi | Shell |
|---|---|---|---|---|
| jemaah/umum | Info Publik | `/beranda` | Blade | app-shell.blade |
| (semua) | Login | `/masuk` | Blade | login.blade |
| peserta | PPDB | `/spmb` | Vue | SpmbLayout |
| wali | Portal Wali | `/wali` | Vue | PortalWaliLayout |
| santri | App Santri | `/santri-app` | Vue | SantriApp |
| guru, asatidz, guru_tahfidz | Portal Guru | `/guru` | Vue | PortalGuruLayout |
| kepala_keuangan, bendahara_pusat | Aplikasi Keuangan | `/keuangan` | Vue | KepalaKeuanganLayout |
| kasir, operator_divisi, dewan_pembina | Keuangan Lapangan | `/admin/keuangan/pwa` | Vue | KeuanganPwa |
| akuntan | Akuntan | `/akuntan` | Vue | Pages/Akuntan |
| petugas_payroll | Kepegawaian | `/kepegawaian` | Vue | Pages/Kepegawaian |
| konselor | Konselor | `/konselor` | Vue | Pages/Konselor |
| pengelola_asrama | Pengasuhan | `/pengasuhan` | Vue | Pages/Pengasuhan |
| pengelola_uks | Kesehatan UKS | `/uks` | Vue | Pages/(UKS) |
| keamanan | Pos Keamanan | `/keamanan` | Vue | Pages/Keamanan |
| pengelola_sarpras | Sarpras | `/sarpras` | Vue | Pages/Sarpras |
| panitia_ppdb | PPDB Panitia | `/ppdb-panitia` | Vue | Pages/PpdbPanitia |
| tu | Tata Usaha | `/tu` | Vue/Blade | Pages/(TU) |
| kepala, dewan_pembina | Dasbor Pimpinan | `/pimpinan` | Vue | Pages/Pimpinan |
| staf (generik, tanpa PWA khusus) | Beranda Staf | `/staf` | Blade | staf-home.blade ✅ mockup Nur |
| admin_pesantren, super_admin | Panel Admin | `/admin` | Filament | panel (token Nur via app.css) |

Total: **~25 role, ~18 aplikasi, 6 layout Vue + 174 halaman Vue + 3 blade + 1 panel Filament.**
Router peran: `App\Support\AppLauncher::primaryDestination()`.

## 5. Roadmap Upgrade PWA Lama → Nur

**Kunci strategi:** semua Vue app membaca CSS token bersama (`--brand-*`, `--app-*`, `--wali-*`) dari `resources/css/app.css`. Upgrade tingkat-TOKEN + shell Layout → 174 halaman ikut berubah dengan usaha minimal per-halaman.

- **F1 — Fondasi token (1x, dampak luas):** samakan `app.css` ke spec Nur (Plus Jakarta Sans, ramp `--pst-nur-*`, alias, + blok `html.dark{}` + `button{color:inherit}` + reduce-motion). Semua Vue+blade+Filament ikut.
- **F2 — Shell per-role (6 Layout Vue):** PortalWali, PortalGuru, KepalaKeuangan, Spmb, PortalWaliAuth, JemaahAuth → hero/nav/kartu gaya Nur + toggle tema.
- **F3 — Blade publik:** port `app-shell.blade` (Beranda/Ibadah/Kabar) + `login.blade` dari mockup (hati-hati white-label + AlbaKids).
- **F4 — Halaman Vue high-traffic:** dashboard tiap role (bukan semua 174 sekaligus) — komponen kartu/stat/list Nur di `Components/Ui`.
- **F5 — Dark mode toggle tersimpan** per pengguna (localStorage + setelan) lintas semua app.
- **F6 — QC:** render-test authed per role (jebakan redis lokal → uji via Docker/deploy).

Urutan dampak: F1 (token) memberi ~70% perubahan visual instan; sisanya penghalusan shell & dashboard.
