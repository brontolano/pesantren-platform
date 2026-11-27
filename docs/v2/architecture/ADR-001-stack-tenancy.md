# Keputusan arsitektur v2 — ADR-001

Status: dipilih untuk fondasi dan prototipe lokal; validasi kapasitas serta MFA wajib sebelum staging publik.
Tanggal tinjau sumber: 28 November 2026.

## Keputusan

Gunakan **Laravel 13 + Livewire 4/Blade + Tailwind CSS 4 + PostgreSQL 16** sebagai modular monolith baru di `apps/platform-v2`. UI inti menggunakan paket Laravel milik produk `brontolano/pesantren-online-design-system`, dikembangkan bersama produk tetapi tidak bergantung pada Filament. Laravel Boost dipasang sebagai dependensi development untuk membantu agen memahami aplikasi Laravel; tidak ada fitur AI runtime di slice ini.

Panel pusat memakai identitas `PlatformOperator` dan jalur masuk tersendiri; panel organisasi memakai identitas dan sesi tenant yang terpisah. Penyimpanan pusat menyimpan organisasi, domain, status provisioning, katalog/aktivasi modul, metadata database, token undangan yang di-hash, dan audit operator. Setiap organisasi memiliki database PostgreSQL sendiri untuk identitas/admin dan data tenant. Database tenant tidak menjadi koneksi default.

Batas modul aplikasi: **Platform** (ringkasan, organisasi, status layanan, audit), **Identity & Access** (operator terpisah dari tenant admin, policy/middleware, MFA operator sebelum staging), **Tenant Provisioning** (domain kanonis, database, status/attempt, kunci idempotensi/retry), **Module Catalog** (definisi/availability yang diperiksa server), dan **UI Framework** (design tokens, shell, komponen Blade, pola form/tabel responsif, aksesibilitas). Tidak ada microservice, Redis, atau runtime kedua dalam slice ini.

Di v2, tenant baru mendapat hanya beranda pengelolaan identitas dan halaman uji akses. PPDB, kesantrian, keuangan, dan portal orang tua tidak diimplementasikan; tampil hanya sebagai katalog nonaktif dan server menolak akses. Tidak ada data santri di panel/monitor pusat.

## Opsi stack

| Opsi | Kelebihan untuk v2 | Biaya/risiko | Keputusan |
|---|---|---|---|
| Laravel 13 + Livewire 4/Blade + Tailwind 4 + PostgreSQL 16 | Memanfaatkan pengetahuan dan tes Laravel lama; UI server-side dinamis tanpa SPA/runtime frontend terpisah; shell, form, navigasi dan tabel dapat disusun dalam package produk; PostgreSQL tersedia di VPS aktif. | UI package dan aksesibilitasnya perlu dibangun/dirawat sendiri; Laravel 13 memerlukan PHP 8.3 sedangkan image produksi yang terlihat memakai PHP 8.2, sehingga v2 perlu image sendiri. | **Dipilih.** Memenuhi kebutuhan layar operasional khas, memberi kendali atas perilaku tabel seluler, dan tidak menambah runtime server kedua. |
| Laravel 13 + Filament 5 + PostgreSQL 16 | Memanfaatkan pengetahuan Laravel lama dan menyediakan resource, dashboard, form serta akses kontrol dengan konvensi bawaan. | Kerangka tabel/navigasinya membatasi susunan layar yang diinginkan; penyesuaian berat tetap harus diuji terhadap update Filament. PHP 8.3 juga diperlukan untuk Laravel 13. | Ditolak sebagai shell utama. Dapat dipertimbangkan untuk alat internal yang tidak memerlukan tata letak produk. |
| Django 5.2 LTS + Django Admin + PostgreSQL 16 | Admin CRUD matang dan dukungan LTS sampai April 2028; satu server-side runtime. | Tim perlu membangun kembali pengetahuan, autentikasi, desain panel, serta integrasi dengan ekosistem PHP yang sudah ada; admin bawaan tetap memerlukan UX khusus untuk onboarding staf. | Ditolak: biaya alih ekosistem melebihi manfaat teknis yang belum terukur. |
| NestJS + React + PostgreSQL 16 | Pemisahan API/UI fleksibel dan cocok untuk interaksi klien kaya. | Memerlukan runtime/build frontend, pola authorization lintas API, lebih banyak komponen untuk login/session, dan beban operasi lebih tinggi. | Ditolak: kompleksitas belum memberi manfaat terukur bagi formulir dan tabel awal. |

Penilaian “cepat membangun” dan “lebih mudah dirawat” adalah perkiraan berdasarkan jumlah runtime, pola lama, dan cakupan layar awal; tidak ada angka benchmark yang diklaim. Kinerja akan diukur dari LCP/INP/CLS serta p95 daftar organisasi menggunakan fixture sintetis sebelum staging.

## Isolasi tenancy

| Model | Keuntungan | Biaya dan kontrol yang diperlukan |
|---|---|---|
| Tabel bersama, `tenant_id` | Infrastruktur dan migrasi paling sederhana; biaya awal rendah. | Setiap query, cache, job, berkas, ekspor dan audit wajib menegakkan scope; satu jalur lupa berpotensi membuka data tenant lain. PostgreSQL RLS adalah pertahanan tambahan, bukan pengganti policy aplikasi. Pemulihan satu tenant selektif lebih rumit. |
| Database terpisah per tenant | Kredensial, restore, migrasi dan pemantauan dapat dibatasi per tenant; sesuai permintaan v2 memiliki konfigurasi/database/penyimpanan/sesi sendiri. | Provisioning, rotasi kredensial, backup dan migrasi menambah operasi. Data pusat tidak boleh tersimpan pada database tenant; koneksi ditentukan resolver domain tepercaya dan cache konfigurasi diberi namespace tenant. |

**Pilihan:** database per tenant untuk penyimpanan identitas organisasi dan seluruh data tenant. Database pusat memegang registri serta metadata koneksi yang dienkripsi oleh platform. Nama domain dinormalisasi dan memiliki constraint unik pusat; tenant ID/domain tidak dipercaya dari request. Setup menyimpan state terpisah dari state layanan. Retry menggunakan idempotency key, constraint unik, transaksi/lock pusat dan pencatatan langkah agar tenant yang sama dapat dilanjutkan tanpa membuat domain atau akun duplikat.

Undangan admin menyimpan hash token sekali pakai, masa berlaku pendek, organisasi dan alamat tujuan; email hanya masuk ke mail sink lokal pada demo. Akun dibuat/diaktifkan setelah klaim token tervalidasi. Tidak ada kata sandi polos di log, email fixture, atau panel operator. Operasi lintas tenant dan pemeriksaan tenant diwajibkan di server. Unit test memakai dua tenant fiktif dan PostgreSQL service yang dapat dibuang.

## UI framework dan tata letak

Bangun package Composer milik produk di `packages/pesantren-design-system`, dengan nama `brontolano/pesantren-online-design-system`. Package memuat design tokens, layout, komponen Blade, pola form, status, navigasi, tombol, tabel responsif, dan panduan aksesibilitas. Tidak bergantung pada Filament, komponen berlisensi, atau font jarak jauh. Aplikasi Laravel tetap menjadi host autentikasi, policy, data, dan Livewire; UI package berdiri sendiri sebagai lapisan presentasi yang dapat digunakan ulang. Kode dan panduan package akan diterbitkan ke repositori GitHub terpisah dengan nama `brontolano/pesantren-online-design-system`.

- Desktop: sidebar kiri yang dapat diciutkan; baris atas berisi breadcrumb, pencarian yang berfungsi, dan akun; isi halaman punya satu CTA utama dan hierarki judul/status jelas.
- Ponsel: navigasi dalam drawer, isi satu kolom, dan daftar data berubah menjadi kartu/baris berlabel dengan aksi terlihat sehingga kolom utama tidak terpotong. Form memecah langkah panjang; tombol tindakan tidak bergantung hover.
- Tabel memakai HTML semantik di layar lebar dan kartu berlabel pada layar kecil. Filter/paginasi tersedia, kolom identitas tidak dipersempit sampai tidak terbaca, dan aksi utama tetap terlihat.
- Status selalu punya label teks; fokus keyboard tampak; pesan gagal menyebut bidang serta cara memperbaikinya; kontras dan ukuran target diuji.
- Package punya source dan dokumentasi sendiri, serta dapat diuji tanpa Filament.

## Laravel Boost

URL yang dikirim pengguna adalah halaman AI Assisted Development Laravel. Halaman itu mengarahkan ke **Laravel Boost**, yaitu alat development: MCP tools, panduan AI dan pencarian dokumentasi Laravel yang cocok dengan versi package. Boost ditambahkan sebagai dev dependency dan diaktifkan untuk Codex; konfigurasi mesin-local yang dihasilkan tidak menyimpan rahasia dan tidak perlu dipublikasikan. Ini berbeda dari package runtime Laravel AI SDK (`laravel/ai`) yang menghubungkan provider eksternal serta menambah storage percakapan; SDK runtime tidak dipasang pada slice ini karena belum ada fitur produk yang memerlukannya. Tidak ada API key dibutuhkan untuk Boost.

## Sesi, penyimpanan, antrean, dan observabilitas

- Konfigurasi aplikasi berada di `.env` lokal yang tidak dilacak; contoh hanya berisi nama variabel tanpa nilai rahasia. App key, koneksi pusat, koneksi tenant, dan sesi v2 berbeda dari aplikasi lama.
- Cookie platform dan tenant memakai nama/path/host sesuai panel. Domain cookie tidak dibagi dengan sistem lama. CSRF aktif; logout dan pertukaran kredensial menginvalidasi sesi.
- File tenant memakai disk privat dengan prefix/volume per tenant dan authorization sebelum akses; demo memakai storage lokal terpisah. Pencadangan database dan berkas tenant mempunyai inventaris, retensi dan uji restore yang terpisah.
- Provisioning synchronous untuk demo; antrean/asynchronous ditunda sampai ada pengukuran yang menunjukkan kebutuhan. Tanpa Redis; mekanisme bawaan database cukup untuk slice awal.
- Monitor pusat menampilkan state layanan administratif, status setup, waktu pemeriksaan teknis, hasil probe/failure dan langkah pemulihan; tidak membaca tabel tenant. Semua perubahan operator dicatat append-only pada data pusat.

## Kapasitas dan staging

VPS yang terhubung mempunyai 2 vCPU, RAM 8 GiB dan disk 100 GiB; container aplikasi, PostgreSQL 16, Redis dan beberapa servis lain berjalan. Snapshot metrik Hostinger 7 hari (21–28 November 2026, 331 sampel) menunjukkan CPU 4,33–9,45% (rata-rata 4,68%), RAM 2,30–2,40 GiB (rata-rata 2,35 GiB), dan ruang disk terpakai sekitar 32,00–32,10 GiB. Ini metrik host agregat, bukan alokasi proyek atau beban puncak/pengguna; maka **kapasitas headroom dan kemampuan menambah v2 belum terbukti**. V2 memerlukan runtime PHP 8.3 terpisah; tidak boleh ditambahkan ke produksi sebelum beban diukur, demo database diuji, MFA operator aktif, restore teruji, dan persetujuan staging diperoleh. Tidak ada konfigurasi VPS, DNS, secret, atau deploy yang diubah.

## Sumber resmi yang ditinjau

- Laravel 13 release dan support policy: https://laravel.com/framework/docs/13.x/releases
- Laravel database support: https://laravel.com/docs/13.x/database
- Laravel 13 starter kits (Livewire 4/Tailwind): https://laravel.com/framework/docs/13.x/starter-kits
- Livewire 4 installation and layout: https://livewire.laravel.com/docs/4.x/installation
- Laravel Boost installation: https://laravel.com/framework/docs/boost
- Laravel AI SDK (ditinjau, tidak dipasang sebagai runtime): https://laravel.com/framework/docs/ai-sdk
- Filament 5 (dievaluasi sebagai opsi, bukan shell utama): https://filamentphp.com/docs/5.x/introduction/installation
- PostgreSQL 16 documentation: https://www.postgresql.org/docs/16/index.html
- PostgreSQL version support (5 tahun tiap major): https://www.postgresql.org/support/versioning/
- Django release/support schedule: https://www.djangoproject.com/download/
- Django admin: https://docs.djangoproject.com/en/5.2/ref/contrib/admin/
- NestJS docs: https://docs.nestjs.com/
- OWASP Multi-Tenant Security Cheat Sheet: https://cheatsheetseries.owasp.org/cheatsheets/Multi_Tenant_Security_Cheat_Sheet.html

## Ukuran keberhasilan yang masih harus dihasilkan

- Test otomatis: izin operator, penolakan admin tenant di panel pusat, pembuatan draf, domain unik, setup sukses/gagal/retry, undangan expiry/sekali pakai, login admin, batas dua tenant, status/modul/monitor/audit.
- Browser/manual: alur demo end-to-end; keyboard/fokus/kontras; lebar ponsel; teks/form; tabel tanpa potong; status/error jelas.
- Lab: LCP/INP/CLS serta p95 untuk daftar organisasi pada fixture sintetis. Catat lingkungan dan pisahkan dari target serta data pengguna nyata.
