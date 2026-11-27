# PRD — Perancangan Ulang Pesantren.online

| Atribut | Nilai |
|---|---|
| Produk | Pesantren.online — platform pengelolaan layanan digital untuk banyak pesantren |
| Versi | 0.1 — rancangan untuk validasi produk |
| Tanggal | 2026-11-27 |
| Status | Draf berbasis eksplorasi repo; perlu validasi pemilik produk dan pesantren uji coba |
| Pemilik produk | Hamdan / tim Pesantren.online |
| Cakupan | Situs pusat, ruang kerja superadmin, modul platform, ruang kerja tenant dan situs publik tenant |
| Baseline repo | `main` pada `cf87b257dc99663ea34714e85d340b3795de7960` saat eksplorasi |

> PRD ini menjadi dokumen produk baru yang fokus pada Pesantren.online. Dokumen ini melengkapi, bukan menggantikan, [`docs/PRD.md`](PRD.md), yang membahas platform operasional Asy-Syifaa. PRD ini menjelaskan kebutuhan dan hasil yang diinginkan. Keputusan teknis rinci dibuat setelah alur prioritas tervalidasi.

## 1. Ringkasan

Pesantren.online perlu berfungsi sebagai **layanan platform yang mudah dipahami dan dioperasikan oleh pesantren**, bukan sekadar kumpulan modul administrasi. Ada tiga permukaan produk yang harus terasa menyatu tetapi mempunyai tanggung jawab dan hak akses berbeda:

1. **Situs pusat Pesantren.online** untuk menjelaskan manfaat, membangun kepercayaan, menunjukkan cara kerja, dan mengarahkan pesantren ke konsultasi atau onboarding.
2. **Ruang kerja platform (superadmin)** untuk mengelola tenant, konfigurasi dasar dan domain, komunikasi resmi, dukungan, kondisi layanan, modul, serta kontribusi/donasi.
3. **Ruang kerja pesantren dan situs publik tenant** untuk menyiapkan identitas pesantren, memilih fitur yang dibutuhkan, mengelola konten publik, dan mengakses operasional sesuai hak akses.

Masalah produk yang hendak dipecahkan adalah **jarak antara kemampuan sistem yang besar dan kemampuan pesantren untuk memulai serta menjalankannya**. Pusat kendali yang hanya menyediakan banyak pengaturan membuat pesantren harus memahami struktur sistem sebelum memperoleh manfaat. Produk perlu memandu langkah berikutnya, memakai bahasa kerja pesantren, membuka kompleksitas secara bertahap, dan tetap memberi ruang bagi kebutuhan pesantren yang beragam.

Model utama pada PRD ini adalah **platform layanan dengan aktivasi bertahap**: tenant dapat mulai dari identitas dan situs publik, kemudian menyalakan modul saat kebutuhan serta kesiapan datanya sudah jelas. Semua permukaan wajib menjaga isolasi tenant dan menampilkan status serta tindakan berikutnya dengan jelas.

## 2. Bukti, inferensi, dan asumsi

### 2.1 Fakta yang diperiksa pada repo

| Fakta repo | Bukti yang diperiksa | Batas makna |
|---|---|---|
| Host pusat dan host tenant sudah dibedakan pada rute root; tenant tipe sistem menuju alur login, tipe lain menampilkan landing tenant. | [`routes/web.php`](../routes/web.php#L51-L64) | Ini fakta alur kode, bukan bukti pengalaman pengguna sudah sederhana atau konsisten. |
| Panel platform telah mendaftarkan resource tenant, invoice, aktivasi modul, dan halaman platform lain. | [`app/Providers/Filament/AdminPanelProvider.php`](../app/Providers/Filament/AdminPanelProvider.php#L55-L96) | Pendaftaran resource tidak membuktikan semua kebutuhan support/monitoring telah terpenuhi. |
| Pengelolaan tenant punya resource khusus. | [`app/Filament/Resources/Tenants/TenantResource.php`](../app/Filament/Resources/Tenants/TenantResource.php) dan [`app/Models/Tenant.php`](../app/Models/Tenant.php) | Struktur field perlu dipetakan kembali terhadap informasi tenant yang benar-benar dibutuhkan. |
| Modul dikelola melalui pusat modul, aktivasi tenant, service, dan konfigurasi tersendiri. | [`app/Filament/Pages/PusatModul.php`](../app/Filament/Pages/PusatModul.php), [`app/Filament/Pages/AktivasiModulTenant.php`](../app/Filament/Pages/AktivasiModulTenant.php), [`app/Services/ModuleService.php`](../app/Services/ModuleService.php), [`config/tenant_modules.php`](../config/tenant_modules.php) | Perlu validasi dengan pengguna apakah pembagian dan istilah modul masuk akal. |
| Tenant telah memiliki builder situs dan konten CMS seperti profil, artikel, galeri, serta komentar. | [`Modules/CMS/app/Filament/Pages/WebsiteBuilder.php`](../Modules/CMS/app/Filament/Pages/WebsiteBuilder.php), [`Modules/CMS/app/Http/Controllers/TenantLandingPageController.php`](../Modules/CMS/app/Http/Controllers/TenantLandingPageController.php), [`Modules/CMS/app/Services/TenantWebsiteService.php`](../Modules/CMS/app/Services/TenantWebsiteService.php) | Keberadaan CMS tidak menjamin editor mudah dipakai atau mendukung semua kebutuhan publik. |
| Sudah ada konsep integrasi situs eksternal dengan layanan platform melalui subdomain, embed, atau API. | [`docs/PLAN-INTEGRASI-WEBSITE.md`](PLAN-INTEGRASI-WEBSITE.md) | Dokumen itu berstatus perencanaan; jangan menganggap semua pola sudah tersedia di produksi. |
| Panduan onboarding yang ada melibatkan pembuatan tenant, pengaturan domain/modul, DNS, kredensial, branding, dan input data. | [`docs/PANDUAN-ONBOARDING-TENANT.md`](PANDUAN-ONBOARDING-TENANT.md) | Panduan adalah bukti beban langkah yang direncanakan, bukan pengukuran lama onboarding aktual. |
| Invoice platform dan pengaturan notifikasi tersedia sebagai permukaan yang berbeda dari pengumuman dukungan lintas tenant. | [`PlatformInvoiceResource.php`](../app/Filament/Resources/Billing/PlatformInvoiceResource.php), [`PengaturanNotifikasi.php`](../app/Filament/Pages/PengaturanNotifikasi.php) | Donasi dan pengumuman resmi membutuhkan model dan alur produk yang ditetapkan, bukan sekadar mengganti label. |

### 2.2 Temuan Graphify

Graphify memetakan 105 file kode terpilih, menghasilkan 1.519 node AST, 2.779 relasi mentah, dan 77 komunitas. Pemetaan mengindikasikan bahwa `Tenant`, `TenantWebsiteService`, `SetupPesantren`, `WebsiteBuilder`, `PusatModul`, `TenantResource`, serta `Setting` berada di antara simpul paling terhubung. Ini membantu menemukan area yang berpotensi menjadi titik temu ketika alur tenant dan situs publik dirancang ulang.

Temuan tersebut **bukan penilaian kualitas produk atau bukti dampak bisnis**. Pemeriksaan kesehatan mendapati 83 relasi dengan pasangan endpoint yang sama terlipat di graf pascapembuatan, 6 self-loop, dan tidak ada endpoint hilang. Karena itu, graf dipakai sebagai peta navigasi; kesimpulan tentang perilaku aktual diverifikasi lagi ke file sumber. Detail audit dan graf disimpan di [folder bukti eksplorasi](../.unlazy/prd-pesantren-online-20261127/corpus/graphify-out/GRAPH_REPORT.md).

### 2.3 Inferensi dan asumsi produk

- **Inferensi:** onboarding yang mengharuskan pesantren memahami tenant, paket, toggle modul, DNS, branding, dan data sejak awal kemungkinan menambah hambatan. Ini diturunkan dari urutan pada panduan, bukan hasil uji pengguna.
- **Asumsi untuk rancangan:** setiap pesantren memerlukan satu catatan identitas tenant yang menjadi sumber kebenaran bagi kontak, alamat, domain, status layanan, dan pemilik akun.
- **Asumsi untuk rancangan:** “invoice bersifat donasi” berarti permintaan kontribusi sukarela, bukan kewajiban pembayaran atas layanan. Sebutan, penerima dana, bukti penerimaan, tata kelola, dan aturan penghimpunan harus dikonfirmasi sebelum transaksi daring diluncurkan.
- **Belum diketahui:** apakah produk akan berbayar, gratis, berbasis donasi, atau memakai gabungan model; apakah ada masa layanan atau paket yang harus tetap dicatat meski donasi sukarela.
- **Belum diketahui:** definisi “monitoring aktif” yang diinginkan, sumber sinyalnya, siapa yang merespons, dan target waktunya.

## 3. Visi dan hasil produk

### Visi

Pesantren dapat memulai layanan digitalnya tanpa harus menjadi ahli sistem: pahami langkah awal, siapkan informasi pokok, tampilkan wajah pesantren, lalu aktifkan kemampuan tambahan sesuai kebutuhan dan kesiapan.

### Hasil yang diinginkan

| ID | Hasil | Pengukuran awal |
|---|---|---|
| O1 | Pesantren baru paham cara memulai dan mampu menyelesaikan setup dasar dengan bantuan sesedikit mungkin. | Waktu menuju situs/ruang tenant siap, persentase onboarding selesai, langkah yang memerlukan bantuan. Baseline dan target ditetapkan setelah uji coba. |
| O2 | Superadmin mengetahui keadaan tenant dan tindakan berikutnya dari satu tempat. | Waktu menemukan status tenant, kelengkapan profil, domain, pembaruan, dan kasus dukungan. Baseline/target TBD. |
| O3 | Tenant dapat mengelola situs publik tanpa mengubah konfigurasi teknis atau data tenant lain. | Tingkat keberhasilan tugas edit-preview-publish dan insiden akses lintas tenant. Baseline/target TBD. |
| O4 | Modul tersedia dengan penjelasan kegunaan, prasyarat, dan konsekuensi aktivasi yang dipahami. | Rasio aktivasi yang berhasil dan permintaan bantuan terkait modul. Baseline/target TBD. |
| O5 | Komunikasi dan kontribusi ke platform tercatat secara transparan. | Persentase pengumuman terkirim/terbaca; kelengkapan catatan kontribusi dan tanda terima. Target TBD setelah klasifikasi hukum/model diputuskan. |

## 4. Pengguna dan kebutuhan utama

| Pengguna | Pekerjaan yang ingin diselesaikan | Kebutuhan desain |
|---|---|---|
| Pengelola/pengasuh pesantren | Memastikan layanan sesuai identitas dan kebutuhan lembaga. | Ringkasan yang mudah dibaca, kontrol atas publikasi dan izin, keputusan penting ditampilkan dalam bahasa sederhana. |
| Admin/TU pesantren | Menyelesaikan setup, mengelola profil/konten, menemukan modul dan meminta bantuan. | Checklist yang dapat dilanjutkan, penyimpanan otomatis/draf, contoh pengisian, pesan kesalahan yang membantu. |
| Operator konten | Memperbarui artikel, galeri, pengumuman dan halaman situs. | Editor aman, preview nyata, alur draf-terbit, pemulihan kesalahan. |
| Superadmin platform | Mengelola tenant, layanan, modul, komunikasi dan dukungan. | Daftar kerja terpadu, filter status, tindakan tercatat dan pembatasan hak akses. |
| Tim support/teknis | Mendiagnosis masalah dan memberi tindak lanjut yang dapat dilacak. | Konteks tenant, riwayat kasus, prioritas, status, pemilik kasus, dan catatan internal yang tidak terlihat oleh tenant. |
| Pengunjung publik / wali / calon santri | Menemukan informasi resmi pesantren dan tindakan publik yang tersedia. | Situs ringan, responsif, informasi kontak jelas, aksesibilitas, batas publik dan privat yang tegas. |

## 5. Prinsip produk dan pengalaman

1. **Mulai dari pekerjaan, bukan daftar menu.** Beranda tenant menunjukkan langkah berikutnya, status setup, dan pekerjaan yang tertunda.
2. **Bertahap, bukan membebani.** Setup awal meminta informasi minimum. Pilihan lanjutan muncul saat relevan.
3. **Bahasa operasional pesantren.** Pakai istilah yang dipahami pengelola; istilah teknis disertai penjelasan dan tidak menjadi hambatan.
4. **Aman secara bawaan.** Tenant hanya melihat data dan tindakan yang menjadi haknya; akses ditolak jika tidak secara eksplisit diizinkan. Izin diperiksa pada tiap permintaan dan tindakan sensitif dicatat, sejalan dengan panduan OWASP.
5. **Jelas sebelum terbit atau mengubah layanan.** Preview, konfirmasi, dampak perubahan, serta pemulihan ditampilkan sebelum tindakan berisiko.
6. **Nyaman di ponsel dan jaringan terbatas.** Tugas umum singkat, halaman ringan, data penting punya status yang dapat dipahami tanpa grafik berat.
7. **Situs dan sistem login dipisahkan secara konseptual.** Pengunjung melihat situs publik; staf masuk ke ruang sistem yang aman. Domain dan integrasi eksternal mengikuti verifikasi kepemilikan serta aturan tenant.

## 6. Arsitektur pengalaman produk

### A. Situs pusat Pesantren.online

Tujuan: membuat produk mudah dipahami dan membuka jalur awal yang jelas. Halaman minimum: Beranda, Cara Kerja, Fitur/Modul, Untuk Siapa, Keamanan dan privasi, Bantuan/FAQ, Hubungi/ajukan demo, serta status layanan jika produk memilih mempublikasikannya.

Ajakan utama harus tunggal dan konsisten, misalnya “Konsultasikan kebutuhan pesantren” atau “Mulai setup”. Situs tidak boleh mengklaim fitur, harga, kepatuhan, uptime, atau kisah pelanggan yang belum terverifikasi.

### B. Ruang kerja superadmin

Navigasi dikelompokkan menurut pekerjaan:

- **Ringkasan layanan:** tenant memerlukan tindakan, status domain, setup, insiden, dan perubahan penting.
- **Pesantren/tenant:** identitas, alamat, kontak, domain, status layanan, pengelola utama, modul, dan riwayat perubahan.
- **Komunikasi:** pengumuman umum atau sasaran tenant, pembaruan teknis, pemeliharaan, status terkirim/terbaca, arsip.
- **Dukungan:** tiket/kasus, prioritas, penanggung jawab, percakapan tenant dan catatan teknis internal.
- **Modul:** katalog, status kesiapan, dokumentasi sederhana, prasyarat, aktivasi, dan ringkasan tenant pengguna modul.
- **Kontribusi:** permintaan kontribusi sukarela, pencatatan, rekonsiliasi bila relevan, dan tanda terima; terpisah dari tagihan kewajiban layanan.
- **Audit & pengaturan:** akun staf, izin, log tindakan, kanal notifikasi dan preferensi operasional.

### C. Ruang kerja tenant dan situs publiknya

Beranda tenant memakai checklist ringkas: lengkapi identitas, tentukan domain/alamat, siapkan situs, cek tampilan, publikasikan. Setelahnya, tampilkan kartu modul yang relevan dengan status dan penjelasan manfaat. Website publik memiliki editor/preview terpisah dari konten operasional; akun publik tidak boleh mengubah pengaturan platform.

## 7. Ruang lingkup dan persyaratan fungsional

Prioritas: **P0** = wajib untuk fondasi/versi pertama; **P1** = setelah alur dasar tervalidasi; **P2** = pengembangan lanjut. Setiap requirement memiliki kriteria penerimaan yang dapat diuji.

### 7.1 Situs pusat

| ID | Prioritas | Persyaratan | Kriteria penerimaan |
|---|---|---|---|
| WEB-01 | P0 | Menjelaskan masalah, manfaat, alur memulai, modul, dan cara menghubungi platform. | Pengunjung baru dapat menjawab “apa ini”, “cocok untuk siapa”, dan “langkah pertama apa” dari halaman utama tanpa login. |
| WEB-02 | P0 | Menyediakan jalur kontak/konsultasi dengan konteks kebutuhan pesantren. | Formulir meminta data minimum, menampilkan konfirmasi terkirim, mencegah pengiriman berulang yang tidak disengaja, dan mengarahkan ke kanal tindak lanjut yang benar. |
| WEB-03 | P1 | Menampilkan informasi layanan, perubahan, dan status gangguan yang telah disetujui untuk publik. | Informasi memiliki tanggal/status dan dapat diarsipkan; konten internal tidak muncul di situs pusat. |
| WEB-04 | P0 | Mendukung tampilan responsif, navigasi keyboard, label yang jelas, dan ukuran target interaksi sekurangnya sesuai WCAG 2.2 AA. | Audit tugas inti di lebar ponsel dan desktop; kontrol pointer memenuhi WCAG 2.2 SC 2.5.8 (24×24 CSS px dengan pengecualian yang berlaku). |

### 7.2 Tenant dan onboarding superadmin

| ID | Prioritas | Persyaratan | Kriteria penerimaan |
|---|---|---|---|
| TEN-01 | P0 | Superadmin dapat membuat dan memperbarui identitas tenant: nama resmi/publik, pengenal, status, penanggung jawab, dan kontak utama. | Field wajib/opsional jelas; validasi mencegah identifier bentrok; perubahan menampilkan konfirmasi dan tercatat dalam audit. |
| TEN-02 | P0 | Superadmin dapat mengelola alamat tenant dengan tujuan alamat yang jelas (korespondensi/lokasi utama bila perlu). | Alamat dapat disimpan/diubah; format dan label tidak ambigu; tenant lain tidak dapat membaca atau mengubahnya. |
| TEN-03 | P0 | Domain situs publik dan domain sistem/login dibedakan dan status verifikasinya terlihat. | UI menampilkan nilai, status verifikasi, instruksi berikutnya, dan peringatan jika DNS/sertifikat belum siap; perubahan domain dicatat. |
| TEN-04 | P0 | Onboarding berbentuk tahapan dengan indikator selesai, simpan dan lanjutkan, penanggung jawab, serta bantuan kontekstual. | Pengguna bisa meninggalkan setup lalu kembali tanpa kehilangan data yang sudah tersimpan; langkah yang belum perlu tidak menghalangi publikasi tahap awal. |
| TEN-05 | P1 | Ringkasan tenant menyatukan profil, domain, modul, aktivitas, dukungan, notifikasi penting dan riwayat. | Superadmin dapat menemukan status utama tenant tanpa berpindah ke banyak resource; setiap status mengarah ke tindak lanjut terkait. |
| TEN-06 | P1 | Sistem memberi status aktivitas tenant yang dapat dijelaskan dan tidak memberi kesan pengawasan atas isi data santri. | Setiap indikator punya definisi, sumber, waktu pembaruan, dan batas interpretasi; sinyal sensitif tidak ditampilkan tanpa kebutuhan dan hak akses. |

### 7.3 Komunikasi dan informasi teknis

| ID | Prioritas | Persyaratan | Kriteria penerimaan |
|---|---|---|---|
| COM-01 | P0 | Superadmin dapat membuat pengumuman dengan audiens, judul, isi, kategori, waktu berlaku, dan tingkat urgensi. | Sebelum kirim, pratinjau menampilkan target dan isi; penerima yang salah dapat dicegah lewat ringkasan konfirmasi. |
| COM-02 | P0 | Tenant memiliki kotak masuk notifikasi dan arsip pesan platform. | Pesan berstatus baru/dibaca/diarsip; pengumuman mendesak tetap ditemukan; pengguna tenant hanya melihat pesan untuk tenantnya. |
| COM-03 | P1 | Pesan teknis memisahkan tindakan yang perlu dilakukan tenant dari catatan internal platform. | Pesan tenant tidak menampilkan catatan internal; pesan berisi dampak, waktu, langkah yang perlu dilakukan, dan kanal bantuan jika relevan. |
| COM-04 | P1 | Sistem mencatat status pengiriman dan pembacaan per kanal bila kanal mendukungnya. | Superadmin dapat membedakan dibuat, terantre, terkirim, gagal, dan dibaca; kegagalan dapat dicoba ulang dengan aman. |

### 7.4 Dukungan, teknis, dan monitoring

| ID | Prioritas | Persyaratan | Kriteria penerimaan |
|---|---|---|---|
| SUP-01 | P0 | Tenant dapat membuka kasus dukungan dengan kategori, ringkasan, dampak, uraian, dan lampiran yang dibatasi aman. | Nomor kasus dan status tampil setelah pengiriman; tenant dapat melihat percakapan dan pembaruan kasusnya saja. |
| SUP-02 | P0 | Tim platform dapat menugaskan, mengategorikan, memprioritaskan, memberi status, dan mencatat penyelesaian kasus. | Perubahan status memiliki waktu dan pelaku; catatan internal dibedakan jelas dari balasan yang dilihat tenant. |
| SUP-03 | P1 | Ringkasan operasional menunjukkan sinyal kesehatan layanan yang relevan: ketersediaan endpoint, antrean/notifikasi gagal, domain atau sertifikat bermasalah, serta tenant perlu tindak lanjut. | Setiap sinyal menyertakan sumber, waktu terakhir diperbarui, status, dan tindakan; tidak menyebut “tenant aktif” berdasarkan aktivitas santri kecuali ada dasar kebutuhan, persetujuan, dan kebijakan privasi. |
| SUP-04 | P1 | Peristiwa teknis yang berdampak pada tenant dapat dihubungkan ke insiden, pengumuman, dan kasus dukungan. | Operator dapat melihat tenant terdampak dan status komunikasi/tindak lanjut pada satu insiden tanpa mengekspos data tenant lain. |
| SUP-05 | P2 | Menetapkan target respons/penyelesaian setelah kapasitas support dan kelas prioritas disepakati. | SLA hanya ditampilkan setelah ada definisi jam layanan, kalender, prioritas, pengecualian, dan sumber daya penanggung jawab yang disetujui. |

### 7.5 Modul dan aktivasi

| ID | Prioritas | Persyaratan | Kriteria penerimaan |
|---|---|---|---|
| MOD-01 | P0 | Katalog menjelaskan fungsi modul dengan istilah pesantren, siapa pengguna utamanya, data/prasyarat, dan kesiapan. | Pengguna dapat membedakan modul yang tersedia, perlu persiapan, aktif, dan belum didukung; label tidak hanya berupa nama teknis. |
| MOD-02 | P0 | Admin tenant dapat meminta atau mengaktifkan modul yang diizinkan kebijakan platform. | Dampak dan prasyarat ditampilkan sebelum tindakan; sistem mencegah aktivasi jika persyaratan wajib belum ada serta menerangkan alasannya. |
| MOD-03 | P1 | Ada rekomendasi urutan aktivasi berdasar kesiapan, bukan kewajiban mengaktifkan semua modul ketika setup awal. | Pesantren dapat menyelesaikan setup dasar dan situs publik walau modul operasional lain belum diaktifkan. |
| MOD-04 | P1 | Superadmin mengelola status katalog, dependensi, versi/ketersediaan, dan tenant yang terdampak. | Perubahan status modul menghasilkan daftar tenant terdampak dan memerlukan konfirmasi/audit sebelum diterapkan. |
| MOD-05 | P2 | Integrasi modul dengan situs eksternal melalui subdomain, embed, atau API disediakan per kebutuhan dan tahap. | Token, domain yang diizinkan, batas laju, hak akses tenant, dan dokumentasi telah ditetapkan serta diperiksa sebelum integrasi dibuka. |

### 7.6 Situs publik tenant / CMS

| ID | Prioritas | Persyaratan | Kriteria penerimaan |
|---|---|---|---|
| CMS-01 | P0 | Admin tenant dapat mengelola profil dasar: nama, logo, alamat publik, kontak, deskripsi, dan tautan resmi. | Pengguna melihat hasil preview sebelum terbit; data privat tidak otomatis ditampilkan pada halaman publik. |
| CMS-02 | P0 | Admin dapat membuat/mengubah halaman dan konten publik melalui draf, preview, dan publikasi. | Perubahan draf tidak langsung terlihat publik; publikasi menunjukkan ringkasan perubahan dan dapat dipulihkan ke versi sebelumnya atau dibatalkan lewat tindakan yang jelas. |
| CMS-03 | P0 | Halaman publik responsif, cepat dimuat, aman, memiliki metadata dasar, dan navigasi mudah. | Halaman dapat diakses tanpa login; rute admin tetap terlindungi; konten antartenant tidak tertukar. |
| CMS-04 | P1 | CMS menyediakan blok/templat yang menjaga konsistensi visual tanpa meminta pengguna memahami desain web. | Pengguna dapat mengganti urutan/isi blok yang diizinkan, melihat hasil langsung, dan kembali ke templat awal. |
| CMS-05 | P1 | Persetujuan komentar atau konten kiriman publik dapat diatur tenant. | Konten pengunjung tidak otomatis tampil bila moderasi aktif; penanggung jawab dan status moderasi terlihat. |
| CMS-06 | P2 | Tenant dapat memakai situs eksternal dan menyambungkan modul melalui metode integrasi yang disetujui. | Sistem memisahkan kepemilikan konten, domain, kredensial API, dan kewenangan publikasi; perubahan dicatat dan dapat dicabut. |

### 7.7 Donasi/kontribusi platform

| ID | Prioritas | Persyaratan | Kriteria penerimaan |
|---|---|---|---|
| DON-01 | P0 | Sistem memisahkan kontribusi sukarela dari kewajiban pembayaran layanan, paket, atau kontrak. | Tidak ada status tenant yang otomatis turun/nonaktif karena belum berdonasi; halaman dan pesan secara eksplisit menyatakan sifat sukarela bila itulah model yang diputuskan. |
| DON-02 | P1 | Permintaan kontribusi menyebut tujuan, penerima, kanal resmi, tanggal, serta petunjuk dan tata kelola penggunaan. | Tenant dapat memastikan tujuan dan penerima sebelum mengirim; platform menyediakan arsip dan bukti pencatatan. |
| DON-03 | P1 | Setiap kontribusi memiliki catatan nominal/tanggal/metode/referensi, status verifikasi, dan tanda terima sesuai kebijakan. | Pembuat, pemeriksa, perubahan, dan pengembalian (bila ada) tercatat; data tidak dapat diedit tanpa jejak. |
| DON-04 | P0 | Sebelum penggalangan atau pembayaran elektronik diaktifkan, klasifikasi hukum, pihak penerima, izin yang berlaku, pencatatan dan komunikasi publik ditinjau. | Keputusan terdokumentasi dan disetujui pemilik produk serta penasihat yang kompeten; status fitur tetap nonaktif sampai keputusan tersebut ada. |

## 8. Persyaratan nonfungsional

### Keamanan dan isolasi tenant

- Hak akses platform, staf support, admin tenant, editor konten, dan publik harus dibedakan menurut tugas dan data yang diperlukan.
- Akses memakai prinsip **deny by default** dan **least privilege**; semua permintaan ke data tenant menjalani pemeriksaan izin, bukan hanya penyembunyian tombol.
- Data tenant terikat pada konteks tenant yang terverifikasi di server. Pencarian, ekspor, lampiran, notifikasi, cache, dan pekerjaan latar belakang mengikuti batas yang sama.
- Tindakan sensitif (ubah domain/status/modul, mengirim pengumuman, akses dukungan, verifikasi kontribusi) menyimpan siapa, kapan, perubahan apa, dan konteks yang cukup.
- Rahasia, token, data kontak nonpublik, atau data santri tidak boleh masuk ke log atau materi publik tanpa kebutuhan dan kebijakan yang disetujui.

### Privasi dan data santri

Produk perlu menentukan peran pengendali/pemroses data dan tujuan pemrosesan per fitur. UU 27/2022 menyatakan pemrosesan Data Pribadi anak diselenggarakan secara khusus dan mensyaratkan persetujuan orang tua dan/atau wali; persyaratan implementasi perlu ditelaah bersama penasihat hukum, termasuk bagaimana penerapannya pada beragam fitur platform. Pengumpulan data harus dibatasi pada tujuan yang dijelaskan, memiliki pengaturan retensi dan hak subjek data, serta menjelaskan kanal permintaan koreksi/penghapusan sesuai kebijakan dan aturan yang berlaku.

### Aksesibilitas dan kemudahan

- Target pengalaman produk adalah WCAG 2.2 AA untuk alur inti. Kriteria target interaksi pointer minimal SC 2.5.8: 24×24 CSS px dengan pengecualian sesuai standar; 44×44 px adalah kriteria terpisah tingkat AAA.
- Semua tugas umum dapat dilakukan tanpa drag-only, memiliki fokus keyboard yang terlihat, label form, kontras memadai, serta pesan validasi yang terhubung ke field.
- Hindari dasbor yang bergantung pada warna saja atau visualisasi rumit. Status disertai teks dan tindakan.
- Uji tugas dengan operator pesantren memakai ponsel, termasuk kondisi jaringan lemah dan kemampuan digital yang beragam.

### Kinerja, keandalan, dan operasi

- Target kinerja, ketersediaan, pencadangan, pemulihan, dan masa retensi ditetapkan setelah kapasitas host serta kebutuhan pemulihan disurvei. Jangan mengiklankan SLA sebelum disepakati dan dibuktikan.
- Operasi kritis (publikasi situs, perubahan domain, pengiriman pengumuman, dan perubahan modul) harus memberi status sukses/gagal yang dapat dipahami dan tidak menggandakan dampak ketika dicoba ulang.
- Ekspor atau impor data yang memengaruhi banyak santri memerlukan validasi, pratinjau jumlah/perubahan, pencadangan atau rencana pemulihan, serta pemilik keputusan.

## 9. Batas lingkup

### Termasuk dalam PRD

- Situs utama Pesantren.online.
- Pengelolaan identitas, alamat, kontak, domain, status, komunikasi, support, monitoring layanan, dan modul tenant.
- CMS situs publik tenant dan alur draf-preview-publikasi.
- Pemisahan konsep dan izin antara platform pusat dan tenant.
- Pemisahan catatan kontribusi sukarela dari tagihan layanan.

### Tidak otomatis termasuk dalam tahap pertama

- Penulisan ulang seluruh modul operasional pesantren (akademik, PPDB, keuangan santri, wali, dan sebagainya).
- Payment gateway, penggalangan publik, klaim keringanan pajak, atau penetapan izin donasi sebelum klasifikasi hukum disetujui.
- Pemantauan aktivitas santri atau isi data operasional sebagai ukuran “tenant aktif”.
- Integrasi otomatis dengan semua website pihak ketiga atau migrasi seluruh tenant tanpa inventaris dan rencana cutover.
- Klaim SLA, kepatuhan sertifikasi, maupun target bisnis numerik tanpa baseline.

## 10. Tahapan produk

| Tahap | Fokus | Keluar jika |
|---|---|---|
| 0. Validasi kebutuhan | Wawancara pengasuh, admin/TU, operator konten, superadmin dan support; pilih 2–3 tugas prioritas; sepakati model donasi dan definisi tenant aktif. | Alur utama dipetakan, istilah dipahami, risiko hukum/operasi tercatat, dan baseline pengukuran tersedia. |
| 1. Fondasi pengalaman | Situs pusat, ringkasan tenant, profil/alamat/kontak/domain, checklist onboarding, sistem izin dan audit untuk perubahan inti. | Tenant uji dapat menyelesaikan setup dasar dan admin memahami status serta langkah berikutnya. |
| 2. Situs tenant | Profil publik, editor dasar, draf-preview-terbit, domain situs, bantuan kontekstual dan panduan. | Tenant uji mempublikasikan serta mengubah situs tanpa bantuan langsung pada tugas standar. |
| 3. Layanan platform | Kotak masuk pengumuman, kasus support, status teknis, katalog dan aktivasi modul bertahap. | Tenant menerima komunikasi yang tepat, support dapat menutup kasus secara terlacak, aktivasi dapat dijelaskan. |
| 4. Kontribusi dan skala | Alur kontribusi sukarela setelah tinjauan hukum/keuangan; monitoring dan integrasi tambahan berdasarkan data penggunaan. | Penerima, tata kelola, tanda terima, audit, penanganan komplain dan biaya operasi telah disepakati. |

## 11. Metrik dan evaluasi

Semua target angka tetap **TBD** hingga baseline diambil. Hindari mengoptimalkan jumlah modul aktif jika hal itu membuat pesantren mengaktifkan fitur yang belum siap.

- **Keberhasilan setup:** persentase pesantren uji yang menyelesaikan profil, alamat/kontak, dan situs awal.
- **Waktu menuju manfaat:** waktu dari akun dibuat sampai situs tenant dapat dilihat publik dan informasi kontak terverifikasi.
- **Beban bantuan:** tiket atau intervensi staf yang diperlukan per onboarding dan per tugas edit konten.
- **Keberhasilan tugas:** tingkat keberhasilan tanpa bantuan untuk mengubah kontak, membuat draf, preview dan publikasi.
- **Kejelasan modul:** apakah admin mampu menjelaskan fungsi, prasyarat, dan dampak modul sebelum mengaktifkannya.
- **Kualitas dukungan:** waktu respons pertama dan waktu penyelesaian per prioritas, dihitung hanya setelah jam layanan dan kapasitas disetujui.
- **Keandalan komunikasi:** pengumuman terkirim, gagal, dibaca; persentase komunikasi penting yang memiliki tindakan/konteks lengkap.
- **Keamanan:** insiden akses lintas tenant, akses tanpa izin, perubahan tanpa audit, dan waktu perbaikan.
- **Kualitas publikasi:** validasi tautan, kontak, performa halaman, aksesibilitas tugas inti, serta jumlah pembatalan/pemulihan publikasi.
- **Kontribusi:** jumlah dan nominal hanya dipantau bila model donasi disetujui; jangan digunakan sebagai ukuran kelayakan atau loyalitas tenant tanpa persetujuan eksplisit.

## 12. Risiko dan mitigasi

| Risiko | Dampak | Mitigasi produk |
|---|---|---|
| Kompleksitas lama dipindahkan ke UI baru tanpa mengubah alur kerja. | Antarmuka lebih rapi, tetapi onboarding tetap terasa berat. | Uji prototype dan tugas nyata; ukur intervensi bantuan, bukan hanya kepuasan umum. |
| “Donasi” dipakai sebagai nama invoice wajib. | Kebingungan, hilang kepercayaan, potensi masalah hukum/akuntansi. | Pisahkan model dan tampilan dari kewajiban langganan; tinjauan penasihat dan keputusan penerima dana sebelum transaksi. |
| Indikator monitoring disalahartikan sebagai kondisi pesantren. | Pelabelan tenant keliru atau rasa diawasi. | Definisikan metrik layanan dan sumber; tampilkan waktu serta batas interpretasi; hindari data santri sebagai sinyal rutin. |
| Kebocoran data lintas tenant melalui resource, API, ekspor, cache atau notifikasi. | Dampak privasi dan kepercayaan serius. | Otorisasi server-side menyeluruh, isolasi tenant, audit, dan pengujian negatif pada setiap jalur data. |
| Pemrosesan data anak tanpa dasar/izin yang jelas. | Risiko privasi, kepatuhan, dan kepercayaan wali. | Minimalisasi data, tujuan transparan, persetujuan orang tua/wali sesuai aturan, retensi, dan tinjauan hukum. |
| Domain/DNS rumit bagi staf pesantren. | Tenant terhambat sebelum situs tampil. | Jalur subdomain otomatis sebagai default; status verifikasi dan langkah DNS sederhana; bantuan support saat gagal. |
| Modul diaktifkan sebelum data dan petugas siap. | Sistem tampak gagal meski masalahnya kesiapan proses. | Prasyarat, checklist kesiapan, aktivasi bertahap, dan contoh data aman. |
| Dukungan memiliki ekspektasi waktu yang tidak didukung kapasitas. | Komplain dan beban tim. | Publikasikan jam layanan dan SLA hanya setelah kapasitas, hari libur, prioritas, serta eskalasi disetujui. |
| Perubahan situs tenant langsung terlihat tanpa pemeriksaan. | Kesalahan publikasi atau informasi keliru. | Draf, preview, konfirmasi ringkasan, audit revisi, dan pemulihan versi. |

## 13. Catatan implementasi redesign

Irisan implementasi awal dan kelanjutan pada branch redesign memperbaiki beranda platform, ringkasan superadmin, mode sederhana tenant, informasi kontak internal tenant, penemuan menu website, serta katalog modul. Pengumuman pusat dan permintaan bantuan kini mempunyai rancangan UI dan model data tersendiri: pengumuman dapat ditujukan ke semua tenant atau satu tenant; pesan dicatat sebagai dibaca/diarsipkan per staf; tiket menampilkan status, dampak, percakapan, dan catatan internal yang disaring dari tampilan tenant. Pemeriksaan kesiapan teknis menggunakan `TenantHealthCheck` yang sudah ada dan dijalankan atas permintaan operator; ini bukan monitoring kontinu atau metrik keaktifan pengguna.

Tagihan saat ini tetap dicatat sebagai tagihan layanan/langganan. Pencatatan kontribusi platform dan pembayaran daring belum diaktifkan karena penerima, tata kelola, klasifikasi hukum, serta alur tanda terimanya belum diputuskan. Builder website bawaan menyimpan bagian halaman, kontak, dan SEO sebagai draf; pratinjau memakai tautan bertanda tangan 10 menit, dibatasi ke tenant yang sama, dan mengirim `noindex,nofollow`; tombol Terbitkan memerlukan konfirmasi, sedangkan Buang Draf tidak mengubah versi publik. Editor identitas untuk situs eksternal masih menerapkan perubahan secara langsung. SLA support, lampiran tiket, pengiriman pengumuman lewat email/WhatsApp, riwayat versi/pemulihan konten setelah terbit, dan pencatatan hasil pengiriman belum menjadi kemampuan pada irisan ini.

Migrasi komunikasi dan dukungan ditambahkan ke repo, tetapi belum dijalankan pada database mana pun. Perubahan ini belum dideploy ke Hostinger dan perlu diperiksa dengan PHP/vendor proyek serta tenant uji sebelum rilis. Keberadaan kode di branch bukan bukti fitur telah berjalan di lingkungan produksi.

## 14. Pertanyaan keputusan terbuka

1. Apa kategori pelanggan utama: pesantren kecil tanpa staf IT, pesantren menengah, atau tim yayasan dengan beberapa unit? Siapa tenant uji yang bersedia ikut desain?
2. Siapa yang membuat tenant dan menyelesaikan onboarding: superadmin platform, pengasuh, admin pesantren, atau alur undangan bertahap?
3. Apakah pesantren.online akan memiliki paket/biaya layanan? Jika ya, bagaimana hubungan paket dengan kontribusi sukarela dan status layanan?
4. Siapa penerima kontribusi: operator platform, yayasan, atau pesantren? Apakah fitur dimaksudkan untuk kontribusi ke platform atau sumbangan publik untuk pesantren? Kedua tujuan ini harus berbeda.
5. Apakah pembayaran/donasi dilakukan di dalam platform, dicatat manual, atau keduanya? Siapa yang memverifikasi dan menerbitkan tanda terima?
6. Apa arti “monitoring aktif”: domain/SSL dan kesehatan aplikasi, progres setup, respons notifikasi, atau pemakaian fitur oleh admin? Sinyal mana yang dilarang?
7. Kanal apa yang benar-benar tersedia untuk notifikasi tenant (in-app, email, WhatsApp) dan bagaimana persetujuan, biaya, serta bukti pengiriman dikelola?
8. Bagaimana situs publik dan sistem login dipetakan ke domain/subdomain tenant, terutama saat tenant sudah punya situs eksternal?
9. Data apa yang boleh dilihat staf support tanpa persetujuan tenant, dan proses akses darurat seperti apa yang dapat diaudit?
10. Apa target jam layanan support, siapa petugasnya, dan bagaimana eskalasi insiden kritis?
11. Modul mana yang paling sering dibutuhkan lebih dulu? Apakah onboarding awal sebaiknya hanya identitas + website, dengan modul lain muncul setelah konsultasi?
12. Apakah tenant boleh menghapus/mengekspor situs dan data saat berhenti memakai layanan? Bagaimana format, retensi cadangan, serta masa transisinya?

## 14. Rencana validasi sebelum implementasi

1. Wawancarai setidaknya pengasuh/pimpinan, admin/TU, pengelola konten, superadmin, dan staf support dari pesantren yang berbeda kesiapan digitalnya.
2. Minta peserta menyelesaikan tugas konkret: melengkapi data dasar, menyiapkan situs, menemukan cara meminta bantuan, dan menentukan apakah mereka paham fungsi sebuah modul.
3. Catat waktu, titik bingung, pertanyaan yang berulang, tugas yang memerlukan bantuan, dan istilah yang tidak dipahami.
4. Uji prototype navigasi dengan satu tenant kecil dan satu tenant yang lebih kompleks; jangan menganggap satu pesantren mewakili seluruh pengguna.
5. Tinjau model kontribusi, privasi data anak, peran pemroses/pengendali, serta pengumpulan uang/barang dengan penasihat yang kompeten sebelum keputusan implementasi.
6. Setelah keputusan produk disetujui, turunkan persyaratan P0 menjadi desain alur, model data, izin, dan rencana migrasi terpisah sebelum perubahan produksi.

## 15. Riset dan sumber

### Riset adopsi sistem pesantren

- Studi E-Santren (2024), mixed-method dengan kuesioner dan focus group, melaporkan persepsi peserta tentang efisiensi administrasi, akurasi, pemantauan, dan kepatuhan. Abstrak yang tersedia tidak mencantumkan ukuran sampel; hasil dipakai sebagai indikasi untuk diuji, bukan generalisasi. [Rekam ilmiah ITS](https://scholar.its.ac.id/en/publications/enhancing-administrative-efficiency-in-pondok-pesantren-exploring/)
- Studi kasus SISANTRI (tesis, 2025) di satu pesantren mengidentifikasi komitmen pimpinan, sosialisasi/pelatihan, kesiapan infrastruktur, dan evaluasi berkelanjutan sebagai faktor adopsi; migrasi data, keterbatasan infrastruktur, dan ketidakbiasaan staf/wali sebagai hambatan. Ini hanya satu studi kasus. [Repositori UIMSYA](https://repository.uimsya.ac.id/id/eprint/1651/)

### Aksesibilitas, otorisasi, privasi, dan kontribusi

- W3C WCAG 2.2 menetapkan target pointer minimum 24×24 CSS px pada SC 2.5.8 dengan pengecualian tertentu; 44×44 px adalah kriteria enhanced tingkat AAA yang berbeda. [Rekomendasi WCAG 2.2](https://www.w3.org/TR/WCAG22/)
- OWASP menganjurkan default deny, validasi izin pada setiap permintaan, dan least privilege. Ini menjadi dasar persyaratan produk; implementasi tetap harus dirancang sesuai model multi-tenant repo. [OWASP Authorization Cheat Sheet](https://cheatsheetseries.owasp.org/cheatsheets/Authorization_Cheat_Sheet.html)
- UU 27/2022 tentang Pelindungan Data Pribadi, Pasal 25, mengatur perlakuan khusus terhadap pemrosesan data anak dan persetujuan orang tua/wali. Pasal lain mengatur tujuan, dasar, transparansi, hak dan kewajiban; penentuan penerapan terhadap tiap fitur memerlukan tinjauan hukum. [JDIH Komdigi — UU 27/2022](https://jdih.komdigi.go.id/produk_hukum/view/id/832/t/undangundang%2Bnomor%2B27%2Btahun%2B2022)
- Permensos 8/2024 berstatus berlaku dan mengubah beberapa ketentuan Permensos 8/2021 mengenai penyelenggaraan pengumpulan uang atau barang. Sumber ini menandai perlunya tinjauan sebelum membuat alur pengumpulan; PRD tidak menyimpulkan izin tertentu otomatis berlaku untuk semua bentuk kontribusi. [JDIH BPK — Permensos 8/2024](https://peraturan.bpk.go.id/Details/311984/permensos-no-8-tahun-2024)
- PP 29/1980 tercatat pada basis data peraturan BPK sebagai pelaksanaan UU pengumpulan sumbangan. Relevansi dan aturan mutakhir untuk model yang dipilih harus ditelaah oleh penasihat hukum. [JDIH BPK — PP 29/1980](https://peraturan.bpk.go.id/Details/66625/pp-no-29-tahun-1980)

---

## Lampiran A — Peta sumber lokal

- Struktur domain dan route publik: [`routes/web.php`](../routes/web.php#L51-L64)
- Resource panel platform: [`app/Providers/Filament/AdminPanelProvider.php`](../app/Providers/Filament/AdminPanelProvider.php#L55-L96)
- Model dan resource tenant: [`Tenant.php`](../app/Models/Tenant.php), [`TenantResource.php`](../app/Filament/Resources/Tenants/TenantResource.php)
- Pengaturan modul platform: [`PusatModul.php`](../app/Filament/Pages/PusatModul.php), [`AktivasiModulTenant.php`](../app/Filament/Pages/AktivasiModulTenant.php), [`ModuleService.php`](../app/Services/ModuleService.php), [`tenant_modules.php`](../config/tenant_modules.php)
- Website tenant: [`WebsiteBuilder.php`](../Modules/CMS/app/Filament/Pages/WebsiteBuilder.php), [`TenantLandingPageController.php`](../Modules/CMS/app/Http/Controllers/TenantLandingPageController.php), [`TenantWebsiteService.php`](../Modules/CMS/app/Services/TenantWebsiteService.php)
- Dokumen konteks onboarding dan integrasi: [`PANDUAN-ONBOARDING-TENANT.md`](PANDUAN-ONBOARDING-TENANT.md), [`PLAN-INTEGRASI-WEBSITE.md`](PLAN-INTEGRASI-WEBSITE.md), [`SLA-SUPPORT-TIER.md`](SLA-SUPPORT-TIER.md)
- Peta Graphify terkurasi: [GRAPH_REPORT.md](../.unlazy/prd-pesantren-online-20261127/corpus/graphify-out/GRAPH_REPORT.md) dan [graph.html](../.unlazy/prd-pesantren-online-20261127/corpus/graphify-out/graph.html)

## Lampiran B — Ketentuan makna istilah

- **Tenant:** satu organisasi pesantren/yayasan yang ruang data dan penggunanya dibatasi.
- **Ruang kerja platform:** area superadmin/internal Pesantren.online.
- **Ruang kerja tenant:** area staf pesantren untuk mengelola konfigurasi yang diizinkan, modul, dan situs publik.
- **Situs publik tenant:** halaman yang dapat dibuka tanpa login dan dikelola tenant.
- **Kontribusi/donasi platform:** dukungan sukarela kepada penerima yang harus disebutkan; bukan invoice kewajiban layanan.
- **Monitoring layanan:** status teknis yang dapat diverifikasi seperti domain, aplikasi, antrean, atau insiden. Istilah “aktif” harus didefinisikan terpisah sebelum dipakai sebagai label tenant.
