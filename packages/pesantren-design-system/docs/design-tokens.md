# Token desain

Semua token CSS memakai awalan --pu- agar tidak bertabrakan dengan gaya aplikasi. resources/css/pesantren-ui.css memakai tiga lapisan: primitive untuk nilai mentah, semantic untuk maksud, dan component untuk penyesuaian kontrol.

| Token | Nilai awal | Pemakaian |
|---|---|---|
| --pu-color-brand-700 | #1d4ed8 | Tombol utama, fokus, tautan aktif |
| --pu-color-brand-50 | #eff6ff | Latar informasi |
| --pu-color-ink-900 | #111827 | Teks utama |
| --pu-color-ink-600 | #4b5563 | Teks pendamping |
| --pu-color-surface | #ffffff | Panel, formulir, kartu |
| --pu-color-canvas | #f8fafc | Latar kerja |
| --pu-color-border | #6b7280 | Garis kontrol |
| --pu-color-success-800 | #166534 | Keberhasilan |
| --pu-color-warning-900 | #78350f | Peringatan |
| --pu-color-danger-800 | #991b1b | Kegagalan |

Warna adalah nilai awal, bukan bukti kontras untuk semua kombinasi. Periksa kontras setelah tema/status diterapkan. Tampilkan status dalam teks, jangan mengandalkan warna saja.

Tipografi memakai Inter bila tersedia dan font sistem sebagai fallback; paket tidak mengunduh font. Ukuran dasar 16 px; label minimum 13 px. Jarak memakai kelipatan 4 px, dari 4 hingga 48 px. Radius kontrol 8 px, kartu 12 px, lebar isi 1200 px.

## Kustomisasi

Override token semantik di stylesheet host setelah stylesheet paket dimuat:

~~~css
:root {
    --pu-color-brand-700: #14532d;
    --pu-color-brand-50: #f0fdf4;
    --pu-color-focus: #14532d;
}
~~~

Hindari menimpa selector global seperti button, table, input, atau body. Gunakan kelas pu- atau wrapper aplikasi.
