# Migrasi ke PWA Wali Santri — Catatan Transisi (MIG-1)

Status: **SELESAI** per 2026-06-10. Aplikasi monitoring wali lama sudah
sepenuhnya digantikan PWA Wali di `{domain-tenant}/wali`.

## Hasil verifikasi sunset

| Item | Status |
|---|---|
| Rute/halaman monitoring wali lama | **Sudah tidak ada** — seluruh halaman dimigrasi ke controller `App\Http\Controllers\PortalWali\*` (Academic, Hafalan, Kesehatan, Kegiatan, Izin, Rapor, Financial, Pembayaran, Tabungan, Topup, Notifikasi, Profil). |
| Redirect peserta lama | `/portal/{any}` → `/spmb/login` (routes/web.php) — bookmark lama tetap berfungsi. |
| Blade/view lama | Tidak ada sisa view `monitoring*`/`wali*` di luar PortalWali (diverifikasi 2026-06-10). |

## Peta URL baru (per tenant)

| Kebutuhan | URL |
|---|---|
| Login wali | `https://{tenant}.pesantren.online/wali/login` |
| Beranda (ringkasan keuangan + switcher anak) | `/wali` |
| Tagihan & Cara Bayar | `/wali` → tab **Tagihan** |
| Kirim bukti pembayaran | tab **Pembayaran** |
| Tabungan + Setor Saldo (topup) | tab **Tabungan** |
| Akademik / Hafalan / Kesehatan / Kegiatan / Izin / Rapor | menu di tab **Akun** |
| Install ke layar utama (PWA) | banner install otomatis (manifest dinamis ber-branding tenant) |

## Akses akun wali

1. Akun wali dibuat otomatis saat santri punya `no_hp_wali` (lihat `WaliAccountService`).
2. Aktivasi/lupa password: OTP via WhatsApp dari halaman login.
3. Satu akun wali bisa memantau **banyak anak** (switcher di beranda); akses
   per-anak ditegakkan policy (PortalWaliAccessTest).

## Kontrol & keamanan terkait

- Pembekuan rekening & limit tarik harian diatur staf di panel: **Keuangan →
  Tabungan → aksi "Limit / Bekukan"** (berlaku untuk tarik tunai & bayar dari saldo).
- Semua alur uang (topup → verifikasi, bayar dari saldo, verifikasi bukti) race-safe
  (lock + transaksi; lihat `TabunganLedgerIntegrityTest`).
- Notifikasi WA event uang ke wali/admin via Pengaturan Notifikasi (toggle,
  template, webhook per-tenant).

## Operasional

- Tidak ada data yang perlu dimigrasi manual — PWA membaca tabel yang sama.
- Bila tenant lama menyimpan tautan app lama di pengumuman/website, arahkan ke
  `/wali`. Tautan `/portal/*` apa pun otomatis dialihkan ke `/spmb/login` (peserta).
