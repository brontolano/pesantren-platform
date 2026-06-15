@extends('legal.layout')
@section('title', 'Kebijakan Privasi')
@section('content')

<h1>Kebijakan Privasi</h1>
<p class="updated">Terakhir diperbarui: 5 Juni 2026</p>

<p>Pesantren Platform ("kami", "Platform") yang dikelola oleh PT Rojo Bronto Lano berkomitmen melindungi privasi pengguna. Kebijakan ini menjelaskan bagaimana kami mengumpulkan, menggunakan, dan melindungi data pribadi Anda sesuai dengan Undang-Undang Pelindungan Data Pribadi (UU PDP) No. 27 Tahun 2022.</p>

<h2>1. Data yang Kami Kumpulkan</h2>
<p>Kami mengumpulkan data berikut saat Anda menggunakan Platform:</p>
<ul>
    <li><strong>Data identitas:</strong> Nama, email, nomor telepon, alamat</li>
    <li><strong>Data santri:</strong> Nama lengkap, NIK, tanggal lahir, data akademik, riwayat kesehatan</li>
    <li><strong>Data keuangan:</strong> Riwayat tagihan dan pembayaran (bukan data kartu/rekening bank)</li>
    <li><strong>Data teknis:</strong> Alamat IP, jenis browser, log akses untuk keamanan</li>
</ul>

<h2>2. Tujuan Penggunaan Data</h2>
<ul>
    <li>Menyediakan layanan administrasi pesantren sesuai kontrak</li>
    <li>Mengirim notifikasi terkait tagihan, kegiatan, dan pengumuman pesantren</li>
    <li>Memelihara keamanan dan integritas sistem</li>
    <li>Memenuhi kewajiban hukum yang berlaku</li>
</ul>

<h2>3. Perlindungan Data Anak</h2>
<div class="info-box">
    Karena Platform mengelola data santri yang sebagian besar berusia di bawah 18 tahun, kami menerapkan perlindungan ekstra sesuai Pasal 25 UU PDP. Data anak hanya diproses dengan persetujuan wali dan hanya untuk keperluan pendidikan.
</div>

<h2>4. Isolasi & Keamanan Data</h2>
<p>Setiap pesantren (tenant) memiliki ruang data yang terisolasi secara teknis. Kami menerapkan:</p>
<ul>
    <li>Enkripsi data saat transit (HTTPS/TLS)</li>
    <li>Password ter-hash menggunakan bcrypt</li>
    <li>Multi-Factor Authentication (MFA) untuk akun admin</li>
    <li>Audit log untuk setiap aksi sensitif</li>
    <li>Backup terenkripsi harian</li>
</ul>

<h2>5. Pembagian Data dengan Pihak Ketiga</h2>
<p>Kami <strong>tidak menjual</strong> data pribadi Anda. Data hanya dibagikan kepada:</p>
<ul>
    <li>Penyedia layanan infrastruktur (hosting) untuk menjalankan Platform</li>
    <li>Pihak berwenang jika diwajibkan oleh hukum</li>
</ul>

<h2>6. Hak Subjek Data</h2>
<p>Sesuai UU PDP, Anda berhak:</p>
<ul>
    <li>Mengakses dan mendapatkan salinan data pribadi Anda</li>
    <li>Meminta perbaikan data yang tidak akurat</li>
    <li>Meminta penghapusan data (dengan batasan kontraktual dan hukum)</li>
    <li>Menarik persetujuan pemrosesan data</li>
    <li>Mengajukan keberatan atas pemrosesan data</li>
</ul>

<h2>7. Penyimpanan & Penghapusan Data</h2>
<p>Data disimpan selama pesantren aktif menggunakan Platform. Setelah kontrak berakhir, data disimpan maksimal 90 hari sebelum dihapus permanen, kecuali diwajibkan lain oleh peraturan.</p>
<p>Anda dapat meminta penghapusan akun dan data pribadi kapan saja. Tata cara, jenis data yang dihapus, dan masa retensi dijelaskan pada halaman <a href="{{ route('legal.account-deletion') }}">Penghapusan Akun &amp; Data</a>.</p>

<h2>8. Cookie</h2>
<p>Platform menggunakan cookie esensial untuk fungsi login dan keamanan. Lihat <a href="{{ route('legal.cookies') }}">Cookie Policy</a> kami untuk detail lengkap.</p>

<h2>9. Perubahan Kebijakan</h2>
<p>Kami dapat memperbarui kebijakan ini sewaktu-waktu. Perubahan material akan diinformasikan melalui email atau notifikasi di Platform.</p>

<h2>10. Kontak</h2>
<p>Untuk pertanyaan terkait privasi data, hubungi kami di <a href="mailto:admin@brontolano.com">admin@brontolano.com</a> atau kunjungi halaman <a href="{{ route('legal.contact') }}">Kontak</a>.</p>

@endsection
