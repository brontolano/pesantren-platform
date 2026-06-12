# Laporan QC pesantren.online — 13 Juni 2026

## Ringkasan

| Metrik | Nilai |
|--------|-------|
| Total route diperiksa | 117 |
| Error 500 | 0 |
| Error di log Laravel | 0 |
| Tenant live | 3 (demo, asy-syifaa, albakids) |
| Disk VPS | 69 GB / 96 GB (73%) |

---

## Tenant Health

| Tenant | Status |
|--------|--------|
| demo.pesantren.online | ✅ 200 OK |
| asy-syifaa.pesantren.online | ✅ 200 OK |
| albakids.pesantren.online | ✅ 200 OK |
| pesantren.online (central) | ✅ 200 OK |

---

## Admin Panel (Filament) — 58 halaman

| Modul | Jumlah Resource | Status |
|-------|-----------------|--------|
| Dashboard + Aktivasi Modul | 2 | ✅ OK |
| Kesantrian | 6 | ✅ OK |
| Akademik | 14 | ✅ OK |
| Asrama + Kepengasuhan | 5 | ✅ OK |
| Kesehatan | 3 | ✅ OK |
| Kegiatan | 4 | ✅ OK |
| Payroll/SDM | 10 | ✅ OK |
| Ledger | 11 resource + 6 page | ✅ OK |
| PPDB | 3 | ✅ OK |
| Tahfidz | 5 | ✅ OK |
| Inventory | 3 | ✅ OK |
| Approval | 2 | ✅ OK |
| Baitul Maal | 3 | ✅ OK |
| CMS | 4 | ✅ OK |
| CRM | 2 | ✅ OK |
| Broadcast WA + Kotak Masuk | 2 | ✅ OK |
| AI Pages | 3 | ✅ OK |
| Laporan | 8 | ✅ OK |
| Keuangan PWA | 6 | ✅ OK |

---

## PWA Kepala Keuangan — 21 halaman + 18 API

| Halaman | Status |
|---------|--------|
| Beranda | ✅ 302 (auth) |
| Penerimaan (Tagihan, Verifikasi, Loket) | ✅ OK |
| Kas Harian (Tabungan, POS) | ✅ OK |
| Pengeluaran (Approval, Payroll) | ✅ OK |
| Ledger (Jurnal, Anggaran) | ✅ OK |
| Laporan (Index, Neraca LR, Arus Kas, Operasional, Dana, Neraca Saldo) | ✅ OK |
| PSAK 109, ISAK 35, Rekap Tahunan | ✅ OK |
| Pajak (SPT Masa, e-Bupot, SPT Badan, Sisa Lebih) | ✅ OK |
| PWA Assets (manifest, icon, sw.js) | ✅ 200 OK |

---

## Portal Guru — 15 halaman

| Halaman | Status |
|---------|--------|
| Beranda, Nilai, Setoran, Simaan, Munaqasyah | ✅ OK |
| Disiplin (Daftar, Catat, Poin) | ✅ OK |
| Izin, Kitab, Wali Kelas, Gerbang | ✅ OK |
| Pesan, Slip Saya, Ujian, Koreksi | ✅ OK |
| PWA Assets (manifest, icon, sw.js) | ✅ 200 OK |

---

## Portal Wali & App Santri & Pimpinan

| Portal | Status |
|--------|--------|
| Wali — Login, Aktivasi, Lupa Password | ✅ 200 OK |
| Wali — PWA Assets | ✅ 200 OK |
| Wali — Auth pages | ✅ 302 (auth) |
| Santri — Login | ✅ 200 OK |
| Santri — Auth pages | ✅ 302 (auth) |
| Pimpinan — Beranda | ✅ 302 (auth) |
| Pimpinan — API ringkasan | ✅ 302 (auth) |

---

## Database Health (tenant demo)

| Tabel | Jumlah Record |
|-------|---------------|
| Santri | 18 |
| Users | 23 |
| Jurnal | 30 |
| Akun (CoA) | 47 |
| Sumber Dana | 7 |
| TER PPh 21 | 125 |
| Error log (200 baris terakhir) | 0 error |

---

## Temuan & Perbaikan

| Status | Temuan |
|--------|--------|
| ✅ Fixed | Jurnal: field `keterangan` → `deskripsi` (deploy 79000cb) |
| ✅ Fixed | Neraca & Laba Rugi: kosong karena expect `sections`, padahal service return flat array |
| ✅ New | Rekap Keuangan Tahunan: halaman baru format PDF pondok |
| ℹ️ Info | `/tanya-ustadz` return 404 — endpoint AI belum ada route publik (gated GATE-3) |
| ℹ️ Info | Disk usage VPS: 69GB / 96GB (73%) — monitor jika mendekati 85% |
| ℹ️ Info | Semua halaman auth-protected return 302 (redirect login) — benar, bukan error |

---

## Dokumentasi & Pelaporan

| Halaman | Status |
|---------|--------|
| /dokumentasi/ | ✅ 200 OK |

---

*Laporan ini dibuat otomatis pada 13 Juni 2026 oleh sistem QC pesantren.online.*
