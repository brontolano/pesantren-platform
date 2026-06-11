# Blueprint — Modul Akademik (Rinci) + Modul SDM/HRD Pesantren (2026-06-11)

Riset 2-arah: (1) riset web organisasi pesantren Indonesia — struktur salafiyah/modern/tahfidz/yayasan multi-lembaga, regulasi UU 18/2019 + PMA 30-32/2020, EMIS/PDPontren, kekhasan SDM (bisyaroh, pengabdian, rangkap jabatan), alur harian lapangan, pembanding ePesantren/SIPond; (2) audit repo menyeluruh (Payroll/Employee/Khidmah/Cuti/PresensiStaf yang SUDAH ada, Akademik/PenempatanKelas/Rubrik, pola Ledger 5-pagar, ModuleRegistry, Setting, PWA Wali). Dokumen ini adalah KELANJUTAN dari `BLUEPRINT-PWA-GURU.md` — fase-fase di sana (Halaqah, JurnalMengajar, Kitab, komponen nilai) menjadi prasyarat sebagian fase di sini; jangan dikerjakan dobel.

## Visi

Dua modul yang lahir dari satu temuan riset: **di pesantren, satu orang adalah banyak peran** (guru MTs pagi + musyrif asrama + pengajar diniyah malam + pengurus qism) dan **satu pesantren adalah banyak lembaga** (yayasan → MI/MTs/MA formal Kemenag + madrasah diniyah takmiliyah + tahfidz + pondok/asrama, masing-masing dengan kalender, kurikulum, raport, dan kas berbeda). Maka tulang punggung kedua modul adalah dua model jangkar: **Lembaga** (unit pendidikan ber-NSM/NSPP/NPSN, jenis formal|diniyah|tahfidz|pondok, terhubung ke DivisiUnit Ledger yang SUDAH ada agar laporan keuangan per-unit langsung jalan) dan **PenugasanPegawai** (many-to-many pegawai×jabatan×lembaga×periode — bukan kolom `jabatan` string seperti sekarang). Dari dua jangkar itu mengalir semuanya: jadwal & raport per lembaga, bisyaroh terhitung dari sesi mengajar nyata (JurnalMengajar dari blueprint PWA Guru), SK yayasan tercetak dari penugasan, dan data EMIS (lembaga+santri+ustadz) siap ekspor karena field-nya ditangkap sejak input. Diferensiasi vs pesaing (ePesantren/SIPond kuat di SPP, dangkal di sini): bisyaroh multi-peran, dua kalender Masehi/Hijriyah, sorogan per-halaman kitab, guru pengabdian, dan EMIS-ready. Semua tabel tenant-aware + Auditable; semua posting uang lewat JurnalService idempoten (pagar yang sudah teruji — JANGAN bikin jalur posting baru); modul menyusut/mengembang via ModuleRegistry per tenant — pesantren salaf kecil cukup Lembaga tunggal "Pondok" tanpa merasa memakai ERP yayasan raksasa.

## Temuan kunci yang mengikat desain

1. **Rangkap jabatan adalah norma** → penggajian per-PERAN bukan per-orang: slip satu, sumber honor banyak (jam MTs + sesi diniyah + tunjangan musyrif).
2. **Dua kalender paralel**: formal Masehi (Juli-Juni), diniyah Hijriyah (mulai Syawal, libur penuh Ramadhan + kegiatan pasaran/kilatan).
3. **Bisyaroh ≠ gaji flat**: dihitung per jam/sesi/kitab dari kehadiran mengajar; repo SUDAH punya `khidmah` (poin per periode) + `SlipGaji.poin` tapi pengisiannya manual — loop-nya yang harus disambung, mesinnya jangan diganti.
4. **Status kepegawaian beragam dalam satu yayasan**: GTY, GTT/honorer, PNS DPK, PPPK, guru pengabdian (alumni khidmat 1-2 th, bisyaroh minim + fasilitas), ustadz kalong (tidak mukim).
5. **Tunjangan negara via SIMPATIKA**: TPG sertifikasi, inpassing, insentif GBPNS — ERP cukup MENCATAT (NRG, status, nominal) untuk arsip & syarat, bukan menghitung.
6. **Dua raport terpisah**: raport formal (RDM Kemenag) vs raport diniyah/kepesantrenan (kitab, ubudiyah, akhlak, hafalan) — JANGAN dipaksa satu skema nilai.
7. **Disiplin berbasis poin berjenjang** (mis. 50 poin = SP1 + panggil wali, 100 = SP2 + skorsing) dengan pencatat berlapis: qism keamanan (santri) → mahkamah/pengasuhan (ustadz).
8. **EMIS/PDPontren** butuh NSPP+IJOP lembaga, NIK+NISN santri, data ustadz — prasyarat SEMUA bantuan (BOP ±Rp10jt, insentif ustadz non-ASN); tangkap sejak input, ekspor belakangan.
9. Repo SUDAH punya lebih banyak dari dugaan: PresensiStaf, Cuti (+approval), Khidmah, ApprovalRule, Rubrik penilaian, Alumni, PenempatanKelas ber-riwayat — modul SDM ini banyak MENYAMBUNG, sedikit membangun.

---

## BAGIAN I — MODUL AKADEMIK (perincian)

### A-0 Fondasi: Lembaga & dua kalender — effort: sedang

(1) Tabel `lembaga`: tenant-aware, nama, jenis ENUM('formal','diniyah','tahfidz','pondok','mahad_aly','unit_usaha'), jenjang VARCHAR NULL (MI/MTs/MA/SMP/SMA/SMK | ula/wustha/ulya), nomor_statistik VARCHAR NULL (NSM/NPSN/NSPP), izin_operasional VARCHAR NULL + tanggal, kepala_id FK users NULL, divisi_unit_id FK NULL ke DivisiUnit Ledger (sambungan laporan keuangan per-unit GRATIS — dimensi divisi sudah ada di tiap baris jurnal), aktif. Seeder default 1 lembaga "Pondok" agar tenant lama & pesantren kecil TIDAK berubah perilaku. (2) ALTER `kelas` + `lembaga_id` FK NULL (NULL = lembaga default; backfill semua kelas lama ke lembaga Pondok), ALTER `periode_akademik` + `lembaga_id` NULL + `kalender` ENUM('masehi','hijriyah') default masehi + `tahun_hijriyah` VARCHAR NULL — satu tenant boleh punya periode aktif GANDA asal beda lembaga (ubah unique constraint: tenant+lembaga+tahun+semester). (3) Tabel `kalender_akademik`: tenant, lembaga_id NULL (NULL = berlaku semua), tanggal_mulai/selesai, jenis ENUM('libur_nasional','libur_pondok','ramadhan','ujian','pasaran','kegiatan'), keterangan; service `KalenderService::isHariEfektif($tanggal, $lembaga)` dipakai presensi & rekap kehadiran. JANGAN bangun konversi Hijriyah otomatis (hisab vs rukyat beda-beda) — tanggal Hijriyah adalah LABEL yang diisi manual, tanggal Masehi tetap kolom kerja.

### A-1 Kurikulum & mapel per tingkat — effort: kecil-sedang

Tabel `kurikulum` (tenant, lembaga_id, nama mis. "K-Merdeka MTs 2026" | "Diniyah Wustha", aktif) + `kurikulum_mapel` (kurikulum_id, pelajaran_id, tingkat, kitab_id NULL ← model Kitab dari blueprint PWA Guru F0, sks/jam_per_minggu, is_wajib, urutan). `Pelajaran` tetap flat (tidak diubah — dipakai Nilai & Jadwal), kurikulum hanya LAPISAN PEMETAAN di atasnya: dipakai untuk (a) validasi jadwal ("mapel ini memang untuk tingkat ini?") — warning bukan blokir, (b) auto-generate baris Nilai kosong per santri saat periode dibuka, (c) cetak struktur kurikulum untuk dokumen izin/akreditasi. Mapel diniyah = pelajaran biasa ber-kitab_id; bedanya hanya lembaga induknya.

### A-2 Kenaikan kelas, kelulusan & mutasi — effort: sedang

`PenempatanKelas` (sudah ada, ber-riwayat) jadi tulang punggung — JANGAN bikin tabel baru. Yang dibangun: (1) **Observer sync**: saat PenempatanKelas is_current berubah → Santri.kelas_id ikut (risk yang ditemukan audit: sekarang manual, nilai bisa tercatat ke kelas lama). (2) `KenaikanKelasService`: evaluasi per santri (nilai akhir rata, % kehadiran dari PresensiSantri+KalenderService hari efektif, poin pelanggaran dari A-4, capaian tahfidz/kitab bila lembaga mensyaratkan — semua ambang via Setting per lembaga) → REKOMENDASI naik/tinggal/lulus, BUKAN keputusan otomatis: layar sidang kenaikan (Filament page ala LoketKas) menampilkan rekomendasi + override per santri oleh rapat guru — keputusan kenaikan di pesantren adalah musyawarah, sistem hanya menyiapkan data. Batch generate PenempatanKelas baru + tutup periode lama dalam SATU transaksi. (3) Kelulusan → tulis ke tabel `alumni` yang sudah ada + status santri; dokumen kelulusan per jenis lembaga: ijazah negara (formal — di luar sistem, cukup arsip nomor), syahadah internal (diniyah/tahfidz — generate PDF via pola SuratTemplate yang ada). (4) Mutasi keluar/masuk pesantren (pindah pondok): status + tanggal + arsip dokumen.

### A-3 Raport ganda: formal vs diniyah/kepesantrenan — effort: sedang

Raport yang ada (IPK/SKS/rank — gaya formal) DIPERTAHANKAN untuk lembaga formal. Tambahan: `rapor_diniyah` per santri×periode-diniyah berisi blok-blok yang faktanya berbeda sumber: nilai imtihan per mapel-kitab (dari Nilai pada lembaga diniyah), capaian kitab/sorogan (dari capaian_kitab — blueprint PWA Guru), hafalan (rekap HafalanTahfidz: total juz + nilai sima'an), **ubudiyah & akhlak** (penilaian rubrik — pakai RubrikKriteria/RubrikSkor yang SUDAH ada di repo, jangan bikin skema baru), kehadiran kegiatan pondok (dari PresensiKegiatan), poin disiplin, catatan mustahiq + catatan pengasuh. Template cetak via LetterHeader/SuratTemplate; kolom tanggal Hijriyah tampil di kop. Wali melihat KEDUA raport di Portal Wali (menu rapor sudah ada — tinggal tab).

### A-4 Disiplin & ta'zir berbasis poin — effort: sedang

(1) `jenis_pelanggaran` (master per tenant: nama, kategori ringan|sedang|berat, poin, sanksi_default — seeder contoh umum). (2) `pelanggaran_santri`: santri_id, jenis_id, tanggal+waktu, lokasi, kronologi, pencatat_id (bisa pengurus santri — lihat catatan role), status ENUM('dicatat','diverifikasi','sanksi_dijalankan','selesai','dibatalkan') — pelanggaran yang dicatat qism keamanan WAJIB diverifikasi ustadz/pengasuhan sebelum poin berlaku (temuan riset: pencatat berlapis santri→mahkamah; tanpa gerbang ini poin bisa jadi alat bully antar santri). (3) `sanksi_santri` (pelanggaran_id, jenis sanksi, pelaksana, tanggal, bukti). (4) Akumulasi poin per periode = query, bukan kolom (hindari drift); ambang eskalasi via Setting (mis. 50=SP1+notifikasi wali otomatis via modul Notifikasi, 100=SP2) — notifikasi ke wali HANYA pada eskalasi atau kategori berat, bukan tiap pelanggaran ringan (kebijakan via Setting). (5) Prestasi/poin positif sebagai penyeimbang (tabel sama, poin negatif) — banyak pesantren memakai reward-punishment. Masuk modul Kesantrian (bukan modul baru).

### A-5 Imtihan, sima'an & kegiatan khas — effort: kecil-sedang

(1) Imtihan = ujian lembaga diniyah: pakai mesin ujian hasil generalisasi F4 blueprint PWA Guru BILA online, atau cukup input nilai via grid massal F2 BILA tulis/lisan (mayoritas) — jangan paksa online. Jenis nilai imtihan: tulis, lisan/musyafahah (penguji menilai langsung), praktik. (2) Tahfidz lanjutan: `sima_an` (santri, juz yang disimak, penyimak, nilai kelancaran/tajwid via rubrik, tanggal) + `munaqasyah` (ujian akhir tahfidz) + penanda peserta `wisuda_tahfidz` per periode — semuanya kecil, menumpang pola PenilaianTahfidz. (3) Kegiatan pasaran/kilatan Ramadhan: pakai modul Kegiatan yang SUDAH ada (Kegiatan + PresensiKegiatan + template + recurring) — cukup jenis kegiatan baru + boleh peserta non-santri (kolom peserta_luar JSON) — JANGAN bangun modul baru.

### A-6 EMIS & pelaporan Kemenag — effort: kecil (fase arsip), sedang (fase ekspor)

Fase arsip (sekarang): pastikan field tertangkap — lembaga: NSPP/NSM+IJOP (A-0); santri: NIK, NISN, mukim/tidak (audit kolom santri, tambah yang kurang); pegawai: NIK, NUPTK/NRG (Bagian II). Fase ekspor (nanti, setelah ada tenant yang butuh): ekspor Excel berformat EMIS per kategori — JANGAN bangun integrasi API EMIS (tidak ada API publik yang stabil; pesaing pun manual). Tambahan bernilai: halaman rekap "kesiapan EMIS" (berapa santri tanpa NIK, dst) — membantu TU melengkapi data.

---

## BAGIAN II — MODUL SDM/HRD

### S-0 Fondasi: struktur organisasi & penugasan multi-peran — effort: sedang-besar (jantung modul)

(1) Tabel `jabatan` (master per tenant: nama, kategori ENUM('struktural','pengajar','kepengasuhan','pengurus_santri','staf'), seeder dari riset: mudir, kepala madrasah, waka kurikulum/kesiswaan, mustahiq, musyrif, muhaffizh, lurah pondok, qism keamanan/bahasa/ibadah, TU, bendahara unit, ustadz pengabdian — tenant bebas tambah). (2) **`penugasan_pegawai`**: employee_id, jabatan_id, lembaga_id, tanggal_mulai, tanggal_selesai NULL (jabatan aktif = selesai NULL), nomor_sk NULL, file_sk NULL, is_primary BOOL (jabatan utama untuk tampilan), keterangan — INILAH pengganti kolom `Employee.jabatan` string; kolom lama dibiarkan (read-only, deprecated) sampai migrasi data selesai, JANGAN drop di migrasi yang sama. Riwayat jabatan = query penugasan non-aktif — TIDAK perlu tabel riwayat terpisah. (3) ALTER `employees`: + nik VARCHAR NULL, nuptk/nrg VARCHAR NULL, status_kepegawaian perluas ENUM ('gty','gtt_honorer','pns_dpk','pppk','pengabdian','kontrak'), is_mukim BOOL, tanggal_selesai NULL (resign/purna), pendidikan_terakhir, asal_alumni BOOL (temuan riset: rekrutmen dominan alumni). (4) Sinkron role: penugasan jabatan → auto-assign role Spatie yang sesuai (mapping jabatan→role via kolom di master jabatan) — agar PWA Guru/panel langsung membuka menu yang benar; revoke saat penugasan ditutup. (5) Cetak SK penugasan via SuratTemplate (pola sudah ada).

### S-1 Bisyaroh multi-peran: sambungkan loop yang sudah setengah jadi — effort: sedang (MENYENTUH UANG — adversarial-verify wajib)

Audit menemukan mesinnya SUDAH ada: KomponenGaji per-employee (tetap/tunjangan/potongan), `khidmah` (poin per periode), SlipGaji.poin×tarif_poin, PayrollService idempoten → JurnalService 4-baris. Yang dibangun adalah SAMBUNGANNYA: (1) `tarif_khidmah` per tenant: jabatan_id/jenis_sesi → poin atau rupiah per sesi (mis. jam MTs = 2 poin, sesi diniyah = 1.5, malam musyrif = 1; tarif_poin global sudah ada di FiturKeuangan). (2) `KhidmahAggregatorService`: tiap periode payroll, baca JurnalMengajar status 'terlaksana' (blueprint PWA Guru F3) + PresensiStaf + penugasan aktif → tulis baris `khidmah` per pegawai per sumber (BUKAN langsung ke slip — khidmah jadi staging yang bisa diperiksa), lalu SlipGaji.poin = Σ khidmah periode itu saat buatSlip. Layar pratinjau rekap per pegawai SEBELUM generate slip massal (bendahara memeriksa; selisih = koreksi di sumber, bukan edit slip). (3) Potongan cuti: Cuti (sudah ada + approval) yang jenisnya tak berbayar → potongan = hari × (honor_pokok/hari_kerja_periode) — masuk terapkanPotongan() yang ada; cuti Ramadhan TIDAK memotong (kalender pondok — cek KalenderService). (4) ATURAN BAKU REPO BERLAKU PENUH: posting tetap HANYA dari SlipGaji final via JurnalService (jangan posting khidmah/presensi sendiri-sendiri), idempotency_key per slip, JANGAN ubah math PPh21/BPJS yang ada, gate "PERLU REVIEW PENASIHAT PAJAK" tetap terpasang (TER PMK 168/2023 + surcharge non-NPWP dicatat sebagai utang compliance Q3 — sudah ada gate Q3 pajak di memori proyek). (5) THR/bingkisan hari raya: SlipGaji tipe baru 'thr' satu kali per tahun, komponen manual atau formula sederhana via Setting — posting jurnal sama.

### S-2 Administrasi kepegawaian: SK/kontrak, izin, presensi, pengabdian — effort: sedang

(1) `kontrak_kerja`: employee_id, jenis (sk_gty|kontrak|sk_pengabdian|sk_dpk), nomor, tanggal mulai/selesai, file arsip, status — reminder kontrak habis (widget panel + notifikasi). (2) Khusus **pengabdian**: kolom periode_khidmat di kontrak + asal lembaga pengirim (pola Gontor mengirim ke pesantren lain) + tanggal purna → laporan "guru pengabdian angkatan ini" untuk pengasuh; selesai pengabdian → tawaran GTT/GTY = penugasan baru. (3) Izin pegawai harian (beda dari cuti): pulang kampung, keperluan keluarga — pakai pola tabel cuti yang ada (tambah jenis), approval atasan = kepala lembaga dari penugasan primary. (4) PresensiStaf (sudah ada) diperkuat: rekap bulanan per lembaga, dan tautan opsional ke khidmah (kehadiran kantor staf TU dihargai poin tetap — via aggregator S-1, bukan jalur baru). (5) `sertifikasi_pegawai`: jenis (TPG/inpassing/GBPNS/PPG), NRG, nomor SK, nominal, berlaku — ARSIP untuk syarat & pelaporan, bukan mesin hitung. (6) Pinjaman/kasbon pegawai: tabel pinjaman + cicilan auto-potong slip — TUNDA ke fase lanjut KECUALI owner minta (menyentuh uang, butuh kebijakan dulu); cantumkan di keputusan owner.

### S-3 Rekrutmen & evaluasi kinerja ringan — effort: kecil (TUNDA jika perlu)

Pesantren merekrut dari alumni & jejaring kyai — JANGAN bangun ATS korporat. Cukup: (1) tabel `pelamar` sederhana (nama, asal, alumni?, berkas, tahap ENUM('lamaran','tes_baca_kitab','microteaching','wawancara','diterima','ditolak'), catatan penilai) — satu Filament resource. (2) Evaluasi kinerja = RAPOR USTADZ tahunan ringan: rekap otomatis (kehadiran sesi %, jurnal terisi %, nilai diisi tepat waktu) + penilaian kualitatif mudir via rubrik (RubrikKriteria reuse) — dipakai pertimbangan SK tahun berikutnya. Bukan KPI 360.

---

## BAGIAN III — PEMETAAN JABATAN → APLIKASI (panel & PWA)

Prinsip tetap: **panel Filament untuk kerja meja, PWA untuk kerja lapangan; SATU PWA bisa melayani banyak jabatan via permission** — jangan tambah PWA baru bila momen kerjanya sama.

| Jabatan (dari riset) | Kerja meja → Panel ERP | Kerja lapangan → PWA |
|---|---|---|
| Kyai/Pengasuh/Mudir 'Aam | Laporan lintas lembaga, approval SK & ApprovalRule | (cukup panel mobile/Laporan) |
| Pengurus yayasan | SK penugasan, keuangan lintas unit | — |
| Kepala madrasah/mudir unit + waka | Aplikasi Akademik: kurikulum, jadwal, sidang kenaikan, raport, EMIS | PWA Guru (mode kepala: monitor jurnal & presensi guru) |
| TU/operator EMIS | Kesantrian + rekap kesiapan EMIS + arsip pegawai | Stasiun Pendaftaran (blueprint terpisah) |
| Bendahara unit | Keuangan + pratinjau khidmah + slip massal | PWA Keuangan Lapangan (live) |
| Mustahiq/wali kelas diniyah | Raport diniyah, catatan | **PWA Guru**: absen diniyah, nilai imtihan grid, catatan santri |
| Ustadz pengajar (semua status, termasuk pengabdian) | — | **PWA Guru**: jadwal, presensi, jurnal (→ bisyaroh), nilai, slip gaji sendiri |
| Muhaffizh | — | **PWA Guru**: setoran ziyadah/muraja'ah, sima'an, progres halaqah |
| Musyrif/murabbi | — | **PWA Guru**: absen kamar/malam, presensi sholat, izin pulang santri, catat pelanggaran (verifikasi), pesan wali |
| Qism keamanan/ibadah/bahasa (pengurus SANTRI) | — | **Mode pengurus di PWA Guru**: input pelanggaran (status 'dicatat', menunggu verifikasi ustadz) + presensi kegiatan — login via akun santri NIS+PIN dengan permission pengurus, BUKAN akun pegawai |
| Lurah pondok | Rekap pelanggaran & kegiatan | PWA Guru mode pengurus |
| Wali santri | — | PWA Wali (live): + tab raport diniyah, + notifikasi poin/SP |
| Santri | — | App Santri `/app` (kelak): jadwal, progres hafalan/kitab, poin |

KESIMPULAN PWA: **TIDAK perlu PWA baru.** Semua kebutuhan jabatan di atas masuk PWA Guru/Khidmat (diperluas mode musyrif + mode pengurus-santri), PWA Keuangan Lapangan, PWA Wali, App Santri. Peta tetap 3 internal + 2 eksternal.

---

## BAGIAN IV — BREAKDOWN TUGAS DEVELOPER (work package, urut dependensi)

Metode baku tiap WP: agent build → adversarial review (WP uang: 3 lensa) → verifikasi independen → test hijau → PR → deploy; render-test authed sebelum deploy panel; WP uang JANGAN ubah math teruji.

**Gelombang 1 — Jangkar (tidak tergantung apa pun):**
- WP-01 `lembaga` + seeder default + tautan DivisiUnit + ALTER kelas/periode (A-0.1-2) — test: tenant lama tak berubah perilaku
- WP-02 `kalender_akademik` + KalenderService::isHariEfektif (A-0.3)
- WP-03 `jabatan` master + `penugasan_pegawai` + sinkron role Spatie + cetak SK (S-0) — test: rangkap 3 jabatan 2 lembaga, tutup penugasan revoke role
- WP-04 ALTER employees (NIK/NUPTK/status baru/mukim) + form Filament (S-0.3)
- WP-05 Observer sync PenempatanKelas→Santri.kelas_id (A-2.1) — kecil, risiko data nyata, kerjakan awal

**Gelombang 2 — Akademik inti (butuh G1 + PWA-Guru F0):**
- WP-06 `kurikulum` + `kurikulum_mapel` + validasi jadwal + auto-baris nilai (A-1)
- WP-07 KenaikanKelasService + layar sidang kenaikan + batch penempatan (A-2.2) — adversarial: santri pindah tengah tahun, periode ganda beda lembaga
- WP-08 Kelulusan→alumni + syahadah PDF + mutasi keluar/masuk (A-2.3-4)
- WP-09 Disiplin: jenis_pelanggaran + pelanggaran_santri + verifikasi 2-lapis + sanksi + eskalasi poin + notifikasi wali via Setting (A-4)
- WP-10 `rapor_diniyah` + blok rubrik ubudiyah/akhlak + cetak + tab di Portal Wali (A-3) — butuh WP-09 (blok poin) & data tahfidz/kitab
- WP-11 Sima'an + munaqasyah + wisuda tahfidz + pasaran via Kegiatan (A-5)
- WP-12 Rekap kesiapan EMIS (A-6 fase arsip)

**Gelombang 3 — SDM uang (butuh G1 + PWA-Guru F1/F3 jurnal):**
- WP-13 `tarif_khidmah` + KhidmahAggregatorService + layar pratinjau (S-1.1-2) — UANG: 3 lensa (double-count sesi diganti? guru 2 lembaga? periode tutup?)
- WP-14 Potongan cuti tak berbayar + integrasi kalender pondok (S-1.3) — UANG
- WP-15 SlipGaji tipe THR (S-1.5) — UANG
- WP-16 `kontrak_kerja` + reminder + alur pengabdian (S-2.1-2)
- WP-17 Izin pegawai harian + approval kepala lembaga (S-2.3)
- WP-18 `sertifikasi_pegawai` arsip (S-2.5)

**Gelombang 4 — Pelengkap (boleh kapan saja setelah G1):**
- WP-19 Rekrutmen pelamar sederhana (S-3.1)
- WP-20 Rapor ustadz tahunan (S-3.2) — butuh WP-13 berjalan ≥1 periode
- WP-21 Mode pengurus-santri di PWA Guru (Bagian III) — butuh PWA-Guru F1 live + WP-09
- WP-22 Ekspor Excel EMIS (A-6 fase ekspor) — TUNDA sampai ada tenant yang minta
- WP-23 Pinjaman/kasbon + auto-potong cicilan — HANYA bila owner putuskan kebijakan; UANG

Estimasi kasar: G1 ≈ 1 loop pendek; G2 & G3 bisa paralel di worktree terpisah setelah G1 merge (pola wt-f6 self-sufficient).

## Keputusan Owner (sebelum build)

- LEMBAGA: daftar lembaga nyata tiap tenant pilot (asy-syifaa & Alba Kids: apa saja unitnya, mana yang ber-NSM/NSPP?) — menentukan seeder WP-01
- JABATAN & TARIF: daftar jabatan + tarif khidmah per jenis sesi (jam formal vs sesi diniyah vs malam musyrif) — WP-03/13, menyentuh uang, harus tegas
- DISIPLIN: master jenis pelanggaran + poin + ambang SP + kebijakan notifikasi wali (tiap kejadian atau hanya eskalasi?) — WP-09
- KALENDER: tenant pilot pakai kalender diniyah Hijriyah terpisah atau cukup satu kalender? — menentukan kerumitan A-0
- RAPORT DINIYAH: blok apa saja yang dicetak + siapa penandatangan (mustahiq? pengasuh?) — WP-10
- CUTI & POTONGAN: kebijakan cuti tak berbayar + hari kerja per periode + cuti Ramadhan — WP-14
- PENGABDIAN: apakah tenant menerima/mengirim guru pengabdian, durasi standar — WP-16
- KASBON: mau diaktifkan? plafon & aturan potong — WP-23 (boleh "tidak dulu")
