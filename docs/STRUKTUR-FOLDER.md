# Panduan Tata Letak File & Folder — Agar Selalu Rapi

> Tujuan: tiap berkas punya **rumah yang jelas**, root repo tetap bersih, dan tak ada file nyasar. Untuk arsitektur konsep (modul/layer), lihat [`BLUEPRINT-ARSITEKTUR.md`](BLUEPRINT-ARSITEKTUR.md).

## Prinsip utama (1 kalimat)
**Root repo HANYA untuk berkas yang WAJIB di root (framework/tooling). Semua dokumen, skrip, dan artefak masuk ke subfolder yang sesuai.**

---

## Peta folder root (apa & boleh diisi apa)

### Folder framework (JANGAN pindah/ganti nama — Laravel/composer membaca path persis ini)
| Folder | Isi | Catatan |
|---|---|---|
| `app/` | Kode inti aplikasi (Models, Services, Http, Filament platform) | |
| `Modules/` | **19 modul master** (Kesantrian, Keuangan, Akademik, PPDB, AI, …) | tiap modul = paket sendiri (lihat di bawah) |
| `config/` | Konfigurasi | |
| `database/` | Migrasi, seeder, factory | |
| `routes/` | Definisi rute | |
| `resources/` | View Blade, aset Vue/CSS/JS sumber | |
| `public/` | Root web (yang diakses browser) | `public/build` = hasil build, **gitignored** |
| `bootstrap/` `storage/` | Bootstrap & file runtime | `storage/tenant*` = artefak per-tenant, **gitignored** |
| `tests/` | Test otomatis | |
| `vendor/` `node_modules/` | Dependensi (composer/npm) | **gitignored**, jangan diutak-atik manual |

### Folder pendukung (sah, jelas fungsinya)
| Folder | Isi | Aturan |
|---|---|---|
| `docs/` | **Semua dokumen** (.md): panduan, blueprint, SOP, laporan | dokumen baru → SINI, bukan root |
| `docs/work-packages/` | Laporan/checklist build per work-package | arsip pekerjaan selesai |
| `scripts/` | Skrip operasional aktif (`.sh`/`.ps1`): backup, deploy, dev | skrip ops → SINI |
| `scripts/arsip/` | Skrip sekali-pakai yang sudah mati (codemod lama) | jangan hapus, arsipkan |
| `stubs/` | Template generator nwidart modules | jarang disentuh |
| `integrasi/` | Aset integrasi (mis. plugin WordPress) | |
| `.github/` | Workflow CI/CD (test, deploy) | |

### Berkas root yang WAJIB di sini (jangan pindah)
`artisan` · `composer.json/.lock` · `package.json/-lock.json` · `phpunit.xml` · `vite.config.js` · `vite-module-loader.js` · `modules_statuses.json` · `.env*` · `.gitignore` · `.gitattributes` · `.editorconfig` · `.dockerignore` · `.npmrc` · `Dockerfile*` · `docker-compose*.yml` · `Caddyfile` · `README.md`

> **Kenapa root terlihat “penuh” (~42 item)?** Karena itulah bentuk standar proyek Laravel — tool (composer, npm, docker, git, vite, phpunit) membacanya otomatis dari root. **Itu rapi, bukan berantakan.** Tak bisa dikurangi tanpa merusak aplikasi.

---

## Struktur 1 modul (di dalam `Modules/<Nama>/`)
```
Modules/Keuangan/
├── app/
│   ├── Filament/Resources/   # halaman admin modul
│   ├── Models/               # model modul
│   ├── Services/             # logika bisnis (mis. posting Ledger)
│   └── Http/Controllers/
├── config/                   # config modul
├── database/migrations/      # migrasi modul
├── routes/                   # rute modul
└── resources/                # view modul
```
Tambah fitur ke modul → taruh di dalam folder modul itu, **bukan** di `app/` global.

---

## Aturan “agar selalu rapi” (do / don’t)

✅ **LAKUKAN**
- Dokumen baru (.md, laporan, panduan) → `docs/`.
- Skrip baru → `scripts/` (kalau ops) atau `scripts/arsip/` (kalau sekali-pakai).
- Kode fitur modul → `Modules/<Nama>/app/...`.
- Cek `git status` rutin — **harus bersih**; kalau ada file nyasar, rapikan/gitignore.

🚫 **JANGAN**
- ❌ Taruh `.md` / `.txt` / laporan / skrip lepas **di root**. (Itu yang bikin terasa berantakan.)
- ❌ Commit artefak: `storage/tenant*`, `.env`, `vendor/`, `node_modules/`, `public/build`, file cache (`.phpunit.result.cache`). Semua sudah di `.gitignore` — biarkan begitu.
- ❌ Pindah/ganti nama folder framework (`app config database public routes …`) — aplikasi rusak.
- ❌ Simpan file sekali-pakai (codemod, dump debug) di root — arsipkan di `scripts/arsip/` atau hapus.

---

## “Saya mau taruh file X, ke mana?” (tabel keputusan)
| File | Rumahnya |
|---|---|
| Dokumen/panduan/laporan (.md) | `docs/` |
| Laporan build work-package | `docs/work-packages/` |
| Skrip ops (backup/deploy/dev) | `scripts/` |
| Skrip lama/sekali-pakai | `scripts/arsip/` |
| Kode fitur modul tertentu | `Modules/<Nama>/app/...` |
| Kode lintas-modul / platform | `app/...` |
| Migrasi DB | `database/migrations/` atau `Modules/<Nama>/database/migrations/` |
| Aset gambar/CSS/JS sumber | `resources/` |
| Catatan/dokumen pribadi non-repo | **di luar repo** → `_PESANTREN/_dokumen/` |

---

## Workspace `_PESANTREN/` (di LUAR repo)
Folder induk `_PESANTREN/` menampung repo + materi lain. Tata letaknya:
| Folder | Isi |
|---|---|
| `pesantren-platform/` | **Repo Laravel** (proyek ini) |
| `android-pesantren/` | Repo aplikasi Android native |
| `_dokumen/` | Dokumen lepas (blueprint, laporan, keputusan, keamanan) |
| `_alat/` | Tooling (twa-demo, migrasi, qc-probe, dll) |
| `_referensi/` | Bahan acuan |
| `_arsip/` | Artefak lama |
| `_worktrees/` | Git worktree (jangan `mv` manual — pakai `git worktree move`) |

> Dokumen yang **tentang proyek tapi bukan kode** boleh di `_PESANTREN/_dokumen/`. Dokumen yang **menyatu dengan repo** (panduan teknis, struktur) → `docs/` di dalam repo (file ini contohnya).

---

## Pemeriksaan rutin (1 menit)
```bash
git status              # harus bersih (tak ada file nyasar/untracked aneh)
ls *.md *.txt 2>/dev/null   # di root: idealnya HANYA README.md
```
Kalau muncul file lepas di root → pindahkan ke `docs/`/`scripts/` lalu commit. Itu saja kunci “selalu rapi”.
