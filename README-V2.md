# Pesantren.online v2

Fondasi platform SaaS baru yang terpisah dari aplikasi lama. Kode aplikasi v2 berada di `apps/platform-v2`. Slice ini memprioritaskan workspace Superadmin: ringkasan operator, daftar dan draf organisasi, penyiapan tenant, undangan admin pertama, katalog modul, status layanan, pemantauan teknis, dan log aktivitas.

V2 memakai Laravel 13, Blade/Livewire, dan PostgreSQL 16 dalam modular monolith. Antarmuka memakai paket Composer Laravel yang berdiri sendiri di `packages/pesantren-design-system`; paket ini tidak bergantung pada Filament. Repo UI terpisah sedang disiapkan untuk publikasi. Data registri platform berada di basis data pusat; setiap organisasi memiliki basis data tenant sendiri. Panel pusat dan tenant memiliki identitas, otorisasi, konfigurasi sesi, serta penyimpanan terpisah.

## Batas fase awal

PPDB, kesantrian, keuangan, dan Portal Orang Tua belum dibangun. Modul tersebut hanya dapat tampil sebagai katalog nonaktif dan harus ditolak oleh otorisasi server. Panel pusat tidak membaca data santri. Penghapusan permanen dan impersonasi belum tersedia.

## Mulai

Baca [panduan sistem desain dan batas paket](docs/v2/design-system/guide.md), [panduan instalasi dan konfigurasi](docs/v2/operations/installation.md), serta [model izin](docs/v2/product/permissions-and-tenancy.md). Arsitektur dan pilihan tenancy dijelaskan di [ADR-001](docs/v2/architecture/ADR-001-stack-tenancy.md). Panduan tes dan CI tersedia di [verification.md](docs/v2/testing/verification.md).

**Jangan arahkan v2 ke basis data, bucket, antrean, sesi, atau domain aplikasi lama. Laravel Boost bersifat opsional untuk integrasi IDE pengembangan; jangan aktifkan tool Boost di staging. SDK runtime `laravel/ai` dan kredensial provider AI tidak termasuk fondasi ini.** Gunakan hanya `.env` lokal yang diabaikan Git dan data sintetis. Jangan membuka staging internet sebelum MFA operator, validasi keamanan, dan pemulihan cadangan lulus.




\n
