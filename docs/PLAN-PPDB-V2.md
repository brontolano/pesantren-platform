# Plan Pengembangan — PPDB v2 (Portal Calon Santri)

> Status dokumen: **PERENCANAAN** · Dibuat 2026-06-05 · Pemilik: Hamdan (solo dev)
> Progres dipantau live di panel central → menu **Manajemen Proyek** (`/platform`).
> Catatan: modul PPDB v2 **belum dibangun** — dokumen ini hanya pemahaman + rencana.

---

## 1. Visi (dari pemilik)

PPDB v2 adalah **portal calon santri self-service** end-to-end, bukan sekadar admin mencatat pendaftar. 10 langkah alurnya:

1. Satu **form pendaftaran online** di website (CTA wajib input **No. Telepon** = nomor utama untuk login nanti).
2. Generate **E-Tiket QR Code** Nomor Peserta + (Nama, No. Telepon, Username & Password sementara) → akun `calon_santri`.
3. **Notifikasi WhatsApp** via webhook ke workflow **n8n + WAHA** (sediakan pengaturannya per tenant).
4. Wali/calon santri login → **dipaksa reset password**, ganti baru.
5. Isi **informasi lengkap**: data diri, data orang tua, data wali, **upload dokumen** (image/PDF). *(frontend user)*
6. Lihat **tagihan, jadwal seleksi, daftar ulang**, dan **fitur pembayaran**. *(frontend user)*
7. **CRUD peserta**, monitoring, approval, **push notifikasi** on-app / WhatsApp. *(frontend admin)*
8. **Ujian Online** — user mengerjakan soal bertimer / admin membuat soal & kunci → generate nilai / manual.
9. Saat **daftar ulang** (lulus + lunas, datang ke pondok): sistem menyediakan **penempatan kelas & asrama** + **auto-generate ID Card** (cetak PDF + kartu visual di akun santri).
10. Akun `calon_santri` **dikonversi** jadi **akun santri aktif** untuk login via aplikasi santri / aplikasi orang tua.

---

## 2. Kondisi saat ini (PPDB v1 yang sudah ada)

Modul `Modules/PPDB` sudah punya **pondasi data & alur admin**, tapi **belum ada portal/akun untuk calon santri**.

| Komponen | Sudah ada |
|---|---|
| Form publik | Form Blade dasar di `/ppdb` (`TenantWebsiteController::ppdbStore`) → simpan baris `calon_santri` status `pending` |
| Nomor & token | `nomor_pendaftaran` (`PPDB-YYYY-xxxx`) + `tracking_token` (cek status publik) |
| Model | `CalonSantri`, `CalonSantriDokumen`, `HasilUjian`, `TagihanPpdb` |
| Service | RegistrationNumber, DocumentUpload, StatusTracking, ExamResult, AdmissionDecision, Reregistration, Billing, DecisionNotification, FormFieldConfiguration |
| Keputusan seleksi | `lulus` / `cadangan` / `ditolak` (wajib ada ≥1 hasil ujian) |
| Daftar ulang | Konversi data → buat record `Santri` (NIS auto), link `santri_id` |
| Billing | Sinkron ke modul Keuangan (`Tagihan`) |
| Notifikasi WA | Template workflow n8n+WAHA `ppdb-wa` (`asy-syifaa-api/n8n/workflows/...`) — **belum** dipanggil dari Laravel |

**Status `status_pendaftaran` saat ini:** `pending → lulus|cadangan|ditolak → registrasi_ulang`.

---

## 3. Gap Analysis (Visi vs Sekarang)

| # | Langkah visi | Sekarang | Gap utama |
|---|---|---|---|
| 1 | Form + No. HP sebagai login | Form ada, No. HP belum jadi login | Jadikan No. HP identitas akun |
| 2 | E-Tiket QR + kredensial | Nomor + token ada | QR, e-tiket PDF, username/password |
| 3 | Notifikasi WA (n8n+WAHA) | Workflow n8n ada | Pemicu webhook dari Laravel + setting per tenant |
| 4 | Login + paksa reset password | — | Akun + flow forced reset |
| 5 | Data lengkap + upload dokumen (user) | Model dokumen ada (admin) | UI portal user |
| 6 | Tagihan/jadwal/daftar ulang + bayar (user) | Billing admin ada | UI portal + payment gateway |
| 7 | CRUD + monitoring + approval + push notif | CRUD + approval ada | Push notif on-app/WA |
| 8 | Ujian online bertimer + auto-grade | Input nilai manual saja | Bank soal + engine ujian |
| 9 | Penempatan kelas/asrama + ID Card PDF | Konversi Santri ada | Integrasi kelas/asrama di alur + generator ID card |
| 10 | Konversi → akun santri/wali app | Konversi data ada | Akun login santri/wali |

---

## 4. Arsitektur & Keputusan Teknis (perlu diputuskan sebelum coding)

### 4.1 Akun calon santri (fondasi — blok semua langkah lain)
- **Identitas login = No. HP.** Opsi:
  - **(A) Pakai tabel `users` + role `calon_santri`** (spatie/permission), scoped per tenant. Saat lulus & daftar ulang, role di-upgrade ke `santri`/`wali` dan ditautkan ke record `Santri`. ➜ *Rekomendasi* (paling DRY, langsung nyambung ke Portal Wali & app nanti).
  - (B) Guard/tabel terpisah `calon_santri_accounts`. Lebih terisolasi tapi dobel infrastruktur auth.
- **Password sementara** di-generate + dikirim via WA; flag `must_reset_password` memaksa ganti di login pertama.

### 4.2 E-Tiket QR Code (langkah 2)
- Lib: **`simplesoftwareio/simple-qrcode`** atau `endroid/qr-code` + **`barryvdh/laravel-dompdf`** untuk render PDF. *(belum ada di composer)*
- QR meng-encode `tracking_token` / URL status. E-tiket memuat Nomor Peserta, Nama, No. HP, Username & Password sementara.

### 4.3 Notifikasi WhatsApp (langkah 3) — n8n + WAHA
- Workflow sudah ada: `ERP-Pesantren/asy-syifaa-api/n8n/workflows/ppdb-wa-notify-n8n-waha-template.json`.
  - Webhook `POST /webhook/ppdb-wa` → normalisasi No. HP (`62…`) → `WAHA /api/sendText`.
  - Payload: `{ to, message, eticket_no, student_name, source }`.
  - Env n8n: `N8N_WAHA_BASE_URL`, `N8N_WAHA_API_KEY`, `N8N_WAHA_SESSION`.
- **Sisi Laravel yang harus dibangun:**
  - `Setting` per tenant: URL webhook n8n, template pesan, toggle aktif.
  - Service pengirim (mis. `WaNotificationService`) → POST ke webhook saat: daftar berhasil, keputusan seleksi, reminder tagihan, info daftar ulang.
  - Queue + retry + log status kirim.

### 4.4 Ujian Online (langkah 8)
- Tabel baru: `bank_soal`, `soal`, `opsi_jawaban`, `ujian_sesi` (timer server-side), `jawaban_peserta`.
- Auto-grading untuk pilihan ganda; manual untuk essay. Hasil masuk ke `HasilUjian` yang sudah ada → reuse alur keputusan.

### 4.5 Pembayaran (langkah 6)
- Reuse `Tagihan` (modul Keuangan). Tambah **payment gateway** (Midtrans/Xendit) atau konfirmasi manual + bukti transfer.

### 4.6 ID Card & konversi akun (langkah 9–10)
- Template kartu → PDF (dompdf) + render visual di akun santri.
- Konversi: upgrade akun `calon_santri` → `santri`/`wali`, aktifkan login aplikasi (Sanctum sudah terpasang untuk API mobile).

---

## 5. Roadmap Berfase (sinkron dengan papan Manajemen Proyek)

| Fase | Fokus | Langkah visi |
|---|---|---|
| **0** | Pondasi akun calon santri (login No. HP, password sementara, forced reset) | 1·2·4 |
| **1** | Pendaftaran + E-Tiket QR + Notifikasi WA + setting WA per tenant | 1·2·3·4 |
| **2** | Portal calon santri: data lengkap, upload dokumen, tagihan/jadwal, pembayaran | 5·6 |
| **3** | Admin PPDB: CRUD, monitoring, approval, push notif | 7 |
| **4** | Ujian online: bank soal, ujian bertimer, auto-grade | 8 |
| **5** | Daftar ulang: penempatan kelas/asrama, ID Card, konversi akun santri/wali | 9·10 |

> Urutan kritikal: **Fase 0 dulu** — tanpa akun calon santri, langkah 4/5/6/10 tidak bisa jalan.

---

## 6. Dependensi yang perlu ditambahkan (composer)

Belum ada di `composer.json` saat ini:
- `simplesoftwareio/simple-qrcode` (atau `endroid/qr-code`) — QR e-tiket.
- `barryvdh/laravel-dompdf` — render PDF (e-tiket & ID card).
- (Opsional) SDK payment gateway (Midtrans/Xendit).

Sudah ada & bisa di-reuse: `laravel/sanctum` (API app santri/wali), `spatie/laravel-permission` (role), `stancl/tenancy` (isolasi per tenant).

---

## 7. Risiko & Catatan

- **Isolasi tenant tetap wajib.** Semua data PPDB v2 tetap di DB tenant (per aturan: `pesantren.online` = SaaS bisnis saja; data operasional di tenant). Papan Manajemen Proyek ini ada di **central** dan tidak boleh bocor ke panel tenant.
- **Dua pintu pendaftaran** saat ini (Blade produksi vs Inertia modul) — satukan ke satu alur sebelum menumpuk fitur baru, agar logika nomor/token tidak dobel.
- **Notifikasi WA** bergantung pada n8n + WAHA yang sehat (session WhatsApp aktif). Sediakan fallback/log bila gagal kirim.
- **Keamanan akun calon:** rate-limit login, verifikasi kepemilikan No. HP (OTP via WA) sangat dianjurkan sebelum produksi.

---

## 8. Cara pantau progres

Buka panel central **pesantren.online/platform** → grup **Manajemen Proyek**:
- **Proyek** → "PPDB Portal v2" (lihat % progres keseluruhan).
- **Task** → dikelompokkan per Fase; ubah status inline (Belum/Dikerjakan/Terblokir/Selesai) & progres.
- **Dashboard** → widget "Progres Pengembangan" (ringkasan total/selesai/dikerjakan/terblokir).
