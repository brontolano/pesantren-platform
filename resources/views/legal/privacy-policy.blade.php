<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kebijakan Privasi — Platform Pesantren</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif; background: #f8fafc; color: #1e293b; line-height: 1.7; }
        .container { max-width: 780px; margin: 0 auto; padding: 2rem 1.5rem 4rem; }
        header { padding: 2rem 0 1.5rem; border-bottom: 1px solid #e2e8f0; margin-bottom: 2rem; }
        header a { color: #64748b; font-size: .875rem; text-decoration: none; }
        header a:hover { color: #1e293b; }
        h1 { font-size: 1.75rem; font-weight: 700; margin-top: .5rem; }
        .meta { color: #64748b; font-size: .875rem; margin-top: .5rem; }
        h2 { font-size: 1.125rem; font-weight: 700; margin: 2rem 0 .75rem; color: #0f172a; }
        p { margin-bottom: 1rem; color: #334155; }
        ul { margin: .5rem 0 1rem 1.5rem; color: #334155; }
        ul li { margin-bottom: .4rem; }
        .highlight { background: #fefce8; border-left: 3px solid #fbbf24; padding: .75rem 1rem; border-radius: 0 .5rem .5rem 0; margin: 1rem 0; font-size: .9rem; }
        footer { margin-top: 3rem; padding-top: 1.5rem; border-top: 1px solid #e2e8f0; font-size: .8rem; color: #94a3b8; }
    </style>
</head>
<body>
    <div class="container">
        <header>
            <a href="/">← Kembali ke beranda</a>
            <h1>Kebijakan Privasi</h1>
            <p class="meta">Platform Pesantren White-Label · PT Rojo Bronto Lano · Berlaku sejak 1 Juli 2026</p>
        </header>

        <div class="highlight">
            Dokumen ini menjelaskan bagaimana PT Rojo Bronto Lano mengumpulkan, menggunakan, dan melindungi data pribadi pengguna platform pesantren, sesuai dengan Undang-Undang Nomor 27 Tahun 2022 tentang Perlindungan Data Pribadi (UU PDP).
        </div>

        <h2>1. Data yang Kami Kumpulkan</h2>
        <p>Kami mengumpulkan data berikut dalam rangka operasional platform:</p>
        <ul>
            <li><strong>Data Identitas Santri:</strong> Nama lengkap, nomor induk siswa (NIS), tanggal lahir, jenis kelamin, foto</li>
            <li><strong>Data Keluarga/Wali:</strong> Nama ayah/ibu/wali, nomor telepon, alamat email</li>
            <li><strong>Data Akademik:</strong> Nilai, rapor, jadwal, riwayat penempatan kelas</li>
            <li><strong>Data Keuangan:</strong> Tagihan, riwayat pembayaran, saldo tabungan</li>
            <li><strong>Data Teknis:</strong> Log aktivitas login, alamat IP, browser/perangkat yang digunakan</li>
            <li><strong>Data Kesehatan:</strong> Riwayat pemeriksaan UKS dan sesi konseling (jika modul diaktifkan)</li>
        </ul>
        <p>Kami <strong>tidak</strong> mengumpulkan data pembayaran kartu kredit atau rekening bank. Pembayaran dilakukan via transfer manual yang dikonfirmasi oleh staf pesantren.</p>

        <h2>2. Tujuan Penggunaan Data</h2>
        <p>Data yang dikumpulkan digunakan semata-mata untuk:</p>
        <ul>
            <li>Operasional sistem manajemen pesantren (absensi, nilai, tagihan)</li>
            <li>Komunikasi antara pesantren dan wali santri (notifikasi WA/email)</li>
            <li>Peningkatan layanan platform dan pendeteksian bug</li>
            <li>Pemenuhan kewajiban hukum yang berlaku</li>
        </ul>
        <p>Kami <strong>tidak</strong> menjual, menyewakan, atau membagikan data pribadi kepada pihak ketiga untuk tujuan komersial.</p>

        <h2>3. Penyimpanan & Keamanan Data</h2>
        <ul>
            <li>Data disimpan di server yang berlokasi di Indonesia</li>
            <li>Enkripsi HTTPS untuk semua komunikasi data (TLS 1.2+)</li>
            <li>Password di-hash menggunakan bcrypt (tidak disimpan dalam bentuk plaintext)</li>
            <li>Backup harian terenkripsi dengan AES-256</li>
            <li>Akses data dibatasi berdasarkan peran (RBAC) — wali hanya bisa melihat data anaknya sendiri</li>
            <li>Audit log untuk semua aksi sensitif (login, ubah data, ekspor)</li>
        </ul>

        <h2>4. Hak Subjek Data (Sesuai UU PDP)</h2>
        <p>Sebagai subjek data, wali/pesantren berhak untuk:</p>
        <ul>
            <li><strong>Mengakses</strong> — meminta salinan data pribadi yang kami simpan</li>
            <li><strong>Mengoreksi</strong> — memperbarui data yang tidak akurat</li>
            <li><strong>Menghapus</strong> — meminta penghapusan data setelah langganan berakhir</li>
            <li><strong>Portabilitas</strong> — mengekspor data dalam format yang dapat dibaca mesin (CSV/JSON)</li>
            <li><strong>Menolak pemrosesan</strong> — untuk tujuan selain operasional inti</li>
        </ul>
        <p>Permintaan hak subjek data dapat diajukan ke: <strong>privacy@pesantren-platform.com</strong></p>

        <h2>5. Data Anak (Santri di Bawah Umur)</h2>
        <div class="highlight">
            Sebagian besar santri adalah anak di bawah umur (< 18 tahun). Kami memberikan perlindungan lebih ketat untuk data mereka: data santri hanya dapat diakses oleh staf pesantren yang berwenang dan wali santri yang terdaftar.
        </div>
        <ul>
            <li>Pesantren bertanggung jawab memastikan bahwa hanya wali sah yang mendaftarkan akun Portal Wali</li>
            <li>Foto santri tidak ditampilkan di halaman publik</li>
            <li>Data kesehatan dan konseling hanya dapat diakses oleh petugas UKS/konselor dan wali</li>
        </ul>

        <h2>6. Cookie & Pelacakan</h2>
        <p>Kami menggunakan cookie sesi untuk menjaga status login pengguna. Kami tidak menggunakan cookie pelacak pihak ketiga (Google Analytics, Facebook Pixel, dll).</p>

        <h2>7. Layanan Pihak Ketiga</h2>
        <p>Platform menggunakan layanan pihak ketiga berikut:</p>
        <ul>
            <li><strong>WhatsApp Gateway (WAHA/n8n):</strong> Untuk pengiriman notifikasi — data yang dikirim: nama santri, nominal tagihan, dan pesan yang sudah disetujui pesantren</li>
            <li><strong>SMTP Email:</strong> Untuk pengiriman email notifikasi</li>
            <li><strong>Sentry (opsional):</strong> Untuk monitoring error — tidak memuat data pribadi santri</li>
        </ul>

        <h2>8. Perubahan Kebijakan</h2>
        <p>Kami akan memberitahukan perubahan kebijakan privasi yang material minimal 30 hari sebelum berlaku, melalui email ke admin pesantren.</p>

        <h2>9. Kontak</h2>
        <p>Pertanyaan terkait privasi dapat dikirimkan ke:</p>
        <ul>
            <li>Email: privacy@pesantren-platform.com</li>
            <li>Alamat: [ISI ALAMAT PT ROJO BRONTO LANO]</li>
        </ul>

        <footer>
            &copy; {{ date('Y') }} PT Rojo Bronto Lano. Semua hak dilindungi. ·
            <a href="/kebijakan-privasi" style="color:#64748b">Kebijakan Privasi</a> ·
            <a href="/syarat-layanan" style="color:#64748b">Syarat Layanan</a>
        </footer>
    </div>
</body>
</html>
