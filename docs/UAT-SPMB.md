# UAT — PWA SPMB (Peserta) & PPDB (Panitia)

Skenario uji terima end-to-end. Jalankan di tenant uji (mis. demo) dengan **nomor WA asli**
(bukan nomor gateway 628557271197). Tandai ✅/❌ tiap langkah.

## A. Alur Peserta (PWA `/spmb`)
1. **Pendaftaran** — isi form publik → terima WA konfirmasi + nomor pendaftaran.
2. **Aktivasi** — `/spmb/aktivasi` → masukkan No. WA → terima OTP → buat password → masuk dashboard.
3. **Login / Lupa password** — `/spmb/login`; reset via OTP.
4. **Dashboard** — status (stepper) + kelengkapan biodata + tracking; lengkapi biodata.
5. **Berkas** — `/spmb/berkas` → unggah tiap dokumen (kamera/galeri, ≤5 MB) → status "Menunggu Verifikasi" → "Lihat".
6. **Tagihan** — `/spmb/tagihan` → unggah bukti transfer → status "menunggu_verifikasi".
7. **Pengumuman** — `/spmb/pengumuman` → tampil keputusan setelah dirilis panitia (+WA).
8. **Kartu Ujian** — `/spmb/kartu-ujian` → identitas + jadwal + QR + cetak.
9. **Daftar Ulang** — (saat lulus) CTA dashboard → konfirmasi → jadi Santri aktif (+WA pengingat bila perlu).
10. **Offline** — matikan jaringan → halaman terakhir tetap tampil (service worker).

## B. Alur Panitia (Filament `/admin`)
1. **Dokumen PPDB** — verifikasi (✅)/tolak (alasan) → peserta dapat WA + status berubah.
2. **Tagihan PPDB** — Lihat Bukti → Konfirmasi Lunas / Tolak Bukti → peserta dapat WA.
3. **Bank Soal** — CRUD SoalUjian (PG/essay, kunci, bobot) per kategori.
4. **Jadwal Ujian** — CRUD JadwalUjian (tanggal/waktu/ruang) → tampil di kartu peserta.
5. **Calon Santri** — Kirim Ulang OTP / Koreksi No. WA; **Ingatkan Daftar Ulang** (saat lulus).
6. **Keputusan** — set lulus/cadangan/ditolak (butuh ≥1 hasil ujian) → peserta dapat WA pengumuman.

## C. Smoke test route (tamu → 302 login; sehat → 200)
```
GET /spmb/login        → 200
GET /spmb/aktivasi     → 200
GET /spmb/berkas       → 302 /spmb/login   (butuh login peserta)
GET /spmb/tagihan      → 302 /spmb/login
GET /spmb/pengumuman   → 302 /spmb/login
GET /spmb/kartu-ujian  → 302 /spmb/login
GET /spmb/daftar-ulang → 302 /spmb/login
GET /admin             → 302 /admin/login  (panel staf)
```

## D. Keamanan (lihat juga SECURITY-AUDIT-PPDB.md)
- Peserta A tak bisa buka file/tagihan peserta B (utak-atik id → 403/404).
- Staf non-peserta tak bisa `/spmb`; peserta tak bisa `/admin`.
- Upload >5 MB / mime tak didukung → ditolak.

> Go/No-Go: semua A & B ✅ + smoke C sesuai + spot-check D.
