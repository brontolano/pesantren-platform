# Blueprint — PWA Guru / Khidmat (2026-06-11)

Hasil riset 2-arah: audit repo (fitur akademik/tahfidz/payroll yang sudah ada) + pemetaan kebutuhan guru lintas tipe pesantren (modern/formal, tahfidz, salafiyah kitab kuning, kombinasi).

## Visi

Satu PWA untuk semua pengajar dan petugas lapangan — ustadz mapel, guru tahfidz/musyrif, ustadz sorogan kitab, wali kelas, hingga petugas gerbang — di `{domain-tenant}/guru`, dibangun dengan pola yang sudah terbukti di PWA Wali (Inertia + Vue 3, manifest dinamis ber-branding tenant, installable, serve file via `/_tenant-storage`). Menu menyusut/mengembang mengikuti dua hal: **modul yang aktif di tenant** (ModuleRegistry — pesantren salaf tanpa modul Akademik formal tidak melihat menu nilai/rapor) dan **role/permission Spatie si pengguna** (musyrif melihat halaqahnya, wali kelas melihat kelas binaannya). Prinsip desain: aplikasi disusun per **momen kerja** ("saya di kelas sekarang", "santri menyetor hafalan di depan saya"), bukan per modul admin; alur tercepat adalah scan QR ID card santri → aksi (pola IdCardService QR sudah live). TIDAK membangun aplikasi terpisah per tipe pesantren dan TIDAK membangun e-learning penuh (materi/video/kuis — tetap out-of-scope sesuai PRD §4); fokusnya administratif-operasional: pencatatan di tempat kejadian, bukan pengajaran daring. Fondasi yang sudah ada dan WAJIB dipakai ulang: modul Akademik (PeriodeAkademik, Pelajaran ber-`guru_pengampu_id`, JadwalPelajaran, Nilai, Rapor, PresensiSantri), Tahfidz (HafalanTahfidz, PenilaianTahfidz), Payroll (Employee, SlipGaji + honor per poin), ujian online PPDB (PaketSoal/Soal/UjianAttempt + UjianScoringService dengan timer server-side), dan pola API idempoten Sanctum dari PWA Keuangan Lapangan (KeuanganApiController) untuk toleransi sinyal jelek di lingkungan pesantren.

## Gap struktural yang ditemukan audit (harus ditutup sebelum/selama build)

1. **Tidak ada model Halaqah** — pengelompokan santri binaan per musyrif tahfidz; sekarang setoran hanya tercatat per santri lepas.
2. **Tidak ada domain kajian kitab** — pesantren salafiyah (sorogan/bandongan) tidak terwakili sama sekali; skema Nilai 3-komponen (harian/UTS/UAS) tidak cocok untuk "capaian bab/halaman kitab → khatam".
3. **Tidak ada JurnalMengajar/berita acara KBM** — wajib administratif untuk pesantren bernaung Kemenag/EMIS, sekaligus sumber data poin honor payroll.
4. **Bobot nilai terkunci** harian/UTS/UAS — tidak konfigurabel per pelajaran/tenant.
5. **Tidak ada view jadwal per guru** — JadwalPelajaran terindex per kelas, belum pernah di-query per `guru_pengampu_id`.
6. **Input nilai hanya per santri** — tidak ada grid massal per kelas; gap efisiensi terbesar.
7. **Tidak ada jalur komunikasi guru→wali per santri** — modul Notifikasi hanya broadcast sistem.
8. **Ujian online hanya untuk PPDB** — belum bisa dipakai ujian semester santri aktif padahal mesinnya matang.
9. **Presensi guru tidak ada** — payroll punya `honor_poin` tapi tidak ada alat pencatat kehadiran mengajar sebagai sumber poinnya.

## Fase

### Fase 0 — Fondasi data: Halaqah, Jurnal Mengajar, Kajian Kitab, bobot nilai — effort: sedang

(1) Migrasi `halaqah`: id, tenant-aware, nama, musyrif_id FK users, jenis ENUM('tahfidz','kitab','diniyah'), aktif; pivot `halaqah_santri` (halaqah_id, santri_id, joined_at, left_at NULL) — riwayat keanggotaan jangan dihapus, di-soft-close. Tambah `halaqah_id` NULL ke `hafalan_tahfidz` (backfill tidak perlu; data lama tetap sah tanpa halaqah). (2) Migrasi `jurnal_mengajar`: tenant-aware, guru_id FK users, jadwal_pelajaran_id NULL (KBM formal) ATAU halaqah_id NULL (non-formal) — CHECK salah satu terisi, tanggal, jam_mulai/selesai, materi TEXT, metode VARCHAR NULL, jumlah_hadir INT, status ENUM('terlaksana','kosong','diganti'), pengganti_id FK users NULL, catatan; unique(guru_id, jadwal_pelajaran_id, tanggal) cegah dobel. (3) Domain kitab: tabel `kitab` (nama, pengarang, fan/kategori, jumlah_bab/halaman), `kajian_kitab` (kitab_id, pengampu_id, halaqah_id/kelas_id, periode, metode ENUM('sorogan','bandongan','wetonan')), `capaian_kitab` (kajian_id, santri_id NULL untuk bandongan massal, posisi_bab/halaman, tanggal, status ENUM('lanjut','ulang','khatam'), catatan) — sorogan dicatat per santri, bandongan cukup posisi kelompok. (4) Bobot nilai: tabel `komponen_nilai` per tenant/pelajaran (nama, bobot persen, urutan) dengan seeder default harian 40/UTS 30/UAS 30 agar tenant lama TIDAK berubah perilaku; `Nilai` dapat kolom JSON `nilai_komponen` berdampingan dengan 3 kolom lama (jangan hapus kolom — laporan & rapor lama membacanya), nilai_akhir dihitung service tunggal `NilaiService` (JANGAN duplikasi rumus di resource Filament dan PWA). Semua tabel pakai pola tenant-aware + Auditable yang sudah baku di Modules/Core.

**Prasyarat:** Keputusan owner: daftar kitab awal + metode kajian per lembaga; konfirmasi default bobot nilai.

### Fase 1 — PWA Guru inti: jadwal pribadi + presensi kelas + setoran tahfidz — effort: sedang

Rute `/guru/*` (middleware auth + role guru/musyrif, satu grup dengan pola PortalWali di routes/tenant.php), controller `App\Http\Controllers\PortalGuru\*`, Inertia + Vue (tiru struktur resources/js/PortalWali — JANGAN bikin framework baru). Tiga layar inti: (a) **Hari Ini** — jadwal mengajar dari JadwalPelajaran where guru_pengampu_id = me (+ jadwal halaqah), kartu per sesi dengan tombol "Mulai KBM" yang sekaligus membuka jurnal; (b) **Presensi kelas** — daftar santri rombel dengan toggle hadir/izin/sakit/alpa sekali ketuk (default semua hadir, guru hanya menandai pengecualian — di kelas 40 santri ini beda 2 detik vs 2 menit), tulis ke PresensiSantri lewat endpoint idempoten (kunci: kelas+tanggal+santri) agar retry saat sinyal jelek tidak dobel; (c) **Setoran tahfidz** — pilih santri dari halaqahku ATAU scan QR ID card (jsQR, pola sudah ada), form surah/ayat/jenis(ziyadah|muraja'ah)/nilai/catatan → HafalanTahfidz + PenilaianTahfidz. Manifest + service worker tiru PortalWaliPwaController (manifest dinamis ber-branding tenant); cache shell + antrean tulis offline ringan CUKUP untuk presensi & setoran (IndexedDB + retry, pola camera-gps.js keuangan), JANGAN janji offline penuh. Tulis SEMUA lewat service (PresensiService/TahfidzService) yang juga dipakai Filament — satu sumber kebenaran. Render-test authed (pola tests/Feature/TenantPanelAuthedSmokeTest.php) WAJIB sebelum deploy.

**Prasyarat:** Fase 0 (halaqah + jurnal); permission Spatie baru `akses_pwa_guru`; tenant punya data jadwal terisi.

### Fase 2 — Grid nilai massal + jurnal KBM + capaian kitab + rapor wali kelas — effort: sedang

(a) **Grid nilai per kelas**: satu layar = satu kelas × satu pelajaran × satu komponen; baris santri, kolom nilai, simpan per-baris otomatis (autosave debounce, bukan tombol simpan raksasa — guru di HP sering terputus), validasi rentang 0-100 di server, `input_by` terisi otomatis; sediakan juga di panel Filament (halaman custom ala LoketKas) karena sebagian guru tetap pakai laptop — DUA pintu, SATU NilaiService. (b) **Jurnal KBM**: form 30-detik di akhir sesi (materi, metode, jumlah hadir auto dari presensi, status) — selesaikan di layar yang sama dengan presensi agar tidak jadi beban ganda; rekap bulanan per guru untuk TU/pimpinan di panel. (c) **Capaian kitab**: layar sorogan (per santri: posisi → lanjut/ulang, riwayat tampil) dan bandongan (update posisi kelompok sekali untuk semua). (d) **Wali kelas**: rekap kehadiran + nilai + catatan perilaku anak binaan, dan **catatan wali kelas rapor** (`Rapor.catatan_walikelas` sudah ada — beri pintu input dari PWA, jangan cuma dari panel admin).

**Prasyarat:** Fase 1 live; Fase 0 komponen_nilai + capaian_kitab.

### Fase 3 — Komunikasi guru→wali + presensi guru→poin payroll — effort: sedang

(a) **Laporan ke wali per santri**: model `pesan_wali` (guru_id, santri_id, jenis ENUM('apresiasi','perhatian','info'), isi, dikirim_via) → masuk notifikasi Portal Wali (menu Notifikasi sudah ada) + opsional WA lewat modul Notifikasi yang sudah live; SEARAH dulu (guru→wali, wali membalas lewat datang/telepon) — chat dua arah adalah beban moderasi yang belum siap ditanggung pesantren, JANGAN bangun sebelum diminta owner. Template pesan siap pakai (guru gaptek tinggal pilih + sunting). (b) **Loop honor**: JurnalMengajar status 'terlaksana' = sumber poin — service `PoinHonorService` mengagregasi per periode payroll ke `SlipGaji.poin` (sekarang diisi manual); ATURAN UANG yang sudah baku di repo berlaku: posting ber-ref wajib tidak NULL, idempotent, adversarial-verify sebelum live, JANGAN ubah math payroll yang sudah teruji — poin hanya MENGISI input, perhitungan gaji tetap di jalur lama. (c) Guru melihat slip gajinya sendiri di PWA (route print sudah ada, tinggal gate per-user).

**Prasyarat:** Fase 2 (jurnal terisi rutin); keputusan owner: tarif poin & definisi sesi yang dihitung; template pesan disetujui pengasuh.

### Fase 4 — Ujian semester online (generalisasi mesin PPDB) — effort: besar

Angkat PaketSoal/Soal/SesiUjian/UjianAttempt + UjianScoringService dari Modules/PPDB ke modul bersama (atau Modules/Akademik) TANPA mengubah perilaku PPDB — refactor extract, bukan rewrite; timer server-side, randomisasi soal, satu attempt per peserta, auto-skor PG + koreksi essay manual semuanya sudah teruji produksi. Tambahan untuk konteks akademik: relasi paket→pelajaran→kelas→periode, jadwal sesi per rombel, **guru pengampu sebagai pembuat soal** (sekarang hanya admin/panitia — beri permission per-pelajaran), layar koreksi essay di PWA Guru (antrean jawaban → nilai + catatan), hasil mengalir ke komponen nilai UTS/UAS lewat NilaiService (sambungan otomatis ujian→nilai→rapor). Santri mengerjakan lewat App Santri `/app` (kerangka sudah ada) atau browser lab komputer. Mode pengawas di PWA Guru: daftar peserta sesi + status attempt live (berjalan/selesai/menunggu nilai).

**Prasyarat:** Fase 2 (komponen nilai); App Santri minimal bisa login NIS+PIN (sudah live) + halaman ujian; keputusan owner soal kebijakan ujian online vs kertas per jenjang.

## Skema Data (ringkas)

- BARU `halaqah` + pivot `halaqah_santri` (riwayat keanggotaan, soft-close `left_at`); ALTER `hafalan_tahfidz` + `halaqah_id` NULL
- BARU `jurnal_mengajar` (jadwal_pelajaran_id XOR halaqah_id; unique guru+jadwal+tanggal; status terlaksana/kosong/diganti + pengganti_id)
- BARU `kitab`, `kajian_kitab` (metode sorogan/bandongan/wetonan), `capaian_kitab` (santri_id NULL = posisi kelompok bandongan)
- BARU `komponen_nilai` per tenant/pelajaran (seeder default 40/30/30 — tenant lama tak berubah); ALTER `nilai` + JSON `nilai_komponen` (kolom lama JANGAN dihapus)
- BARU `pesan_wali` (guru→wali searah, jenis apresiasi/perhatian/info)
- Fase 4: pindahkan tabel ujian PPDB ke modul bersama + kolom konteks akademik (pelajaran_id, kelas_id, periode_id NULL — NULL berarti tetap mode PPDB)
- Semua tenant-aware + Auditable; file (jika ada) via `/_tenant-storage`, JANGAN `Storage::url`

## Prinsip yang mengikat build loop

- SATU service per domain tulis (NilaiService, PresensiService, TahfidzService, PoinHonorService) dipakai PWA dan Filament — dua pintu satu kebenaran
- Endpoint tulis dari PWA WAJIB idempoten (pola KeuanganApiController) — lingkungan pesantren = sinyal jelek + retry
- Menu PWA digerbang ganda: modul aktif tenant (ModuleRegistry) × permission Spatie — pesantren salaf tak melihat rapor formal, guru mapel tak melihat halaqah
- Apa pun yang menyentuh uang (poin honor) lewat adversarial-verify sebelum live; JANGAN ubah math payroll/ledger teruji
- Render-test authed sebelum tiap deploy yang menyentuh panel (TenantPanelAuthedSmokeTest)
- E-learning penuh (materi/video/kuis interaktif) TETAP out-of-scope — tolak scope creep ke arah LMS

## Keputusan Owner (sebelum build)

- DAFTAR KITAB & METODE: kitab apa saja yang dikaji per lembaga, sorogan atau bandongan? (menentukan seeder Fase 0)
- BOBOT NILAI: terima default 40/30/30, atau tiap lembaga beda? Siapa boleh mengubah bobot — admin saja atau kepala kurikulum?
- HONOR POIN: berapa tarif per sesi terlaksana? Sesi kosong/diganti dihitung bagaimana? (Fase 3 — menyentuh uang, wajib tegas di awal)
- KOMUNIKASI WALI: setuju model searah guru→wali dengan template? Perlu persetujuan pengasuh sebelum pesan "perhatian" terkirim?
- UJIAN ONLINE: jenjang mana yang ujian online vs kertas? (Fase 4)
- ROLLOUT: mulai dari guru tahfidz (alur paling harian) atau guru mapel formal? Rekomendasi: tahfidz dulu — frekuensi tertinggi, form paling sederhana, dampak ke Portal Wali langsung terlihat (wali memantau hafalan).
