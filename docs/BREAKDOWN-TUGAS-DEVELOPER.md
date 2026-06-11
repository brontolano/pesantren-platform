# Breakdown Tugas Developer — Per Fungsi Staf Pesantren (2026-06-11)

Dokumen eksekusi yang menyatukan 3 blueprint + peta ekosistem menjadi tugas developer per FUNGSI/QISM. Setiap fungsi mendapat tiga lapis: (1) **ruang kerja panel** (kerja meja di pusat kegiatannya), (2) **mode lapangan di PWA** (input tanpa ke kantor), (3) **tombol kebijakan** (Setting per tenant — sistem mengikuti pesantren, BUKAN sebaliknya).

## PRINSIP NOMOR SATU: Kebijakan-dulu (policy-first)

Diluar sana pesantren terbalik mengikuti sistem. Di pesantren.online aturannya:
1. **Tidak ada angka/aturan yang di-hardcode.** Ambang poin SP, % kehadiran minimum, bobot nilai, tarif khidmah, maks izin pulang/bulan, jam malam — SEMUA lewat `Setting::getTenantValue` (pola sudah baku) dengan default waras dari riset.
2. **Master data di-seed tapi bebas diubah**: jenis pelanggaran, jabatan, jenis izin, komponen nilai, jenis kegiatan — seeder hanya CONTOH, bukan takdir.
3. **Alur bisa pendek atau panjang**: setiap workflow approval punya saklar (mis. pelanggaran perlu verifikasi ustadz: ON/OFF; izin pulang perlu approval wali via PWA: ON/OFF; ApprovalRule nominal sudah ada di repo — pakai itu untuk uang).
4. **Modul bisa mati**: pesantren salaf 100 santri tanpa lembaga formal mematikan Akademik formal dan tetap utuh (ModuleRegistry sudah ada).
5. **Istilah bisa diganti**: label "musyrif/murabbi/pembina", "mustahiq/wali kelas", "ta'zir/pembinaan" per tenant via Setting label-map — pesantren beda istilah bukan beda fitur. (WP baru, kecil — selama ini belum ada.)

Definisi selesai SETIAP WP wajib menyebut: tombol kebijakan apa yang dibuka + nilai default-nya + bukti test bahwa dua kebijakan berbeda menghasilkan perilaku berbeda.

---

## BEDAH PER FUNGSI

Format: **Panel** (ruang kerja meja) · **Lapangan** (mode di PWA) · **Kebijakan** (Setting) · **WP** (rujukan blueprint; WP-24+ baru dari dokumen ini).

### 1. Kedisiplinan / Mahkamah / Qism Keamanan-Santri
- **Panel**: ruang kerja "Kedisiplinan" — antrean pelanggaran menunggu verifikasi, rekap poin per santri/kelas/asrama, surat SP otomatis (SuratTemplate), riwayat sanksi, tren bulanan.
- **Lapangan (PWA Guru mode pengurus + mode pembina)**: catat pelanggaran di tempat kejadian (scan QR santri → jenis → foto bukti opsional → kirim); pengurus santri mencatat status 'dicatat', ustadz memverifikasi dari HP; eksekusi sanksi dicentang pelaksana.
- **Kebijakan**: master jenis+poin bebas; ambang eskalasi & jenis SP; verifikasi 2-lapis ON/OFF; notifikasi wali (tiap kejadian/hanya eskalasi/berat saja); poin positif (reward) ON/OFF; masa berlaku poin (per semester / akumulasi terus).
- **WP**: WP-09 (Akademik A-4) + WP-21 (mode pengurus) + **WP-24: layar verifikasi & eksekusi sanksi di PWA** (kecil).

### 2. Kepengasuhan / Musyrif / Asrama
- **Panel**: ruang kerja "Kepengasuhan" — peta asrama/kamar & penghuninya, rekap absen malam & sholat per kamar, daftar izin pulang aktif + yang terlambat kembali, catatan pembinaan per santri.
- **Lapangan (PWA Guru mode musyrif)**: absen kamar malam per-kamar sekali layar (default hadir, tandai yang tidak), presensi sholat per rombongan, setujui & catat izin pulang dari HP (termasuk saat wali jemput malam), tandai "sudah kembali" saat santri datang, catatan perilaku kilat.
- **Kebijakan**: jam absen malam; sholat mana saja yang diabsen (5 waktu / subuh-maghrib-isya saja); maks izin pulang per bulan + alasan yang dibolehkan + H-berapa pengajuan; izin diajukan siapa (santri/wali/keduanya); jemput wajib orang tua ON/OFF.
- **WP**: PWA-Guru F1-F2 + **WP-25: presensi kamar/sholat per-kelompok satu layar** (sedang — beda dari presensi kelas: satuannya kamar & slot waktu, bukan rombel & mapel; tabel `presensi_kegiatan` reuse dengan jenis slot) + **WP-26: alur izin pulang penuh di PWA** (pengajuan wali dari PWA Wali → approval musyrif → cetak/QR surat izin → gerbang cek → tandai kembali; IzinSantri sudah ada, yang baru = alur antar-aplikasinya).

### 3. Kesehatan / UKS / Bapak-Ibu Asuh
- **Panel**: ruang kerja "Kesehatan" — register kunjungan UKS, stok obat sederhana, santri dalam perawatan/istirahat (otomatis tampil di presensi sebagai 'sakit'), riwayat per santri, rujukan keluar + biaya (→ tagihan/dana kebajikan).
- **Lapangan (PWA Guru mode petugas kesehatan)**: catat kunjungan UKS dari kamar/UKS (scan QR → keluhan → tindakan → obat), tandai santri istirahat (auto-feed status presensi), lapor kasus menular → notifikasi pengasuh.
- **Kebijakan**: notifikasi wali untuk kasus apa (semua/menular/rawat saja); biaya berobat ditanggung siapa (pondok/wali/dana kebajikan) → menentukan posting; daftar obat & stok ON/OFF (pesantren kecil tak butuh).
- **WP**: RiwayatKesehatan sudah ada → **WP-27: register UKS + status istirahat-terusan-ke-presensi + notifikasi wali ber-kebijakan** (sedang) + **WP-28: stok obat sederhana** (kecil, opsional via modul-flag).

### 4. Keamanan / Gerbang / Penerima Tamu
- **Panel**: ruang kerja "Keamanan" — buku tamu digital, jadwal penjengukan, daftar izin keluar aktif hari ini, log gerbang.
- **Lapangan (PWA Guru mode keamanan — HP/tablet di pos)**: scan QR/NFC santri di gerbang → layar verifikasi (foto santri tampil! anti kartu pinjam) + status izin valid/tidak; registrasi tamu & penjengukan (cocokkan dengan data wali — wajah/identitas); panic-note ke pengasuhan.
- **Kebijakan**: hari & jam penjengukan; siapa boleh menjenguk (wali terdaftar saja/keluarga); barang titipan dicatat ON/OFF; santri keluar wajib surat ber-QR ON/OFF.
- **WP**: KunjunganWali sudah ada → **WP-29: mode pos gerbang** (sedang — scan→verifikasi-foto→log; bergantung WP-26 & kartu QR/NFC Stasiun F3) + **WP-30: penjadwalan penjengukan + pengajuan dari PWA Wali** (kecil-sedang; menambah maslahat wali nyata).

### 5. Pendidik (ustadz mapel, mustahiq, muhaffizh)
Sudah terbedah penuh di BLUEPRINT-PWA-GURU (F1: jadwal+presensi+setoran; F2: grid nilai+jurnal+kitab; F3: pesan wali+poin honor; F4: ujian). Panelnya = aplikasi Akademik yang ada + layar sidang kenaikan (WP-07). Kebijakan kunci yang WAJIB dibuka: bobot nilai per lembaga, format & blok raport diniyah, jenis setoran tahfidz, kurikulum bebas per tenant.

### 6. Tata Usaha / Administrasi
- **Panel**: ruang kerjanya sudah paling lengkap (Kesantrian + PPDB + arsip). Tambahan dari blueprint: rekap kesiapan EMIS (WP-12), Stasiun Pendaftaran (blueprint terpisah), arsip SK & kontrak pegawai (WP-16).
- **Lapangan**: praktis tidak butuh — TU memang di kantor. JANGAN memaksakan PWA.
- **Kebijakan**: format NIS (sudah di blueprint Stasiun), template surat bebas (SuratTemplate ada), label istilah per tenant (WP-31).

### 7. Bendahara / Keuangan
Sudah live + blueprint SDM G3. Tambahan dari bedah ini: **WP-32: Setor Keliling masuk PWA Keuangan** (sudah direkomendasikan sejak awal — backend ada, tinggal layar) — petugas keliling kamar menerima setoran tabungan via QR santri + UID+PIN.

### 8. Kebersihan / Sarpras / Qism Nazhafah
- **Panel**: jadwal piket area, daftar kerusakan masuk → tindak lanjut → selesai (siklus sederhana), aset per lokasi (modul Aset ada).
- **Lapangan**: lapor kerusakan via foto dari HP (santri pengurus pun boleh — mode pengurus), ceklis piket area ber-QR lokasi.
- **Kebijakan**: area & jadwal piket bebas; siapa boleh lapor; eskalasi kerusakan ke sarpras pusat.
- **WP**: **WP-33: lapor-kerusakan + ceklis piket** (kecil-sedang; HasDocuments/foto via /_tenant-storage). Prioritas RENDAH — kerjakan setelah fungsi inti.

### 9. IT / Admin Tenant
- **Panel**: sudah ada (Pengguna, RBAC, modul on/off, tema, provisioning). Tambahan yang nyata dibutuhkan admin pesantren GAPTEK-ringan: **WP-34: halaman "Pengaturan Kebijakan Pesantren"** — SATU layar terstruktur yang mengumpulkan SEMUA tombol kebijakan di atas per kelompok (disiplin/izin/kesehatan/akademik/keuangan) dengan bahasa awam + nilai default + tombol "kembalikan ke bawaan"; selama ini Setting tersebar. INI WP yang membuat janji "sistem ikut pesantren" TERLIHAT oleh pembeli.
- **Lapangan**: tidak perlu.

### 10. Pimpinan (kyai/mudir/kepala)
- **Panel**: aplikasi Laporan (ada) + Dasbor Mutu (peta ekosistem 2a) + approval terpusat (ApprovalRule ada).
- **Lapangan**: bukan input — **WP-35: dasbor pimpinan versi PWA ringkas** (baca-saja: kas hari ini, kehadiran, lampu kuning mutu, izin/SP menunggu approval — pimpinan menyetujui dari HP). Kecil, reuse dasbor pimpinan PWA Keuangan yang sudah ada polanya.

---

## REKAP WP BARU DARI BEDAH INI (WP-24 s.d. WP-35)

| WP | Fungsi | Isi | Effort | Bergantung |
|---|---|---|---|---|
| 24 | Kedisiplinan | verifikasi & eksekusi sanksi di PWA | K | WP-09, PWA-Guru F1 |
| 25 | Kepengasuhan | presensi kamar/sholat per-kelompok | S | PWA-Guru F1 |
| 26 | Kepengasuhan | alur izin pulang lintas-aplikasi (Wali→musyrif→gerbang) | S | PWA-Guru F1 |
| 27 | Kesehatan | register UKS + istirahat→presensi + notif ber-kebijakan | S | PWA-Guru F1 |
| 28 | Kesehatan | stok obat sederhana (modul-flag) | K | WP-27 |
| 29 | Keamanan | mode pos gerbang scan+verifikasi foto | S | WP-26, Stasiun F1/F3 |
| 30 | Keamanan | jadwal penjengukan + pengajuan dari PWA Wali | K-S | — |
| 31 | Lintas | label istilah per tenant (musyrif/murabbi dst) | K | — |
| 32 | Keuangan | Setor Keliling masuk PWA Keuangan | K-S | backend ada |
| 33 | Sarpras | lapor kerusakan + ceklis piket | K-S | — (prioritas rendah) |
| 34 | IT/Admin | halaman "Pengaturan Kebijakan Pesantren" terpadu | S | menyusul tiap WP yang buka Setting |
| 35 | Pimpinan | dasbor + approval pimpinan versi PWA | K | Dasbor Mutu 2a |

## URUTAN EKSEKUSI GABUNGAN (semua dokumen)

1. **Sekarang bisa jalan tanpa owner**: PWA-Guru F0 + Akademik/SDM WP-01..05 (jangkar) + WP-31 (label) + WP-32 (setor keliling).
2. **Gelombang lapangan**: PWA-Guru F1 → WP-25, 26, 27, 24 (kepengasuhan-kesehatan-disiplin = denyut harian pondok).
3. **Gelombang akademik & uang** (paralel worktree): G2 (WP-06..12) ∥ G3 (WP-13..18, uang = adversarial 3 lensa).
4. **Gerbang & wali**: WP-29, 30 (butuh kartu dari Stasiun) + PWA-Guru F3.
5. **Pemoles yang menjual**: Dasbor Mutu (2a) → WP-35 → WP-34 (kumpulkan semua kebijakan yang sudah terbuka).
6. Sisanya (WP-28, 33, F4 ujian, Lapisan 2b/2c ekosistem) mengikuti tenant nyata.

Metode tiap WP tetap baku: agent build → adversarial (uang: 3 lensa) → verifikasi independen → test hijau (+ render-test authed bila sentuh panel) → PR → deploy. Tiap WP yang membuka Setting WAJIB mendaftarkannya ke WP-34.

---

## TO-DO DETAIL PER WP — GELOMBANG 1 & 2

Ceklis eksekusi per WP. Gelombang 3-5 dideteilkan saat gelombangnya tiba (agar menyerap pelajaran gelombang sebelumnya). Konvensi: semua tabel tenant-aware + Auditable; semua endpoint tulis PWA idempoten; ☐ = to-do.

### F0-PWA — Fondasi data PWA Guru
☐ Migrasi `halaqah` (nama, musyrif_id, jenis tahfidz|kitab|diniyah, aktif) + pivot `halaqah_santri` (joined_at, left_at — soft-close)
☐ ALTER `hafalan_tahfidz` + halaqah_id NULL (tanpa backfill)
☐ Migrasi `jurnal_mengajar` (guru, jadwal_pelajaran_id XOR halaqah_id, tanggal, jam, materi, jumlah_hadir, status terlaksana|kosong|diganti+pengganti_id, unique guru+jadwal+tanggal)
☐ Migrasi `kitab`, `kajian_kitab` (metode sorogan|bandongan|wetonan), `capaian_kitab` (santri NULL = posisi kelompok)
☐ Migrasi `komponen_nilai` + seeder default 40/30/30 + ALTER `nilai` kolom JSON nilai_komponen (kolom lama JANGAN drop)
☐ `NilaiService::hitungNilaiAkhir()` — satu-satunya rumus; unit test: bobot custom ≠ default menghasilkan nilai beda
☐ Model + relasi + factory + Filament resource minimal (Halaqah, Kitab) di modul Tahfidz/Akademik
☐ Test: createRuntimeTenant pola baku; tenant lama tanpa komponen_nilai tetap berperilaku lama

### WP-01 — Model Lembaga
☐ Migrasi `lembaga` (jenis, jenjang, nomor_statistik, izin_operasional+tanggal, kepala_id, divisi_unit_id FK Ledger, aktif)
☐ Seeder 1 lembaga "Pondok" per tenant + hook di TenantProvisioner (pola LedgerSeeder)
☐ ALTER `kelas` + lembaga_id NULL + backfill ke lembaga default
☐ ALTER `periode_akademik` + lembaga_id NULL + kalender ENUM masehi|hijriyah + tahun_hijriyah; ubah unique → (tenant, lembaga, tahun, semester)
☐ Filament resource Lembaga di grup Sistem/Akademik + kolom lembaga di form Kelas & Periode
☐ Test: dua periode aktif beda lembaga sah; satu lembaga dua periode aktif ditolak; tenant:reprovision idempoten
☐ Render-test authed panel sebelum deploy

### WP-02 — Kalender akademik
☐ Migrasi `kalender_akademik` (lembaga_id NULL=semua, tanggal_mulai/selesai, jenis libur_nasional|libur_pondok|ramadhan|ujian|pasaran|kegiatan, keterangan)
☐ `KalenderService::isHariEfektif($tanggal, $lembaga)` + `hariEfektifDalamRentang()`
☐ Seeder libur nasional tahun berjalan (statis, bisa diedit)
☐ Filament resource + tampilan kalender bulanan sederhana
☐ Test: tanggal dalam rentang Ramadhan → tidak efektif untuk lembaga diniyah, tetap efektif bila lembaga formal tak menandai

### WP-03 — Penugasan multi-peran (jantung SDM)
☐ Migrasi `jabatan` (nama, kategori struktural|pengajar|kepengasuhan|pengurus_santri|staf, role_spatie NULL — mapping) + seeder ±15 jabatan dari riset
☐ Migrasi `penugasan_pegawai` (employee, jabatan, lembaga, mulai, selesai NULL, nomor_sk, file_sk, is_primary)
☐ Observer: penugasan dibuat → assign role Spatie dari mapping; ditutup → revoke BILA tak ada penugasan lain ber-role sama
☐ Tandai `Employee.jabatan` (string) deprecated: read-only di form, tampil "jabatan (lama)"
☐ Halaman penugasan di EmployeeResource (repeater riwayat; satu pegawai banyak baris)
☐ Cetak SK via SuratTemplate (template SK penugasan default)
☐ Test: rangkap 3 jabatan 2 lembaga; tutup 1 dari 2 penugasan ber-role sama → role bertahan; is_primary unik per pegawai
☐ Setting → daftarkan ke WP-34: mapping jabatan→role boleh diubah tenant

### WP-04 — Perluasan data pegawai
☐ ALTER `employees`: nik, nuptk, nrg, status_kepegawaian ENUM baru (gty|gtt_honorer|pns_dpk|pppk|pengabdian|kontrak), is_mukim, tanggal_selesai, pendidikan_terakhir, asal_alumni
☐ Migrasi data: status lama (tetap|honorer|kontrak) → map ke enum baru (tetap→gty, honorer→gtt_honorer)
☐ Form Filament tab "Kepegawaian" + "Identitas" (NIK dkk untuk EMIS)
☐ Test: PayrollService tetap jalan untuk semua status baru (slip pengabdian sah)

### WP-05 — Observer sinkron PenempatanKelas
☐ Observer: PenempatanKelas is_current=true disimpan → set false penempatan lain santri itu + update Santri.kelas_id
☐ Command audit satu-kali: temukan & laporkan santri yang kelas_id ≠ penempatan aktif (JANGAN auto-fix data produksi, tampilkan dulu)
☐ Test: update penempatan → kelas santri sinkron; nilai baru tercatat ke kelas baru

### WP-31 — Label istilah per tenant
☐ Setting `label.istilah` (JSON map: musyrif, mustahiq, takzir, halaqah, bisyaroh, dst → label tenant)
☐ Helper `istilah('musyrif')` + pakai di navigasi/form yang memuat istilah ini (sapu bertahap, mulai dari modul baru)
☐ Halaman edit di Pengaturan + daftarkan ke WP-34
☐ Test: dua tenant label beda → render beda

### WP-32 — Setor Keliling masuk PWA Keuangan
☐ Layar baru di `/admin/keuangan/pwa`: daftar kamar/blok → santri → input setoran (atau scan QR santri)
☐ Pakai SetorKelilingService yang ada — JANGAN tulis logika baru; endpoint idempoten (kunci: petugas+santri+nominal+timestamp-menit)
☐ Mode antrean offline (IndexedDB, pola camera-gps.js) + badge "belum tersinkron"
☐ Test feature: setoran dobel-kirim tidak dobel-catat; SetorKelilingTest yang ada tetap hijau

### F1-PWA — PWA Guru inti
☐ Rute grup `/guru` + middleware role (pola PortalWali) + controller PortalGuru\*
☐ Manifest + SW + ikon dinamis (salin pola PortalWaliPwaController, branding tenant)
☐ Layar Hari Ini: JadwalPelajaran where guru_pengampu_id=me + jadwal halaqah; kartu sesi + tombol Mulai KBM
☐ Layar presensi kelas: daftar santri rombel, default hadir, toggle pengecualian; endpoint idempoten (kelas+tanggal+santri)
☐ Layar setoran tahfidz: pilih dari halaqahku / scan QR (jsQR ada) → surah/ayat/jenis ziyadah|muraja'ah/nilai → HafalanTahfidz+PenilaianTahfidz via TahfidzService
☐ PresensiService & TahfidzService dipakai juga oleh Filament resource yang ada (refactor tipis — satu kebenaran)
☐ Permission `akses_pwa_guru` + gerbang ganda modul×role di menu
☐ Test: guru tanpa jadwal → empty state ramah; presensi retry tidak dobel; render-test authed

### WP-25 — Presensi kamar & sholat
☐ Master slot presensi pondok per tenant (Setting: slot mana aktif — subuh/maghrib/isya/malam dst)
☐ Layar per-kelompok: pilih kamar/halaqah → grid santri × slot, default hadir
☐ Simpan ke PresensiKegiatan (reuse, jenis=slot) — JANGAN tabel baru
☐ Rekap per kamar/slot di panel Kepengasuhan
☐ Kebijakan → WP-34: slot aktif, jam buka-tutup input
☐ Test: slot nonaktif tak tampil; santri pindah kamar tampil di kamar baru

### WP-26 — Alur izin pulang lintas-aplikasi
☐ PWA Wali: form ajukan izin (jenis, alasan, tanggal) → IzinSantri status 'diajukan'
☐ PWA Guru (musyrif): antrean persetujuan → setujui/tolak + catatan; cek kuota izin bulan ini (Setting)
☐ Surat izin PDF ber-QR (SuratTemplate + chillerlan QR) — QR berisi id izin
☐ Tandai "sudah kembali" dari PWA + alarm keterlambatan (daftar merah di panel & PWA)
☐ Notifikasi WA wali saat disetujui & saat jatuh tempo kembali (modul Notifikasi)
☐ Kebijakan → WP-34: maks izin/bulan, H-berapa pengajuan, siapa boleh mengajukan, approval berlapis ON/OFF
☐ Test: kuota habis → tolak otomatis dengan pesan; izin disetujui muncul di pos gerbang (siapkan query untuk WP-29)

### WP-27 — Register UKS
☐ Layar PWA mode kesehatan: scan QR → form kunjungan (keluhan, tindakan, obat, status pulang|istirahat|rujuk)
☐ Status 'istirahat' aktif → PresensiSantri hari itu otomatis 'sakit' (observer; berhenti saat dipulihkan)
☐ Rujukan keluar: biaya + penanggung (Setting: pondok|wali|dana_kebajikan) → BILA wali: draft tagihan; BILA pondok/kebajikan: draft pengeluaran (posting tetap via jalur Keuangan yang ada)
☐ Notifikasi wali ber-kebijakan (semua|menular|rawat saja)
☐ Panel: register harian + riwayat per santri (RiwayatKesehatan reuse)
☐ Test: istirahat 3 hari → 3 presensi sakit; sembuh hari ke-2 → hari ke-3 normal

### WP-09 — Modul disiplin/ta'zir
☐ Migrasi `jenis_pelanggaran` (kategori ringan|sedang|berat, poin, sanksi_default) + seeder contoh
☐ Migrasi `pelanggaran_santri` (jenis, waktu, lokasi, kronologi, pencatat, status dicatat|diverifikasi|sanksi_dijalankan|selesai|dibatalkan, foto bukti via /_tenant-storage)
☐ Migrasi `sanksi_santri` (pelanggaran, jenis, pelaksana, tanggal, bukti)
☐ Akumulasi poin = query per periode (BUKAN kolom); ambang eskalasi via Setting → event SP1/SP2 → surat otomatis + notifikasi wali ber-kebijakan
☐ Poin positif/prestasi (tabel sama, poin negatif) — saklar ON/OFF
☐ Panel Kedisiplinan: antrean verifikasi, rekap per santri/kelas/asrama, tren
☐ Kebijakan → WP-34: master+poin, ambang, verifikasi 2-lapis ON/OFF, notifikasi wali, masa berlaku poin
☐ Test: pelanggaran belum diverifikasi tak menambah poin (saat saklar ON); lintas ambang → SP tepat sekali (idempoten)

### WP-24 — Disiplin di PWA
☐ Form catat kilat: scan QR → jenis (pencarian cepat) → foto → kirim (status sesuai saklar verifikasi)
☐ Antrean verifikasi untuk ustadz/pengasuhan di PWA
☐ Ceklis eksekusi sanksi + bukti
☐ Test: pencatat pengurus-santri tak bisa memverifikasi catatannya sendiri

---

## TO-DO DETAIL PER WP — GELOMBANG 3A (Akademik)

### WP-06 — Kurikulum per tingkat
☐ Migrasi `kurikulum` (lembaga_id, nama, aktif) + `kurikulum_mapel` (kurikulum, pelajaran, tingkat, kitab_id NULL, jam_per_minggu, is_wajib, urutan)
☐ Filament resource Kurikulum (repeater mapel per tingkat) di aplikasi Akademik
☐ Validasi LUNAK di form JadwalPelajaran: mapel di luar kurikulum tingkat itu → warning, bukan blokir
☐ Aksi "Buka Periode": auto-generate baris Nilai kosong per santri×mapel-wajib (idempoten — jangan dobel bila diklik ulang)
☐ Cetak struktur kurikulum PDF (SuratTemplate) untuk dokumen izin/akreditasi
☐ Test: tenant tanpa kurikulum → semua perilaku lama utuh; dua lembaga kurikulum beda tak saling bocor

### WP-07 — Kenaikan kelas & sidang
☐ `KenaikanKelasService::evaluasi($santri, $periode)` → {rekomendasi naik|tinggal|lulus, alasan[]} dari: rata nilai akhir (NilaiService), % kehadiran (PresensiSantri ÷ hari efektif KalenderService), poin disiplin (WP-09), capaian tahfidz/kitab BILA lembaga mensyaratkan — SEMUA ambang via Setting per lembaga
☐ Halaman Filament "Sidang Kenaikan" (pola LoketKas): pilih kelas → tabel santri + rekomendasi + alasan + dropdown keputusan (default = rekomendasi, bisa di-override per santri) + kolom catatan rapat
☐ Aksi finalisasi: batch buat PenempatanKelas baru (observer WP-05 menyinkronkan) + tutup penempatan lama — SATU transaksi DB
☐ Mode mu'adalah: tingkat tujuan bebas dipilih (lompat/turun) — saklar Setting `akademik.penempatan_mode`
☐ Test: santri pindah tengah tahun dievaluasi di kelas terakhir; finalisasi dua kali tidak menduplikasi penempatan; override tercatat siapa & kapan (Auditable)

### WP-08 — Kelulusan & mutasi
☐ Keputusan 'lulus' di sidang → tulis tabel `alumni` (sudah ada) + status santri 'alumni' + tanggal
☐ Syahadah/sertifikat internal PDF per lembaga (template per lembaga via SuratTemplate; nomor seri ber-counter race-safe pola SuratService)
☐ Arsip nomor ijazah negara (kolom di alumni: nomor_ijazah, jenis) — input manual TU
☐ Mutasi keluar (pindah pondok): status + tanggal + alasan + arsip surat; mutasi masuk: santri baru jalur 'pindahan' di PPDB (tandai asal pondok)
☐ Test: santri lulus hilang dari rombel aktif tapi riwayat nilai/penempatan utuh; alumni tak tertagih SPP periode baru

### WP-10 — Raport diniyah
☐ Migrasi `rapor_diniyah` (santri, periode_diniyah, blok JSON ringkasan, catatan_mustahiq, catatan_pengasuh, generated_at; unique santri+periode)
☐ `RaporDiniyahService::generate()` — kumpulkan: nilai imtihan (Nilai pada lembaga diniyah), capaian kitab (capaian_kitab), hafalan (rekap HafalanTahfidz + nilai sima'an), ubudiyah/akhlak (RubrikSkor — reuse RubrikKriteria), kehadiran kegiatan (PresensiKegiatan ÷ hari efektif), poin disiplin bersih
☐ Input rubrik ubudiyah/akhlak per santri oleh mustahiq (grid massal ala F2)
☐ Template cetak per tenant (LetterHeader; kop tanggal Hijriyah) + blok mana yang tampil = Setting per lembaga → WP-34
☐ Tab "Raport Diniyah" di Portal Wali (menu rapor sudah ada) + unduh PDF
☐ Test: blok dimatikan via Setting → tak tercetak; santri tanpa data tahfidz → blok kosong ramah, bukan error

### WP-11 — Tahfidz lanjutan & kegiatan khas
☐ Migrasi `sima_an` (santri, juz/rentang, penyimak_id, nilai kelancaran/tajwid via rubrik, tanggal) + `munaqasyah` (santri, juz diuji, penguji, hasil lulus|mengulang, nilai, tanggal)
☐ Penanda peserta wisuda tahfidz per periode (kolom di munaqasyah atau tabel kecil) + daftar cetak peserta
☐ Layar sima'an & munaqasyah di PWA Guru (mode muhaffizh — menumpang F1)
☐ Jenis kegiatan 'pasaran/kilatan' di modul Kegiatan + kolom peserta_luar JSON (nama+asal — bukan santri) + presensi gabungan
☐ Test: munaqasyah 'mengulang' tidak memunculkan di daftar wisuda; peserta luar tak menyentuh tabel santri

### WP-12 — Kesiapan EMIS
☐ Halaman rekap: % santri ber-NIK/NISN, % pegawai ber-NIK/NUPTK, lembaga tanpa NSPP/IJOP — dengan tautan langsung ke daftar yang kurang
☐ Audit kolom santri: pastikan nik & nisn ada (tambah bila belum) + mukim/tidak
☐ Test: angka rekap cocok dengan query manual

### F2-PWA — PWA Guru tahap 2
☐ Grid nilai massal: satu layar = kelas × pelajaran × komponen; autosave per-baris (debounce), validasi 0-100 server, input_by otomatis
☐ Halaman kembar di panel Filament (guru ber-laptop) — DUA pintu SATU NilaiService
☐ Form jurnal KBM 30-detik menempel di layar presensi (materi, metode, jumlah hadir auto, status) + rekap bulanan per guru di panel
☐ Layar capaian kitab: sorogan per santri (posisi → lanjut|ulang, riwayat) & bandongan per kelompok (sekali update untuk semua)
☐ Layar wali kelas: rekap kehadiran+nilai+poin anak binaan + input catatan_walikelas rapor + input rubrik ubudiyah (WP-10)
☐ Test: autosave putus-sambung tak kehilangan/menduplikasi nilai; jurnal unique guru+jadwal+tanggal

---

## TO-DO DETAIL PER WP — GELOMBANG 3B (SDM & uang ⚠️)

### WP-13 ⚠️ — Loop bisyaroh
☐ Migrasi `tarif_khidmah` (jabatan_id NULL|jenis_sesi ENUM jam_formal|sesi_diniyah|sesi_tahfidz|malam_musyrif|hadir_kantor, poin DECIMAL, aktif) — tarif per tenant
☐ `KhidmahAggregatorService::agregasi($periode)`: baca JurnalMengajar 'terlaksana' + PresensiStaf + penugasan aktif → tulis baris `khidmah` per pegawai per sumber (kolom sumber_type/sumber_id — tertelusur); idempoten per periode (hapus-tulis-ulang baris auto saja, baris manual JANGAN disentuh)
☐ Layar pratinjau bendahara: rekap per pegawai (rincian sumber) SEBELUM generate slip; selisih dikoreksi di sumber (jurnal/presensi), BUKAN edit angka di slip
☐ buatSlip membaca Σ khidmah periode → SlipGaji.poin (mesin slip & posting TIDAK diubah)
☐ Adversarial 3 lensa WAJIB: (a) sesi 'diganti' — poin ke pengganti, bukan guru asal, tidak dobel; (b) guru 2 lembaga 2 tarif — hitung per penugasan; (c) agregasi ulang setelah slip final → DITOLAK (periode terkunci)
☐ Test: agregasi 2× idempoten; jurnal 'kosong' tak menghasilkan poin

### WP-14 ⚠️ — Potongan cuti
☐ Tandai jenis cuti berbayar/tak berbayar di master cuti (Setting default)
☐ Hitung di terapkanPotongan(): hari_tak_berbayar × (honor_pokok ÷ hari_kerja_periode dari KalenderService) — pembagi via Setting
☐ Cuti beririsan Ramadhan/libur pondok → hari itu TIDAK memotong
☐ Adversarial: cuti lintas dua periode payroll terpotong proporsional di masing-masing
☐ Test: cuti berbayar = potongan nol; math PPh21/BPJS lama tak berubah satu rupiah pun (snapshot test)

### WP-15 ⚠️ — THR
☐ SlipGaji tipe 'thr' (kolom tipe default 'bulanan') — satu per pegawai per tahun (unique)
☐ Formula via Setting: nominal tetap | × honor pokok | manual per pegawai; generate massal + pratinjau
☐ Posting JurnalService idempotency_key "SlipGaji:thr:{id}" — jalur posting SAMA
☐ Test: generate 2× tidak dobel; pegawai non-aktif tak kebagian

### WP-16 — Kontrak, SK & pengabdian
☐ Migrasi `kontrak_kerja` (employee, jenis sk_gty|kontrak|sk_pengabdian|sk_dpk, nomor, mulai, selesai, file, status aktif|berakhir|diperpanjang)
☐ Khusus pengabdian: + periode_khidmat, lembaga_pengirim, tanggal_purna; laporan "angkatan pengabdian" untuk pengasuh
☐ Widget panel + notifikasi: kontrak habis ≤60 hari (ambang Setting)
☐ Perpanjangan = baris baru bertaut (riwayat utuh); purna pengabdian → tawaran GTY/GTT = penugasan baru WP-03
☐ Test: reminder muncul tepat ambang; perpanjang tak menimpa arsip lama

### WP-17 — Izin pegawai harian
☐ Jenis izin baru di tabel cuti yang ada (pulang_kampung, keperluan_keluarga, dinas) — JANGAN tabel baru
☐ Approval = kepala lembaga dari penugasan is_primary (fallback: admin)
☐ Pengajuan & status di PWA Guru (menu profil pegawai)
☐ Test: pegawai tanpa penugasan primary → approval jatuh ke fallback

### WP-18 — Arsip sertifikasi
☐ Migrasi `sertifikasi_pegawai` (jenis tpg|inpassing|gbpns|ppg, NRG, nomor SK, nominal, berlaku_mulai/selesai, file)
☐ Tab di EmployeeResource + kolom rekap di kesiapan EMIS (WP-12)
☐ Test: CRUD + tampil di profil

### F3-PWA ⚠️ — Pesan wali + slip
☐ Migrasi `pesan_wali` (guru, santri, jenis apresiasi|perhatian|info, isi, dikirim_via, sent_at)
☐ Template pesan siap-pakai per tenant (Setting; guru tinggal pilih+sunting)
☐ Saklar moderasi: jenis 'perhatian' butuh persetujuan pengasuh ON/OFF → antrean approve di panel
☐ Masuk menu Notifikasi Portal Wali + opsional WA (modul Notifikasi)
☐ Guru lihat slip gaji sendiri di PWA (gate: hanya slip miliknya; route print reuse)
☐ Test: guru tak bisa kirim ke santri di luar kelas/halaqah binaan; saklar moderasi ON → pesan tertahan

---

## TO-DO DETAIL PER WP — GELOMBANG 4 (gerbang, wali, pimpinan)

### WP-29 — Pos gerbang
☐ Layar pos (PWA mode keamanan, tablet/HP): input scan QR/NFC autofocus (pola keyboard-wedge Stasiun F3) → kartu santri: FOTO BESAR + nama + status izin hari ini (valid/tidak/terlambat)
☐ Log gerbang (santri, arah keluar|masuk, waktu, petugas, izin_id NULL) — keluar tanpa izin valid → tercatat merah + notifikasi pengasuhan
☐ Tandai izin 'sudah kembali' langsung dari pos (sinkron WP-26)
☐ Mode offline ringan: cache daftar izin aktif hari ini (refresh berkala)
☐ Test: kartu santri lain (foto tak cocok) tetap tercatat — keputusan di petugas; santri tanpa izin → merah

### WP-30 — Penjengukan
☐ Migrasi jadwal penjengukan per tenant (hari/jam, kuota per slot — Setting)
☐ PWA Wali: ajukan slot jenguk → konfirmasi otomatis/manual (saklar)
☐ Registrasi tamu di pos: cocokkan dengan pengajuan + data wali terdaftar; tamu tanpa pengajuan = walk-in (saklar boleh/tidak)
☐ KunjunganWali reuse (tambah kolom slot & pengajuan_id)
☐ Test: kuota slot penuh → tolak; wali non-terdaftar sesuai kebijakan

### 2a — Dasbor Mutu Santri
☐ `MutuService::profil($santri, $periode)` — agregat: nilai rata, tren hafalan (laju pekan), capaian kitab, % kehadiran per jenis, poin disiplin, kunjungan UKS — SEMUA query ke tabel yang ada, TANPA tabel data baru (kecuali cache ringkasan opsional)
☐ Aturan lampu kuning via Setting (default dari riset): hafalan macet ≥3 pekan, alpa ≥3/bulan, poin ≥75% ambang SP, nilai turun ≥15
☐ Layar per-santri (profil mutu + grafik tren), per-kelas (heatmap), per-lembaga (agregat) di aplikasi Laporan
☐ Lampu kuning → notifikasi mustahiq (PWA) + wali (saklar per jenis)
☐ Job harian hitung lampu (queue) — jangan real-time berat
☐ Test: ambang custom mengubah lampu; santri pindah kelas grafiknya menyambung

### WP-35 — Dasbor & approval pimpinan PWA
☐ Mode pimpinan di PWA (atau rute /pimpinan ringan): kas hari ini (reuse dasbor PWA Keuangan), kehadiran santri/guru, lampu kuning mutu, antrean approval (ApprovalRequest + izin + SP)
☐ Aksi approve/tolak dari HP (ApprovalRule sudah ada — hanya UI)
☐ Baca-saja selain approval; test: pimpinan non-approver tak melihat antrean

### WP-34 — Pengaturan Kebijakan Pesantren
☐ Registry kebijakan: tiap WP mendaftarkan key Setting + label awam + deskripsi + default + kelompok (disiplin|izin|kesehatan|akademik|keuangan|istilah)
☐ Satu halaman Filament terkelompok + pencarian + tombol "kembalikan ke bawaan" per item & per kelompok
☐ Audit trail perubahan kebijakan (siapa, kapan, dari→ke) — kebijakan uang/disiplin sensitif
☐ Ekspor/impor kebijakan antar tenant (JSON) — onboarding pesantren baru tinggal impor profil "salafiyah kecil" / "modern multi-lembaga" (preset!)
☐ Test: ubah → perilaku berubah; reset → default; preset impor utuh

---

## TO-DO DETAIL PER WP — GELOMBANG 5 (per permintaan/keputusan)

### F4-PWA — Ujian semester online (prasyarat: keputusan owner jenjang)
☐ Refactor EXTRACT (bukan rewrite): PaketSoal/Soal/SesiUjian/UjianAttempt/UjianScoringService → modul bersama; PPDB tetap konsumen pertama — SEMUA test PPDB ujian harus tetap hijau TANPA diubah
☐ Kolom konteks akademik: pelajaran_id, kelas_id, periode_id (NULL = mode PPDB)
☐ Permission pembuat soal per-pelajaran (guru pengampu) — bank soal pribadi + bank lembaga
☐ Jadwal sesi per rombel + mode pengawas di PWA Guru (status attempt live)
☐ Layar koreksi essay di PWA (antrean jawaban → nilai + catatan)
☐ Nilai final → komponen nilai via NilaiService (sambungan ujian→nilai→rapor otomatis)
☐ Halaman ujian di App Santri /app (login NIS+PIN sudah live; timer server-side reuse)
☐ Test: attempt PPDB lama tak terpengaruh; santri tak bisa 2 attempt; nilai essay masuk komponen yang benar

### WP-19 — Rekrutmen ringan
☐ Migrasi `pelamar` (nama, kontak, asal, alumni?, berkas, tahap lamaran|tes_baca_kitab|microteaching|wawancara|diterima|ditolak, catatan penilai per tahap JSON)
☐ Satu Filament resource + aksi "jadikan pegawai" (→ Employee + penugasan WP-03)
☐ Test: konversi membawa data; pelamar ditolak terarsip

### WP-20 — Rapor ustadz tahunan
☐ Rekap otomatis per pegawai per tahun: % kehadiran sesi (JurnalMengajar vs jadwal), % jurnal terisi, ketepatan input nilai
☐ Penilaian kualitatif mudir via RubrikKriteria (reuse) + catatan
☐ Cetak PDF — bahan pertimbangan SK tahun berikutnya (tautan ke WP-16)
☐ Test: angka rekap cocok query manual; hanya mudir/atasan yang bisa menilai

### WP-21 — Mode pengurus-santri di PWA
☐ Permission khusus pada akun santri (login NIS+PIN sudah live) — gerbang ganda: santri AND pengurus
☐ Menu terbatas: catat pelanggaran (status 'dicatat'), presensi kegiatan domain qism-nya
☐ Masa jabatan pengurus (periode) — habis → permission dicabut otomatis
☐ Test: pengurus tak bisa lihat data di luar domainnya; akun santri biasa tak melihat menu

### WP-22 — Ekspor EMIS
☐ Ekspor Excel per kategori (lembaga/santri/pegawai) sesuai kolom EMIS 4.0 — format diverifikasi terhadap unduhan template EMIS terbaru SAAT dikerjakan (format sering berubah — jangan kunci sekarang)
☐ Test: kolom wajib terisi dari data yang ada; data kurang → daftar peringatan, bukan gagal

### WP-23 ⚠️ — Kasbon (prasyarat: kebijakan owner)
☐ Migrasi `pinjaman_pegawai` (nominal, tanggal, disetujui_oleh via ApprovalRule, cicilan_bulanan, sisa_pokok, status)
☐ Potongan cicilan otomatis di terapkanPotongan() — berhenti saat lunas; pelunasan dipercepat manual
☐ Posting: pencairan = jurnal piutang pegawai; cicilan = pengurang di slip (jalur posting slip yang sama)
☐ Adversarial 3 lensa: pegawai resign sisa utang; cicilan > gaji bulan itu; pembulatan akhir
☐ Test: total cicilan = pokok persis (tanpa selisih pembulatan)

### WP-28 — Stok obat (modul-flag)
☐ Tabel obat + stok masuk/keluar (keluar otomatis dari register UKS WP-27)
☐ Peringatan stok menipis; TANPA batch-expiry rumit (pelajaran G2-14: kompleksitas farmasi tidak perlu di UKS)
☐ Test: stok tak bisa minus

### WP-33 — Lapor kerusakan & piket
☐ `laporan_kerusakan` (pelapor user/santri-pengurus, lokasi, foto, status masuk|dikerjakan|selesai, biaya NULL → bila ada: draft pengeluaran via jalur Keuangan)
☐ Ceklis piket area ber-QR lokasi (master area per tenant)
☐ Test: siklus status; foto via /_tenant-storage

### 2b — POS kantin via tabungan (prasyarat: kartu Stasiun F3 + keputusan unit usaha)
☐ Layar kasir (pola LoketKas): scan kartu → UID+PIN (aturan uang kartu) → potong saldo tabungan
☐ Batas belanja harian per santri (Setting wali bisa atur dari PWA Wali!)
☐ Posting per unit usaha (DivisiUnit) — jalur JurnalService
☐ Adversarial 3 lensa: saldo race 2 kasir; refund; rekonsiliasi harian kasir
☐ Test: saldo tak bisa minus; wali melihat riwayat belanja anak

### 2c — Baitul Maal digital
☐ Halaman donasi publik di website tenant (program, nominal, konfirmasi transfer manual dulu — payment gateway MENUNGGU keputusan owner)
☐ Pencatatan muzakki/munfiq + kuitansi PDF; dana masuk → SumberDana sesuai (zakat/infaq/wakaf — dimensi SUDAH ada)
☐ Program beasiswa: santri penerima → potongan otomatis tagihan SPP dari dana kebajikan (jalur piutang yang ada) — ⚠️ adversarial
☐ Laporan penyaluran publik per program (transparansi — halaman web)
☐ Test: dana wakaf tak bisa terpakai program non-wakaf (KehalalanGuard); potongan beasiswa tepat & tertelusur
