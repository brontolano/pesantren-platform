# Token desain

Semua token CSS memakai awalan --pu- agar tidak bertabrakan dengan gaya aplikasi. resources/css/pesantren-ui.css memakai tiga lapisan: primitive untuk nilai mentah, semantic untuk maksud, dan component untuk penyesuaian kontrol.

| Token | Nilai awal | Pemakaian |
|---|---|---|
| `--pu-primitive-forest-900` | `#123C35` | Sidebar dan identitas utama |
| `--pu-primitive-jade-700` | `#2F6B5B` | Aksi, tautan, dan fokus |
| `--pu-primitive-sage-100` | `#DDE9DF` | Sorotan lembut dan ikon |
| `--pu-primitive-ivory-50` | `#F7F5EF` | Kanvas halaman |
| `--pu-primitive-paper` | `#FFFEFA` | Kartu dan panel |
| `--pu-primitive-gold-500` | `#B99A55` | Aksen navigasi aktif |
| `--pu-primitive-navy-900` | `#1F2D46` | Teks utama |
| `--pu-color-ink-600` | `#536274` | Teks pendamping |
| `--pu-color-border` | `#D7DFD8` | Garis kontrol dan panel |
| `--pu-color-success-800` | `#176B47` | Keberhasilan |
| `--pu-color-warning-900` | `#744B08` | Peringatan |
| `--pu-color-danger-800` | `#9B2828` | Kegagalan |

Warna adalah nilai awal, bukan bukti kontras untuk semua kombinasi. Periksa kontras setelah tema/status diterapkan. Tampilkan status dalam teks, jangan mengandalkan warna saja.

Tipografi memakai Inter bila tersedia dan font sistem sebagai fallback; paket tidak mengunduh font. Ukuran dasar 16 px; label minimum 13 px. Jarak memakai kelipatan 4 px, dari 4 hingga 48 px. Radius kontrol 8 px, kartu 12 px, lebar isi 1200 px.

## Kustomisasi

Tema dasar v2 adalah light mode. Font Inter memakai fallback sistem tanpa unduhan font eksternal. Override token semantik di stylesheet host setelah stylesheet paket dimuat:

~~~css
:root {
    --pu-color-brand-700: #123c35;
    --pu-color-brand-50: #dde9df;
    --pu-color-focus: #2f6b5b;
}
~~~

Hindari menimpa selector global seperti button, table, input, atau body. Gunakan kelas pu- atau wrapper aplikasi.
