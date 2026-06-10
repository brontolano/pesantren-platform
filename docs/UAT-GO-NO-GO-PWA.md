# Paket UAT & Go/No-Go — PWA Peserta SPMB & PWA Wali Santri

Status teknis per 2026-06-10: seluruh fitur LIVE, audit keamanan + uji beban
hijau (`docs/AUDIT-KEAMANAN-UJI-BEBAN-PORTAL.md`), smoke otomatis tersedia
(`scripts/smoke-pwa.sh`, cron harian di VPS). Dokumen ini adalah satu-satunya
gerbang tersisa sebelum rilis v1.0: **UAT pilot oleh pengguna nyata**.

## Cara menjalankan UAT (pilot: Asy-Syifaa)

Peserta pilot: 2–3 wali santri + 2–3 calon peserta + 1 staf TU.
Durasi: 3–5 hari pemakaian wajar. Pendamping: Hamdan (owner).

### Skenario PWA Peserta (https://<tenant>/spmb)
| # | Skenario | Lulus? | Catatan |
|---|---|---|---|
| P1 | Aktivasi akun via OTP WA → set password → login | ☐ | |
| P2 | Lengkapi biodata + unggah dokumen dari kamera HP | ☐ | |
| P3 | Lihat tagihan PPDB + unggah bukti transfer | ☐ | |
| P4 | Kerjakan ujian online (timer, autosave, kirim) | ☐ | |
| P5 | Lihat pengumuman + kartu ujian QR | ☐ | |
| P6 | Daftar ulang → akun App Wali terbentuk | ☐ | |
| P7 | Install PWA ke home screen (Android & iOS) | ☐ | |
| P8 | Buka saat offline → shell tetap tampil | ☐ | |

### Skenario PWA Wali (https://<tenant>/wali)
| # | Skenario | Lulus? | Catatan |
|---|---|---|---|
| W1 | Aktivasi otomatis + login + ganti anak (multi-anak) | ☐ | |
| W2 | Lihat tagihan + cara bayar + unggah bukti | ☐ | |
| W3 | Terima notif WA saat bukti diverifikasi/ditolak | ☐ | |
| W4 | Setor saldo tabungan (bottom-sheet) → antrean → saldo naik | ☐ | |
| W5 | Bayar tagihan dari saldo | ☐ | |
| W6 | Install PWA + buka offline | ☐ | |

### Skenario staf TU (panel /admin)
| # | Skenario | Lulus? | Catatan |
|---|---|---|---|
| T1 | Verifikasi berkas + bukti bayar dari antrean | ☐ | |
| T2 | Nilai essay ujian online | ☐ | |
| T3 | Keputusan seleksi → notif WA terkirim | ☐ | |

## Kriteria Go/No-Go (isi setelah UAT)

| Kriteria | Ambang | Hasil |
|---|---|---|
| Skenario kritis (P1-P6, W1-W5, T1-T3) lulus | 100% | |
| Bug blocker ditemukan | 0 | |
| Bug mayor terbuka saat rilis | ≤ 2 dengan workaround | |
| Keluhan performa (load > 5 dtk) | 0 | |
| Smoke harian selama UAT | hijau terus | |

**Keputusan:** ☐ GO — rilis v1.0 · ☐ NO-GO — perbaiki dulu: ______________

## Pasca-GO (hari rilis)
1. Umumkan ke wali/peserta via broadcast WA (template sudah ada).
2. Pantau 7 hari: `bash scripts/smoke-pwa.sh` (cron) + error log harian.
3. Catat metrik: jumlah install PWA, login harian, bukti bayar/hari.
