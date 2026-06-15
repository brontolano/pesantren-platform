@extends('legal.layout')
@section('title', 'Penghapusan Akun & Data')
@section('content')

<h1>Penghapusan Akun &amp; Data</h1>
<p class="updated">Terakhir diperbarui: 15 Juni 2026</p>

<p>Halaman ini menjelaskan cara meminta penghapusan akun dan data pribadi pada aplikasi yang dijalankan di atas Pesantren Platform (dikelola oleh PT Rojo Bronto Lano), termasuk aplikasi Penerimaan Santri Baru (SPMB), Portal Wali, dan aplikasi santri masing-masing pesantren.</p>

<div class="info-box">
    Data Anda dikelola oleh <strong>pesantren tempat Anda terdaftar</strong> sebagai pengendali data. Cara tercepat mengajukan penghapusan adalah membuka halaman <em>Penghapusan Akun</em> milik pesantren Anda di alamat <code>https://[nama-pesantren].pesantren.online/hapus-akun</code>, lalu menghubungi kanal resmi pesantren yang tertera di sana.
</div>

<h2>1. Siapa yang Dapat Mengajukan</h2>
<p>Permintaan diajukan oleh pemilik akun: orang tua/wali, calon santri/peserta, atau santri yang bersangkutan. Untuk data anak di bawah 18 tahun, permintaan diajukan oleh orang tua/wali.</p>

<h2>2. Cara Mengajukan</h2>
<ul>
    <li>Buka halaman <code>/hapus-akun</code> pada domain pesantren Anda.</li>
    <li>Hubungi kanal resmi pesantren (WhatsApp admin, email, atau kantor pesantren) yang tercantum di halaman tersebut.</li>
    <li>Sertakan nama lengkap, nomor pendaftaran / NIS / email akun, dan hubungan Anda dengan santri untuk verifikasi identitas.</li>
</ul>

<h2>3. Data yang Dihapus</h2>
<p>Setelah permintaan disetujui dan identitas terverifikasi: akun login (nama pengguna &amp; sandi), data profil pribadi (nama, kontak, alamat, foto, tanda tangan), dan contoh tulisan tangan yang dikirim.</p>

<h2>4. Data yang Disimpan Sementara</h2>
<p>Sebagian data administrasi pendidikan dan keuangan dapat disimpan selama diwajibkan peraturan, lalu dihapus permanen. Data yang tidak wajib disimpan dihapus selambat-lambatnya 30 hari sejak permintaan disetujui; data yang wajib diarsipkan disimpan paling lama 90 hari setelah kewajiban berakhir, kecuali peraturan menentukan lain.</p>

<h2>5. Waktu Pemrosesan</h2>
<p>Permintaan diproses dalam 7 hari kerja. Anda akan diberi tahu setelah penghapusan selesai.</p>

<h2>6. Kontak</h2>
<p>Bila Anda tidak dapat menghubungi pesantren Anda, hubungi kami di <a href="mailto:admin@brontolano.com">admin@brontolano.com</a>. Lihat juga <a href="{{ route('legal.privacy') }}">Kebijakan Privasi</a> kami.</p>

@endsection
