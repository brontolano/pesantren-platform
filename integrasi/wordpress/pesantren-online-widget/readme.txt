=== Pesantren Online Widget ===
Contributors: pesantrenonline
Tags: pesantren, ppdb, widget, pendaftaran
Requires at least: 5.8
Tested up to: 6.7
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv2 or later

Tampilkan statistik, berita terbaru, dan tombol pendaftaran PPDB dari platform
Pesantren Online di situs WordPress pesantren Anda.

== Description ==

Plugin resmi platform Pesantren Online (https://pesantren.online).

* `[pesantren_widget]` — kartu lengkap: nama pesantren, jumlah santri aktif,
  3 berita terbaru, dan tombol "Daftar PPDB Online". Data diambil langsung
  dari platform (selalu mutakhir, cache 5 menit).
* `[pesantren_ppdb_button teks="Daftar Sekarang"]` — tombol PPDB saja.

Hanya data publik yang diambil; tidak ada kredensial yang disimpan.

== Installation ==

1. Unggah folder `pesantren-online-widget` ke `/wp-content/plugins/`
   (atau unggah ZIP via Plugins → Add New → Upload).
2. Aktifkan plugin.
3. Buka Settings → Pesantren Online, isi domain pesantren Anda
   (mis. `demo.pesantren.online`).
4. Sisipkan shortcode di halaman, post, atau widget teks.

== Changelog ==

= 1.0.0 =
* Rilis pertama: shortcode widget kartu + tombol PPDB, halaman pengaturan domain.
