# Riset UI/UX SaaS → Roadmap pesantren.online (2026-06-11)

Sumber: riset 6 arah (navigasi/IA, onboarding, visual/mobile, UX ERP-sekolah,
kapabilitas Filament v5, audit panel saat ini) + benchmark Linear, Stripe,
Notion, QuickBooks, Xero, Odoo, ERPNext, ePesantren, MASANTREN, APPSO.

## Visi
Pondasi panel sudah kuat (tema hijau matang, dashboard 7 widget, Mode Sederhana).
Pekerjaan UI/UX = **disiplin navigasi + onboarding**, BUKAN rewrite. Tiga pilar:
1. Navigasi dijinakkan — 19 group berurut + berikon, dibungkus jadi 6–8 "aplikasi"
   bertema yang dikenal staf pesantren.
2. Onboarding mengikuti jalur adopsi NYATA (impor santri dulu, bukan keuangan).
3. Tiap layar "mengajari" — empty state, badge, global search.

## Pengkategorian: 19 group → 7 "aplikasi"
| Aplikasi | Ikon | Isi | Pengguna |
|---|---|---|---|
| Kesantrian | user-group | Santri, Kelas, Penempatan, Alumni, ID Card, impor | TU (jalur adopsi utama) |
| PPDB | academic-cap | Calon Santri, Dokumen, Hasil Ujian, seleksi | Panitia/TU |
| Website | globe-alt | Artikel, Galeri, Profil, Pengaturan Web, File Media | Humas/TU |
| Kepengasuhan & Asrama | home-modern | Asrama, Tahfidz, Kesehatan, Konseling, Kegiatan, Izin, Kunjungan | Pengasuh/Musyrif |
| Akademik | book-open | Periode, Pelajaran, Jadwal, Nilai, Rapor | Ustadz/kurikulum |
| Keuangan | banknotes | Tagihan, Pembayaran, Tabungan + (collapsed) Buku Kas, Pajak, Payroll, Pembelian, Aset, Laporan | Bendahara |
| Laporan | chart-bar | Ringkasan lintas-modul utk pimpinan | Kepala Pondok |
| Sistem | cog | Pengguna, RBAC, Notifikasi, Provisioning, AI — sembunyi di Mode Sederhana | Admin pusat |

## Roadmap prioritas (urut dampak/effort)
1. **[kecil/tinggi] Urutkan + ikon-i navigation group** sesuai jalur adopsi (Kesantrian/PPDB/Website di puncak; keuangan lanjutan collapsed). `navigationGroups([...])` di TenantPanelProvider — satu file.
2. **[sedang/tinggi] Global Search** di Santri/Tagihan/CalonSantri/Pembayaran (`getGloballySearchableAttributes` + Cmd/Ctrl+K). Fitur paling dicari di 1302 santri.
3. **[kecil/tinggi] Navigation badge** angka-tindakan: Tagihan belum lunas, pendaftar PPDB baru, pembayaran perlu verifikasi (tiru ApprovalRequestResource).
4. **[kecil/tinggi] Empty state mengajari** di 24 menu inti: heading + deskripsi + ikon + tombol aksi (Impor Excel / Tambah). Ubah "No records" jadi onboarding.
5. **[sedang/tinggi] Widget Checklist Onboarding** baca state DB: profil → impor santri → data wali → tampilkan website. Keuangan sengaja TIDAK di checklist awal.
6. **[besar/tinggi] Bungkus 19 group → 7 aplikasi** via penamaan+urutan (murah) lalu bertahap ke Filament Cluster (sidebar sendiri per app).
7. **[sedang/sedang] Ganti jargon** ke bahasa staf (Jurnal Umum→Buku Kas, Pengadaan→Pembelian) + tooltip; menu Sistem/AI keluar dari Mode Sederhana.
8. **[kecil/sedang] Poles toggle Mode Sederhana** ke komponen Filament native (buang inline CSS+hex+emoji) agar adaptif dark mode.
9. **[sedang/sedang] Keterbacaan & target sentuh**: font body 15–16px, tombol aksi ≥44px, kontras AA, status = warna+ikon+teks. Untuk HP murah & ustadz lansia.
10. **[sedang/sedang] Wizard impor santri** (bungkus command jadi Action+Wizard: template → upload → pratinjau → konfirmasi).
11. **[sedang/sedang] Progressive disclosure di form**: field lanjutan dalam Section collapsed; hanya field wajib terlihat.

## Quick wins (<1 sesi, dampak terasa)
- navigationGroups berurut + berikon (satu file).
- Aktifkan Cmd/Ctrl+K + searchable Santri (nama, NIS).
- Badge Tagihan & CalonSantri.
- Empty state Santri + Tagihan + 22 menu inti.
- Refactor toggle Mode Sederhana ke `.fi-*` / x-filament::button.
- Audit label jargon + tooltip.
- Font 15–16px + tombol 44px di theme.css.

## Hindari (anti-pattern)
- Rewrite panel / pindah dari Filament — tidak perlu.
- Keuangan di puncak navigasi atau checklist awal — bukan jalur adopsi.
- Tur multi-langkah bertubi ke staf gaptek — satu hint pada satu waktu.
- Jargon teknis tanpa terjemahan/tooltip.
- Navigasi >3 level — pakai Cluster untuk level ketiga.
- Definisi "core" tersebar di puluhan `shouldRegisterNavigation` — pindah ke satu sumber config.
- Tabel kosong polos / spinner di 1302 baris (pakai skeleton/deferLoading) / status hanya warna.
- Menghapus Mode Sederhana atau mengubah RBAC/route (pagar keras).
