<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Pesantren Platform — Sistem Administrasi Pesantren Terpadu</title>
    <meta name="description" content="Platform SaaS untuk manajemen pesantren modern. Kelola santri, keuangan, akademik, PPDB, asrama, dan komunikasi wali dalam satu sistem terpadu.">
    <meta name="keywords" content="sistem pesantren, manajemen pesantren, software pesantren, administrasi pesantren, SaaS pesantren, aplikasi pondok pesantren">
    <meta name="author" content="PT Rojo Bronto Lano">
    <meta name="robots" content="index, follow">
    <link rel="canonical" href="https://pesantren.online">
    <meta property="og:title" content="Pesantren Platform — Sistem Administrasi Pesantren Terpadu">
    <meta property="og:description" content="Platform SaaS untuk manajemen pesantren modern. Satu sistem untuk semua kebutuhan pengelolaan.">
    <meta property="og:url" content="https://pesantren.online">
    <meta property="og:type" content="website">
    <meta property="og:locale" content="id_ID">
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=plus-jakarta-sans:400,500,600,700,800" rel="stylesheet">
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        :root {
            --primary: #059669;
            --primary-light: #10b981;
            --primary-dark: #047857;
            --bg: #fafbfc;
            --bg-white: #ffffff;
            --bg-dark: #0f172a;
            --text: #1e293b;
            --text-muted: #64748b;
            --text-light: #94a3b8;
            --border: #e2e8f0;
        }
        body {
            font-family: 'Plus Jakarta Sans', system-ui, -apple-system, sans-serif;
            background: var(--bg);
            color: var(--text);
            line-height: 1.6;
            -webkit-font-smoothing: antialiased;
        }
        a { color: inherit; text-decoration: none; }

        /* NAV */
        nav {
            position: fixed; top: 0; left: 0; right: 0; z-index: 50;
            background: rgba(255,255,255,0.9);
            backdrop-filter: blur(12px);
            border-bottom: 1px solid var(--border);
            padding: 0 2rem;
        }
        .nav-inner {
            max-width: 1120px; margin: 0 auto;
            display: flex; align-items: center; justify-content: space-between;
            height: 64px;
        }
        .nav-logo { font-size: 1.15rem; font-weight: 800; color: var(--text); }
        .nav-logo span { color: var(--primary); }
        .nav-links { display: flex; gap: 2rem; align-items: center; }
        .nav-links a { font-size: 0.9rem; font-weight: 500; color: var(--text-muted); transition: color 0.2s; }
        .nav-links a:hover { color: var(--primary); }
        .btn {
            display: inline-flex; align-items: center; gap: 0.5rem;
            padding: 0.6rem 1.5rem; border-radius: 8px;
            font-size: 0.9rem; font-weight: 600;
            transition: all 0.2s; border: none; cursor: pointer;
        }
        .btn-primary { background: var(--primary); color: #fff; }
        .btn-primary:hover { background: var(--primary-dark); transform: translateY(-1px); }
        .btn-outline { border: 1.5px solid var(--border); background: transparent; color: var(--text); }
        .btn-outline:hover { border-color: var(--primary); color: var(--primary); }

        /* HERO */
        .hero {
            padding: 10rem 2rem 5rem;
            text-align: center;
            background: linear-gradient(180deg, #fff 0%, var(--bg) 100%);
        }
        .hero-inner { max-width: 720px; margin: 0 auto; }
        .hero-badge {
            display: inline-block;
            background: #ecfdf5; color: var(--primary);
            font-size: 0.8rem; font-weight: 600;
            padding: 0.35rem 1rem; border-radius: 9999px;
            margin-bottom: 1.5rem;
        }
        .hero h1 { font-size: 3rem; font-weight: 800; line-height: 1.15; margin-bottom: 1.25rem; color: var(--bg-dark); }
        .hero h1 span { color: var(--primary); }
        .hero p { font-size: 1.15rem; color: var(--text-muted); line-height: 1.8; margin-bottom: 2.5rem; max-width: 560px; margin-left: auto; margin-right: auto; }
        .hero-actions { display: flex; gap: 1rem; justify-content: center; flex-wrap: wrap; }

        /* STATS */
        .stats {
            display: flex; justify-content: center; gap: 4rem; flex-wrap: wrap;
            padding: 3rem 2rem;
            max-width: 800px; margin: 0 auto;
        }
        .stat { text-align: center; }
        .stat-num { font-size: 2.25rem; font-weight: 800; color: var(--primary); }
        .stat-label { font-size: 0.85rem; color: var(--text-muted); margin-top: 0.25rem; }

        /* MODULES */
        .section { padding: 5rem 2rem; }
        .section-inner { max-width: 1120px; margin: 0 auto; }
        .section-header { text-align: center; margin-bottom: 3rem; }
        .section-header h2 { font-size: 2rem; font-weight: 800; margin-bottom: 0.75rem; color: var(--bg-dark); }
        .section-header p { font-size: 1.05rem; color: var(--text-muted); max-width: 600px; margin: 0 auto; }

        .modules-grid {
            display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 1.25rem;
        }
        .module-card {
            background: var(--bg-white);
            border: 1px solid var(--border);
            border-radius: 12px; padding: 1.75rem;
            transition: all 0.2s;
        }
        .module-card:hover { border-color: var(--primary-light); box-shadow: 0 4px 24px rgba(5,150,105,0.08); transform: translateY(-2px); }
        .module-icon { font-size: 1.75rem; margin-bottom: 0.75rem; }
        .module-card h3 { font-size: 1.05rem; font-weight: 700; margin-bottom: 0.4rem; }
        .module-card p { font-size: 0.88rem; color: var(--text-muted); line-height: 1.6; }

        /* WHY */
        .why-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 2rem; }
        .why-item { text-align: center; padding: 1.5rem; }
        .why-icon { font-size: 2.5rem; margin-bottom: 1rem; }
        .why-item h3 { font-size: 1.05rem; font-weight: 700; margin-bottom: 0.5rem; }
        .why-item p { font-size: 0.88rem; color: var(--text-muted); line-height: 1.6; }

        /* CTA */
        .cta {
            background: var(--bg-dark); color: #fff;
            text-align: center; padding: 5rem 2rem; margin: 3rem 0 0;
        }
        .cta h2 { font-size: 2rem; font-weight: 800; margin-bottom: 1rem; }
        .cta p { font-size: 1.05rem; color: var(--text-light); margin-bottom: 2rem; max-width: 500px; margin-left: auto; margin-right: auto; }
        .cta .btn-primary { background: var(--primary-light); font-size: 1rem; padding: 0.8rem 2rem; }
        .cta .btn-primary:hover { background: var(--primary); }

        /* FOOTER */
        footer {
            background: var(--bg-dark); color: var(--text-light);
            padding: 2rem;
            border-top: 1px solid #1e293b;
        }
        .footer-inner {
            max-width: 1120px; margin: 0 auto;
            display: flex; justify-content: space-between; align-items: center;
            flex-wrap: wrap; gap: 1rem;
        }
        .footer-copy { font-size: 0.85rem; }
        .footer-copy a { color: var(--primary-light); }
        .footer-copy a:hover { text-decoration: underline; }
        .footer-links { display: flex; gap: 1.5rem; }
        .footer-links a { font-size: 0.8rem; color: var(--text-light); }
        .footer-links a:hover { color: #fff; }

        /* RESPONSIVE */
        @media (max-width: 768px) {
            .hero h1 { font-size: 2rem; }
            .hero { padding: 8rem 1.5rem 3rem; }
            .stats { gap: 2rem; }
            .nav-links { display: none; }
            .modules-grid { grid-template-columns: 1fr; }
            .footer-inner { flex-direction: column; text-align: center; }
            .footer-links { justify-content: center; }
        }
    </style>
</head>
<body>

<nav>
    <div class="nav-inner">
        <a href="/" class="nav-logo">Pesantren<span>.</span>online</a>
        <div class="nav-links">
            <a href="#modul">Modul</a>
            <a href="#keunggulan">Keunggulan</a>
            <a href="https://demo.pesantren.online" target="_blank" rel="noopener">Demo</a>
            <a href="{{ route('legal.contact') }}">Kontak</a>
            <a href="/platform" class="btn btn-primary">Masuk</a>
        </div>
    </div>
</nav>

<section class="hero">
    <div class="hero-inner">
        <div class="hero-badge">Platform Pesantren Modern</div>
        <h1>Kelola Pesantren Lebih <span>Mudah & Profesional</span></h1>
        <p>Sistem administrasi pesantren terpadu berbasis cloud. Dari data santri, keuangan, akademik, hingga komunikasi wali — semua dalam satu platform.</p>
        <div class="hero-actions">
            <a href="https://demo.pesantren.online" target="_blank" rel="noopener" class="btn btn-primary">Coba Demo Gratis</a>
            <a href="https://wa.me/6285572711197?text=Assalamu'alaikum,%20saya%20tertarik%20dengan%20Pesantren%20Platform" class="btn btn-outline">Hubungi Kami</a>
        </div>
    </div>
</section>

<div class="stats">
    <div class="stat">
        <div class="stat-num">12+</div>
        <div class="stat-label">Modul Terintegrasi</div>
    </div>
    <div class="stat">
        <div class="stat-num">100%</div>
        <div class="stat-label">White-Label</div>
    </div>
    <div class="stat">
        <div class="stat-num">24/7</div>
        <div class="stat-label">Cloud Uptime</div>
    </div>
</div>

<section class="section" id="modul">
    <div class="section-inner">
        <div class="section-header">
            <h2>Modul Lengkap untuk Pesantren</h2>
            <p>Setiap modul bisa diaktifkan sesuai kebutuhan pesantren. Bayar hanya yang dipakai.</p>
        </div>
        <div class="modules-grid">
            <div class="module-card">
                <div class="module-icon">&#x1F393;</div>
                <h3>Kesantrian</h3>
                <p>Data santri, penempatan kelas, mutasi, alumni. Engine kelas fleksibel (Mu'adalah/linear).</p>
            </div>
            <div class="module-card">
                <div class="module-icon">&#x1F4B0;</div>
                <h3>Keuangan</h3>
                <p>Tagihan SPP (Masehi/Hijriah), pembayaran, tabungan, laporan keuangan otomatis.</p>
            </div>
            <div class="module-card">
                <div class="module-icon">&#x1F4DD;</div>
                <h3>PPDB Online</h3>
                <p>Pendaftaran santri baru, upload dokumen, ujian seleksi, registrasi ulang digital.</p>
            </div>
            <div class="module-card">
                <div class="module-icon">&#x1F4DA;</div>
                <h3>Akademik</h3>
                <p>Periode akademik, pelajaran, jadwal, penilaian, rapor otomatis per kelas.</p>
            </div>
            <div class="module-card">
                <div class="module-icon">&#x1F3E0;</div>
                <h3>Asrama</h3>
                <p>Blok, kamar, kapasitas, penempatan santri, biaya asrama terintegrasi keuangan.</p>
            </div>
            <div class="module-card">
                <div class="module-icon">&#x1F4D6;</div>
                <h3>Tahfidz</h3>
                <p>Pencatatan hafalan, ziyadah/muraja'ah, penilaian setoran, progress per santri.</p>
            </div>
            <div class="module-card">
                <div class="module-icon">&#x2764;</div>
                <h3>Kesehatan & Konseling</h3>
                <p>Riwayat kesehatan santri, sesi konseling, catatan medis terstruktur.</p>
            </div>
            <div class="module-card">
                <div class="module-icon">&#x1F4C5;</div>
                <h3>Kegiatan & Perizinan</h3>
                <p>Kegiatan pesantren, izin keluar santri, kunjungan wali dengan approval digital.</p>
            </div>
            <div class="module-card">
                <div class="module-icon">&#x1F4F1;</div>
                <h3>Notifikasi WhatsApp</h3>
                <p>Broadcast tagihan, info kegiatan, pengumuman langsung ke WhatsApp wali santri.</p>
            </div>
            <div class="module-card">
                <div class="module-icon">&#x1F310;</div>
                <h3>Website Pesantren</h3>
                <p>Website publik pesantren dengan CMS. Profil, artikel, galeri, PPDB online — domain sendiri.</p>
            </div>
            <div class="module-card">
                <div class="module-icon">&#x1F468;&#x200D;&#x1F469;&#x200D;&#x1F466;</div>
                <h3>Portal Wali</h3>
                <p>Dashboard wali santri: lihat tagihan, pembayaran, perkembangan anak secara real-time.</p>
            </div>
            <div class="module-card">
                <div class="module-icon">&#x1F4CA;</div>
                <h3>Laporan & Analitik</h3>
                <p>Dashboard interaktif, export Excel/PDF, laporan keuangan, statistik kesantrian.</p>
            </div>
        </div>
    </div>
</section>

<section class="section" id="keunggulan" style="background: #fff;">
    <div class="section-inner">
        <div class="section-header">
            <h2>Mengapa Pesantren Platform?</h2>
            <p>Dibangun khusus untuk kebutuhan pesantren Indonesia.</p>
        </div>
        <div class="why-grid">
            <div class="why-item">
                <div class="why-icon">&#x1F512;</div>
                <h3>Data Aman & Terisolasi</h3>
                <p>Setiap pesantren memiliki ruang data terpisah. Data Anda tidak tercampur dengan pesantren lain.</p>
            </div>
            <div class="why-item">
                <div class="why-icon">&#x1F3F7;</div>
                <h3>White-Label 100%</h3>
                <p>Gunakan domain sendiri, logo sendiri, branding sendiri. Tanpa watermark platform.</p>
            </div>
            <div class="why-item">
                <div class="why-icon">&#x2601;</div>
                <h3>Cloud — Tanpa Instal</h3>
                <p>Akses dari mana saja. Tidak perlu server sendiri, tidak perlu tim IT. Kami yang kelola.</p>
            </div>
            <div class="why-item">
                <div class="why-icon">&#x1F527;</div>
                <h3>Configurable per Pesantren</h3>
                <p>Periode Masehi/Hijriah, skala nilai, jenis tagihan — semua bisa disesuaikan tanpa coding.</p>
            </div>
        </div>
    </div>
</section>

<section class="cta">
    <h2>Siap Mendigitalkan Pesantren Anda?</h2>
    <p>Coba langsung demo sistemnya, atau konsultasi gratis dengan tim kami.</p>
    <div class="hero-actions" style="justify-content:center;">
        <a href="https://demo.pesantren.online" target="_blank" rel="noopener" class="btn btn-primary">Coba Demo Gratis</a>
        <a href="https://wa.me/6285572711197?text=Assalamu'alaikum,%20saya%20ingin%20demo%20Pesantren%20Platform" class="btn btn-outline" style="border-color:rgba(255,255,255,0.3);color:#fff;">Chat via WhatsApp</a>
    </div>
</section>

<footer>
    <div class="footer-inner">
        <div class="footer-copy">Pesantren Platform &copy; {{ date('Y') }} <a href="https://brontolano.com" target="_blank" rel="noopener">PT Rojo Bronto Lano</a></div>
        <div class="footer-links">
            <a href="{{ route('legal.privacy') }}">Kebijakan Privasi</a>
            <a href="{{ route('legal.terms') }}">Syarat Layanan</a>
            <a href="{{ route('legal.disclaimer') }}">Disclaimer</a>
            <a href="{{ route('legal.cookies') }}">Cookie Policy</a>
            <a href="{{ route('legal.contact') }}">Kontak</a>
        </div>
    </div>
</footer>

</body>
</html>
