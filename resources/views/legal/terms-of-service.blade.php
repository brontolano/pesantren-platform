@extends('legal.layout')
@section('title', 'Syarat & Ketentuan Layanan')
@section('content')

<h1>Syarat & Ketentuan Layanan</h1>
<p class="updated">Terakhir diperbarui: 5 Juni 2026</p>

<p>Syarat dan ketentuan ini mengatur penggunaan Pesantren Platform ("Platform") yang disediakan oleh PT Rojo Bronto Lano ("Penyedia"). Dengan menggunakan Platform, Anda menyetujui syarat berikut.</p>

<h2>1. Definisi</h2>
<ul>
    <li><strong>Platform:</strong> Sistem administrasi pesantren berbasis cloud di pesantren.online</li>
    <li><strong>Pengguna:</strong> Pesantren (tenant) yang berlangganan layanan Platform</li>
    <li><strong>End User:</strong> Staff, guru, wali santri yang mengakses Platform melalui akun Pengguna</li>
    <li><strong>Data Tenant:</strong> Semua data yang dimasukkan oleh Pengguna ke dalam Platform</li>
</ul>

<h2>2. Kepemilikan Data</h2>
<div class="info-box">
    Data yang Anda masukkan ke Platform adalah <strong>milik Anda</strong> sepenuhnya. PT Rojo Bronto Lano hanya bertindak sebagai pemroses data sesuai instruksi Anda.
</div>
<p>Anda berhak mengekspor seluruh data Anda kapan saja dalam format standar (CSV/Excel). Setelah kontrak berakhir, kami menyediakan masa tenggang 90 hari untuk mengunduh data sebelum dihapus permanen.</p>

<h2>3. Lingkup Layanan</h2>
<p>Platform menyediakan layanan SaaS (Software as a Service) meliputi:</p>
<ul>
    <li>Hosting dan pemeliharaan infrastruktur</li>
    <li>Modul administrasi pesantren sesuai paket berlangganan</li>
    <li>Pembaruan fitur dan perbaikan bug secara berkala</li>
    <li>Dukungan teknis melalui WhatsApp dan email</li>
</ul>

<h2>4. Kewajiban Pengguna</h2>
<ul>
    <li>Menjaga kerahasiaan kredensial login</li>
    <li>Tidak menggunakan Platform untuk tujuan melanggar hukum</li>
    <li>Memastikan data yang dimasukkan akurat dan sah</li>
    <li>Membayar biaya berlangganan sesuai jadwal yang disepakati</li>
</ul>

<h2>5. Larangan Penggunaan</h2>
<p>Pengguna dilarang:</p>
<ul>
    <li>Menyalin, memodifikasi, atau mendistribusikan kode sumber Platform</li>
    <li>Menggunakan Platform untuk menyimpan konten ilegal</li>
    <li>Mencoba mengakses data pesantren (tenant) lain</li>
    <li>Melakukan tindakan yang membahayakan keamanan atau ketersediaan Platform</li>
</ul>

<h2>6. Pembayaran & Penagihan</h2>
<ul>
    <li>Biaya berlangganan sesuai paket yang dipilih (bulanan/tahunan)</li>
    <li>Pembayaran dilakukan via transfer bank sebelum jatuh tempo</li>
    <li>Keterlambatan pembayaran lebih dari 14 hari dapat mengakibatkan suspensi akun</li>
    <li>Akun yang di-suspend tetap menyimpan data — tidak ada penghapusan tanpa pemberitahuan</li>
</ul>

<h2>7. Ketersediaan Layanan (Uptime)</h2>
<p>Kami berusaha menjaga uptime Platform minimal 99%. Pemeliharaan terjadwal akan diinformasikan minimal 24 jam sebelumnya. Kami tidak bertanggung jawab atas gangguan akibat force majeure.</p>

<h2>8. Batasan Tanggung Jawab</h2>
<p>PT Rojo Bronto Lano tidak bertanggung jawab atas:</p>
<ul>
    <li>Kerugian tidak langsung yang timbul dari penggunaan Platform</li>
    <li>Kehilangan data akibat kelalaian Pengguna</li>
    <li>Gangguan layanan akibat pihak ketiga (ISP, penyedia infrastruktur)</li>
</ul>
<p>Total tanggung jawab kami dalam hal apapun tidak melebihi jumlah biaya berlangganan yang telah dibayarkan dalam 12 bulan terakhir.</p>

<h2>9. Penghentian Layanan</h2>
<ul>
    <li>Pengguna dapat menghentikan langganan kapan saja dengan pemberitahuan 30 hari</li>
    <li>Penyedia dapat menghentikan layanan jika Pengguna melanggar syarat ini</li>
    <li>Setelah penghentian, data tersedia untuk diunduh selama 90 hari</li>
</ul>

<h2>10. Hukum yang Berlaku</h2>
<p>Syarat dan ketentuan ini tunduk pada hukum Republik Indonesia. Sengketa diselesaikan secara musyawarah, dan jika tidak tercapai, melalui Pengadilan Negeri Sumedang.</p>

<h2>11. Kontak</h2>
<p>Pertanyaan tentang syarat layanan: <a href="mailto:admin@brontolano.com">admin@brontolano.com</a> atau halaman <a href="{{ route('legal.contact') }}">Kontak</a>.</p>

@endsection
