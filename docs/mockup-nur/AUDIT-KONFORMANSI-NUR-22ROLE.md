# Audit Konformansi Design "Nur" — 22 Role (2026-07-06)

Metode: scan deterministik `resources/js/Pages/**` — halaman dianggap **Nur-conformant**
bila mengimpor `@/Shared` ATAU `@/Components/Ui` (keduanya sudah dihijaukan ke token Nur:
BottomNav/HeroHeader/SegmentedControl + UiCard). Bukan penilaian visual pixel — itu perlu HP.

## Skor per app (≈184 halaman)
**Sudah Nur 100%** (20 app): Akun, Akuntan, BaitulMaal, Humas, Keamanan, KepalaKeuangan(20),
Kepegawaian, KeuanganPwa, Konselor, Notifikasi, Pengabdian, Pengasuhan, Pimpinan,
PortalGuru(23), PosKantin, PpdbPanitia, SantriApp, Sarpras, Tu, Uks.

**Ada gap** (5 app): PPDB(14 lama), PortalWali(6 lama dari 24), Jemaah(4 lama), CMS(3), AppSantri(2).

Total: **~155/184 (84%) sudah Nur.**

## Daftar gap — diklasifikasi
### A. DIKECUALIKAN by-design (jangan diubah)
- `CMS/AlbaKidsLanding.vue`, `CMS/AlbaKidsLandingV2.vue` — AlbaKids dipagari (tema playful sendiri).

### B. Layar khusus (auth/ujian full-screen — prioritas rendah)
- Auth: `PPDB/Portal/{Login,LupaPassword,Aktivasi}`, `PortalWali/Auth/{Login,LupaPassword,Aktivasi}`,
  `Jemaah/Auth/{Login,Daftar,LupaPassword,Aktivasi}`.
- Ujian: `PPDB/Portal/{Ujian,UjianKerjakan}`, `AppSantri/UjianKerjakan`.

### C. Halaman KONTEN — gap nyata bernilai (prioritaskan)
1. `PortalWali/Jenguk.vue`, `PortalWali/Kegiatan.vue`, `PortalWali/Profile.vue` — app wali harus seragam (18 lainnya sudah Nur).
2. `AppSantri/Pengurus/PresensiKegiatan.vue` — presensi kegiatan oleh pengurus santri.
3. `CMS/ArticleDetail.vue` — halaman baca artikel publik.
4. Portal PPDB (guest daftar): `Dashboard, Biodata, Berkas, DaftarUlang, KartuUjian, Pengumuman,
   Tagihan, Tracking` + `PublicForm.vue` — 9 halaman badan pendaftaran.

## Rekomendasi urutan garap (batch kecil, verifikasi HP tiap batch)
1. Batch-1: PortalWali {Jenguk, Kegiatan, Profile} — 3 file, konsistensi app wali (dampak tinggi, risiko rendah).
2. Batch-2: AppSantri/Pengurus/PresensiKegiatan + CMS/ArticleDetail — 2 file.
3. Batch-3: Portal PPDB konten (9) — kelompok terbesar; putuskan dulu apakah PPDB ikut Nur atau punya identitas guest sendiri.
4. Terakhir: layar auth/ujian (opsional; login platform /masuk sudah Nur).

Navigasi per-role SUDAH terverifikasi terpisah & ter-scope (tidak tertukar) — lihat sesi audit nav.
