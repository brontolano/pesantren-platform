# Keandalan Notifikasi WhatsApp — Trade-off WAHA (WEBJS) vs WhatsApp Cloud API

Dokumen keputusan (owner). **Tidak** mengubah arsitektur berjalan; merekam
alasan tetap memakai WAHA + jalur mitigasi, dan mendokumentasikan WhatsApp
Cloud API resmi Meta sebagai **opsi jalur-kedua** untuk notifikasi kritis.

## Status saat ini

Notifikasi WhatsApp platform dikirim lewat pipa **n8n → WAHA** (engine WEBJS),
yaitu WhatsApp Web yang diotomasi. Gratis, tanpa biaya per-pesan, dua-arah
(bisa terima balasan → chatbot AI Khadim), tetapi **rapuh**: sesi bisa logout
sewaktu-waktu, butuh scan QR ulang, dan berisiko diblokir bila kirim massal
tanpa jeda.

Pengerasan pada PR ini menambah dua lapis pertahanan **tanpa mengganti WAHA**:

1. **Enkripsi kredensial** — URL webhook & API key WAHA di tabel `settings`
   kini disimpan terenkripsi (`Crypt::encryptString`), kompatibel-mundur dengan
   nilai plaintext lama.
2. **Health-check sesi** — `wa:health-check` (dijadwalkan harian ~06:40) memeriksa
   status sesi WAHA; bila sesi ≠ `WORKING`, alarm dicatat ke log agar operator
   tahu WA "diam-diam mati". Status juga tampil sebagai badge di halaman
   Pengaturan Notifikasi.

## Perbandingan

| Aspek | WAHA (WEBJS) — dipakai sekarang | WhatsApp Cloud API (resmi Meta) |
|---|---|---|
| Biaya | Gratis (self-host); hanya biaya server | Berbayar per-percakapan (24 jam); ada kuota gratis terbatas |
| Keandalan | Rapuh — sesi logout, perlu scan QR, risiko blokir | Andal — SLA resmi, tak ada sesi/QR |
| Verifikasi | Cukup nomor WhatsApp biasa | Wajib verifikasi bisnis + nomor terdaftar Meta Business |
| Template | Bebas — teks apa pun | Pesan di luar jendela 24 jam **wajib** template yang disetujui Meta |
| Dua arah | Ya — terima balasan (chatbot AI Khadim) | Ya, via webhook, tetapi model percakapan berbayar |
| Kecepatan mulai | Instan (scan QR) | Lambat — proses verifikasi bisnis berhari-hari |
| Risiko diblokir | Ada (dianggap otomasi tak resmi) | Rendah (jalur resmi) |

## Keputusan

- **Tetap WAHA** sebagai jalur utama: gratis, dua-arah, cocok untuk mayoritas
  notifikasi pesantren dan chatbot.
- **WhatsApp Cloud API = opsi jalur-kedua** untuk notifikasi **kritis**
  (mis. OTP, tagihan jatuh tempo) bila suatu pesantren menuntut keandalan tinggi
  dan bersedia menanggung biaya + verifikasi bisnis. Belum diimplementasi;
  dicatat sebagai jalur eskalasi bila WAHA terbukti tak cukup andal untuk
  kasus tertentu.
- **Mitigasi WAHA** yang sudah berjalan: health-check + alarm sesi mati, jeda
  antar-pesan broadcast (rate-limit), dan pencatatan jujur status kirim
  (`sent`/`failed`/`simulated`/`disabled`) di Log Notifikasi.

## Catatan operasional (owner)

- Bila WAHA dijalankan dengan `WAHA_API_KEY`, set ENV `WAHA_API_KEY` (atau isi
  field "API key WAHA" per-tenant di Pengaturan Notifikasi) agar health-check &
  pengiriman langsung menyertakan header `X-Api-Key`.
- Endpoint `/api/sessions` harus terjangkau dari aplikasi. Bila WAHA memakai
  daftar path yang dikecualikan dari auth (`WAHA_API_KEY_EXCLUDE_PATH`),
  **jangan** mengandalkan itu untuk `/api/sessions` — sediakan API key yang benar.
- `WAHA_BASE_URL` kosong ⇒ health-check dilewati (mode pipa n8n); ini normal
  dan bukan kondisi "sakit".
