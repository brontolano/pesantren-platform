# Service Level Agreement (SLA) & Support Tier
## Platform Pesantren White-Label — PT Rojo Bronto Lano

> **Versi:** 1.0 — 4 Juni 2026
> **Berlaku untuk:** Semua tenant aktif platform pesantren

---

## 1. Definisi Layanan

Platform Pesantren adalah layanan SaaS (Software as a Service) yang menyediakan sistem manajemen pesantren berbasis cloud, mencakup: manajemen santri, keuangan, PPDB, website publik, portal wali, dan modul pendukung lainnya.

---

## 2. Paket Layanan & SLA

### Tier 1 — Paket Dasar (Gratis / Rintisan)

| Metrik | Target |
|---|---|
| **Uptime** | 95% per bulan |
| **Downtime maks.** | ~36 jam/bulan |
| **Waktu respons support** | 3–5 hari kerja |
| **Jalur support** | WhatsApp (jam kerja) |
| **Backup** | Mingguan |
| **Pemulihan data** | Best effort (tidak dijamin) |

**Modul:** Kesantrian + Keuangan dasar + Notifikasi

---

### Tier 2 — Paket Standar

| Metrik | Target |
|---|---|
| **Uptime** | 99% per bulan |
| **Downtime maks.** | ~7,2 jam/bulan |
| **Waktu respons support** | 1–2 hari kerja |
| **Jalur support** | WhatsApp + Email |
| **Backup** | Harian (retensi 30 hari) |
| **Pemulihan data** | Dalam 24 jam sejak permintaan |
| **Pembaruan sistem** | Otomatis (maintenance window Minggu 01:00–03:00 WIB) |

**Modul:** Kesantrian + Keuangan + PPDB + CMS + Notifikasi + Akademik

---

### Tier 3 — Paket Premium

| Metrik | Target |
|---|---|
| **Uptime** | 99,5% per bulan |
| **Downtime maks.** | ~3,6 jam/bulan |
| **Waktu respons support** | 4 jam kerja (prioritas) |
| **Jalur support** | WhatsApp prioritas + Email + Telepon |
| **Backup** | Harian + snapshot mingguan (retensi 90 hari) |
| **Pemulihan data** | Dalam 8 jam sejak permintaan |
| **Pembaruan sistem** | Otomatis + notifikasi H-3 sebelum maintenance |
| **Dedicated support** | Kontak langsung ke tim teknis |

**Modul:** Semua modul aktif

---

## 3. Definisi Downtime & Pengecualian

### Yang Dihitung sebagai Downtime
- Sistem tidak dapat diakses oleh pengguna selama > 5 menit berturut-turut
- Panel admin tidak bisa login karena masalah server
- Website publik tidak dapat dimuat

### Yang TIDAK Dihitung sebagai Downtime
- Scheduled maintenance (diumumkan H-24 sebelumnya)
- Gangguan pada sisi pengguna: internet, perangkat, browser
- Gangguan layanan pihak ketiga (WhatsApp Gateway, email SMTP)
- Force majeure: bencana alam, pemadaman listrik massal, serangan siber skala besar

---

## 4. Prosedur Pelaporan Insiden

### Cara Melaporkan

```
1. WhatsApp ke nomor support: [ISI NOMOR]
   Format: "INSIDEN | [Nama Pesantren] | [Deskripsi masalah]"

2. Email ke: support@pesantren-platform.com
   Subject: [INSIDEN] Nama Pesantren — Deskripsi Singkat
```

### SLA Respons per Severity

| Level | Deskripsi | Tier 1 | Tier 2 | Tier 3 |
|---|---|---|---|---|
| **P1 — Kritis** | Sistem tidak bisa diakses sama sekali | 24 jam | 4 jam | 1 jam |
| **P2 — Tinggi** | Fitur utama tidak berfungsi (login, tagihan, santri) | 3 hari kerja | 1 hari kerja | 4 jam kerja |
| **P3 — Sedang** | Fitur sekunder tidak berfungsi | 5 hari kerja | 2 hari kerja | 1 hari kerja |
| **P4 — Rendah** | Pertanyaan, permintaan fitur, keluhan minor | 7 hari kerja | 3 hari kerja | 2 hari kerja |

---

## 5. Kompensasi (Tier 2 & 3)

Jika uptime bulanan **di bawah target SLA** akibat kesalahan platform (bukan pengecualian di §3):

| Penurunan Uptime | Kompensasi |
|---|---|
| 99% → 95% | Perpanjangan langganan 3 hari |
| 95% → 90% | Perpanjangan langganan 7 hari |
| < 90% | Perpanjangan langganan 14 hari |

Kompensasi diklaim maksimal 30 hari setelah insiden, via laporan tertulis ke support.

---

## 6. Hak & Kewajiban Tenant

### Hak Tenant
- Mengakses laporan uptime bulanan (atas permintaan)
- Mendapatkan notifikasi maintenance minimal H-24 sebelumnya
- Mengekspor data sendiri kapan saja via panel admin
- Mengakhiri langganan dan meminta penghapusan data

### Kewajiban Tenant
- Menjaga kerahasiaan kredensial login
- Tidak menggunakan platform untuk aktivitas ilegal
- Melaporkan kerentanan keamanan yang ditemukan (responsible disclosure)
- Memperbarui informasi kontak (nomor WA, email) yang valid

---

## 7. Penghapusan Data & Off-boarding

Saat langganan berakhir atau tenant meminta off-boarding:

1. **Periode grace:** 30 hari setelah masa langganan berakhir — data masih bisa diakses (read-only)
2. **Ekspor data:** Tenant bisa download semua data via panel admin selama periode grace
3. **Penghapusan:** Setelah 30 hari grace period, semua data dihapus permanen dari server
4. **Konfirmasi:** Tenant menerima konfirmasi tertulis bahwa data sudah dihapus

---

## 8. Kontak Support

| Jalur | Kontak | Jam Operasional |
|---|---|---|
| WhatsApp | [ISI NOMOR] | Senin–Jumat, 08:00–17:00 WIB |
| Email | support@pesantren-platform.com | Senin–Jumat, 08:00–17:00 WIB |
| Darurat (Tier 3) | [ISI NOMOR DARURAT] | 24/7 |

---

*Dokumen ini berlaku sejak 1 Juli 2026. PT Rojo Bronto Lano berhak mengubah SLA dengan pemberitahuan 30 hari sebelumnya kepada tenant aktif.*
