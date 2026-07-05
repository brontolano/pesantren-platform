<!DOCTYPE html>
@php
    $namaPesantren = $s['branding_name'] ?? $tenant->name;
    $tagline = $s['branding_tagline'] ?? 'Mencetak Generasi Qurani Berakhlak Mulia';
    $phone = $s['contact_phone'] ?? null;
    $email = $s['contact_email'] ?? null;
    $address = $s['contact_address'] ?? null;
    $waNumber = $phone ? preg_replace('/[^0-9]/', '', $phone) : null;
    if ($waNumber && str_starts_with($waNumber, '0')) { $waNumber = '62' . substr($waNumber, 1); }
@endphp
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $namaPesantren }} — Pondok Pesantren</title>
    <meta name="description" content="{{ $namaPesantren }} — {{ $tagline }}. Tempat harapan Ayah Bunda dirawat, ilmu dan akhlak tumbuh bersama. Pendaftaran santri baru kini dibuka.">
    <meta property="og:title" content="{{ $namaPesantren }}">
    <meta property="og:description" content="{{ $tagline }} — tempat harapan Ayah Bunda dirawat dengan sabar dan penuh cinta.">
    <meta property="og:type" content="website">
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=plus-jakarta-sans:400,500,600,700,800&family=amiri:400,700" rel="stylesheet">
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        :root {
            --primary: #15803d;
            --primary-light: #16a34a;
            --primary-dark: #14532d;
            --gold: #ca8a04;
            --bg: #f8faf9;
            --text: #1e293b;
            --text-muted: #64748b;
            --border: #e2e8f0;
        }
        body { font-family: 'Plus Jakarta Sans', system-ui, sans-serif; background: var(--bg); color: var(--text); line-height: 1.6; -webkit-font-smoothing: antialiased; }
        a { color: inherit; text-decoration: none; }
        .arabic { font-family: 'Amiri', serif; }

        nav { position: fixed; top: 0; left: 0; right: 0; z-index: 50; background: rgba(255,255,255,0.95); backdrop-filter: blur(12px); border-bottom: 1px solid var(--border); padding: 0 1.5rem; }
        .nav-inner { max-width: 1120px; margin: 0 auto; display: flex; align-items: center; justify-content: space-between; height: 68px; }
        .nav-logo { display: flex; align-items: center; gap: 0.65rem; font-size: 1.1rem; font-weight: 800; color: var(--primary-dark); }
        .nav-logo .mark { width: 38px; height: 38px; background: var(--primary); color: #fff; border-radius: 10px; display: flex; align-items: center; justify-content: center; font-size: 1.1rem; }
        .nav-links { display: flex; gap: 1.75rem; align-items: center; }
        .nav-links a { font-size: 0.9rem; font-weight: 500; color: var(--text-muted); transition: color 0.2s; }
        .nav-links a:hover { color: var(--primary); }
        .btn { display: inline-flex; align-items: center; gap: 0.5rem; padding: 0.65rem 1.4rem; border-radius: 8px; font-size: 0.9rem; font-weight: 600; transition: all 0.2s; cursor: pointer; border: none; }
        .btn-primary { background: var(--primary); color: #fff; }
        .btn-primary:hover { background: var(--primary-dark); transform: translateY(-1px); }
        .btn-gold { background: var(--gold); color: #fff; }
        .btn-gold:hover { background: #a16207; transform: translateY(-1px); }
        .btn-outline { border: 1.5px solid var(--border); background: #fff; color: var(--text); }
        .btn-outline:hover { border-color: var(--primary); color: var(--primary); }
        .btn-lg { padding: 0.85rem 2rem; font-size: 1rem; }

        .hero { padding: 11rem 1.5rem 5rem; text-align: center; background: linear-gradient(180deg, #ecfdf3 0%, var(--bg) 100%); position: relative; overflow: hidden; }
        .hero-inner { max-width: 760px; margin: 0 auto; position: relative; z-index: 1; }
        .hero .bismillah { font-size: 1.6rem; color: var(--primary); margin-bottom: 1.25rem; }
        .hero-badge { display: inline-block; background: #fff; color: var(--primary); border: 1px solid #bbf7d0; font-size: 0.8rem; font-weight: 600; padding: 0.35rem 1rem; border-radius: 9999px; margin-bottom: 1.5rem; }
        .hero h1 { font-size: 3rem; font-weight: 800; line-height: 1.15; margin-bottom: 1rem; color: var(--primary-dark); }
        .hero p.tagline { font-size: 1.2rem; color: var(--primary); font-weight: 600; margin-bottom: 0.75rem; }
        .hero p.desc { font-size: 1.05rem; color: var(--text-muted); line-height: 1.8; margin-bottom: 2.5rem; max-width: 580px; margin-left: auto; margin-right: auto; }
        .hero-actions { display: flex; gap: 1rem; justify-content: center; flex-wrap: wrap; }

        .demo-creds { max-width: 460px; margin: 2.5rem auto 0; background: #fff; border: 1.5px solid #bbf7d0; border-radius: 14px; padding: 1.5rem; box-shadow: 0 8px 30px rgba(21,128,61,0.10); text-align: left; }
        .demo-creds-head { font-weight: 700; color: var(--primary-dark); margin-bottom: 1rem; font-size: 0.95rem; text-align: center; }
        .demo-creds-grid { display: flex; flex-direction: column; gap: 0.6rem; margin-bottom: 1.25rem; }
        .demo-creds-grid > div { display: flex; align-items: center; justify-content: space-between; gap: 1rem; background: var(--bg); border: 1px solid var(--border); border-radius: 8px; padding: 0.65rem 0.9rem; }
        .demo-creds-grid .lbl { font-size: 0.8rem; color: var(--text-muted); }
        .demo-creds-grid .val { font-weight: 700; color: var(--primary); font-size: 0.9rem; user-select: all; }
        .demo-creds .btn { width: 100%; justify-content: center; }
        .demo-creds .hint { font-size: 0.78rem; color: var(--text-muted); text-align: center; margin-top: 0.75rem; }

        .stats { display: flex; justify-content: center; gap: 3.5rem; flex-wrap: wrap; padding: 0 1.5rem 4rem; max-width: 900px; margin: 0 auto; }
        .stat { text-align: center; }
        .stat-num { font-size: 2.5rem; font-weight: 800; color: var(--primary); }
        .stat-label { font-size: 0.85rem; color: var(--text-muted); margin-top: 0.25rem; }

        .section { padding: 5rem 1.5rem; }
        .section-inner { max-width: 1120px; margin: 0 auto; }
        .section-header { text-align: center; margin-bottom: 3rem; }
        .section-header .eyebrow { color: var(--gold); font-weight: 700; font-size: 0.85rem; letter-spacing: 0.05em; text-transform: uppercase; }
        .section-header h2 { font-size: 2rem; font-weight: 800; margin: 0.5rem 0 0.75rem; color: var(--primary-dark); }
        .section-header p { font-size: 1.05rem; color: var(--text-muted); max-width: 600px; margin: 0 auto; }

        .programs { display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 1.5rem; }
        .program-card { background: #fff; border: 1px solid var(--border); border-radius: 14px; padding: 2rem; transition: all 0.2s; }
        .program-card:hover { border-color: var(--primary-light); box-shadow: 0 8px 30px rgba(21,128,61,0.08); transform: translateY(-3px); }
        .program-icon { font-size: 2.25rem; margin-bottom: 1rem; }
        .program-card h3 { font-size: 1.2rem; font-weight: 700; margin-bottom: 0.5rem; color: var(--primary-dark); }
        .program-card p { font-size: 0.92rem; color: var(--text-muted); line-height: 1.65; }

        .why { background: #fff; }
        .why-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 2rem; }
        .why-item { text-align: center; padding: 1rem; }
        .why-icon { font-size: 2.5rem; margin-bottom: 1rem; }
        .why-item h3 { font-size: 1.05rem; font-weight: 700; margin-bottom: 0.5rem; }
        .why-item p { font-size: 0.9rem; color: var(--text-muted); line-height: 1.6; }

        .cta-ppdb { background: linear-gradient(135deg, var(--primary-dark) 0%, var(--primary) 100%); color: #fff; text-align: center; padding: 5rem 1.5rem; }
        .cta-ppdb .badge-open { display: inline-block; background: var(--gold); color: #fff; font-size: 0.8rem; font-weight: 700; padding: 0.35rem 1rem; border-radius: 9999px; margin-bottom: 1.25rem; }
        .cta-ppdb h2 { font-size: 2.25rem; font-weight: 800; margin-bottom: 1rem; }
        .cta-ppdb p { font-size: 1.1rem; opacity: 0.9; margin-bottom: 2rem; max-width: 520px; margin-left: auto; margin-right: auto; }

        .contact { background: var(--bg); }
        .contact-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 1.5rem; max-width: 900px; margin: 0 auto; }
        .contact-card { background: #fff; border: 1px solid var(--border); border-radius: 12px; padding: 1.75rem; text-align: center; }
        .contact-card .ci { font-size: 1.75rem; margin-bottom: 0.75rem; }
        .contact-card h4 { font-size: 0.95rem; font-weight: 700; margin-bottom: 0.35rem; }
        .contact-card p, .contact-card a { font-size: 0.9rem; color: var(--text-muted); }
        .contact-card a:hover { color: var(--primary); }

        footer { background: var(--primary-dark); color: #d1fae5; padding: 3rem 1.5rem 2rem; }
        .footer-inner { max-width: 1120px; margin: 0 auto; text-align: center; }
        .footer-logo { font-size: 1.3rem; font-weight: 800; color: #fff; margin-bottom: 0.5rem; }
        .footer-tag { font-size: 0.9rem; opacity: 0.85; margin-bottom: 1.5rem; }
        .footer-divider { border: none; border-top: 1px solid rgba(255,255,255,0.15); margin: 1.5rem 0; }
        .footer-copy { font-size: 0.82rem; opacity: 0.75; }

        @media (max-width: 768px) {
            .hero h1 { font-size: 2.1rem; }
            .hero { padding: 9rem 1.25rem 3rem; }
            .nav-links a:not(.btn) { display: none; }
            .stats { gap: 2rem; }
            .programs { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>

<nav>
    <div class="nav-inner">
        <a href="/" class="nav-logo">
            <span class="mark">{{ mb_substr($namaPesantren, 0, 1) }}</span>
            <span>{{ $namaPesantren }}</span>
        </a>
        <div class="nav-links">
            <a href="#program">Program</a>
            <a href="#keunggulan">Keunggulan</a>
            <a href="#kontak">Kontak</a>
            <a href="/admin" class="btn btn-outline">Login</a>
            <a href="{{ route('tenant.ppdb.form') }}" class="btn btn-gold">Daftar PPDB</a>
        </div>
    </div>
</nav>

<section class="hero">
    <div class="hero-inner">
        <div class="bismillah arabic">بِسْمِ اللَّهِ الرَّحْمَٰنِ الرَّحِيمِ</div>
        <div class="hero-badge">🟢 Pintu Pendaftaran Tahun Ajaran 2026/2027 Terbuka</div>
        <h1>{{ $namaPesantren }}</h1>
        <p class="tagline">{{ $tagline }}</p>
        <p class="desc">Setiap Ayah Bunda menitipkan harapan yang sama: putra-putrinya tumbuh menjadi pribadi yang berilmu, berakhlak, dan mandiri. Di sinilah harapan itu kami rawat — dengan bimbingan yang hangat, lingkungan yang aman, dan hati yang sabar mendampingi setiap langkah.</p>
        <div class="hero-actions">
            <a href="{{ route('tenant.ppdb.form') }}" class="btn btn-primary btn-lg">Titipkan Putra-Putri Anda</a>
            <a href="#program" class="btn btn-outline btn-lg">Kenali Perjalanannya</a>
        </div>

        @if($tenant->id === 'demo')
        <div class="demo-creds">
            <div class="demo-creds-head">🔑 Akun Demo — Silakan Coba Dashboard Admin</div>
            <div class="demo-creds-grid">
                <div><span class="lbl">Email</span><span class="val">admin@demo.pesantren.online</span></div>
                <div><span class="lbl">Password</span><span class="val">demo1234</span></div>
            </div>
            <a href="/admin" class="btn btn-primary">Masuk ke Dashboard →</a>
            <div class="hint">Login untuk menjelajah fitur: data santri, keuangan, akademik, PPDB, dan lainnya.</div>
        </div>
        @endif
    </div>
</section>

<div class="stats">
    <div class="stat"><div class="stat-num">{{ $stats['santri'] }}+</div><div class="stat-label">Keluarga yang Mempercayakan Putra-Putrinya</div></div>
    <div class="stat"><div class="stat-num">{{ $stats['kelas'] }}</div><div class="stat-label">Kelas Penuh Kebersamaan</div></div>
    <div class="stat"><div class="stat-num">{{ $stats['pelajaran'] }}</div><div class="stat-label">Ilmu yang Dipelajari</div></div>
    <div class="stat"><div class="stat-num">24</div><div class="stat-label">Jam Pendampingan Tanpa Henti</div></div>
</div>

<section class="section" id="program">
    <div class="section-inner">
        <div class="section-header">
            <div class="eyebrow">Perjalanan yang Kami Tempuh Bersama</div>
            <h2>Setiap Anak Punya Cerita, Kami Membimbingnya dengan Sabar</h2>
            <p>Bukan sekadar kurikulum di atas kertas — ini adalah perjalanan menemukan jati diri, dituntun langkah demi langkah oleh mereka yang tulus mendampingi.</p>
        </div>
        <div class="programs">
            <div class="program-card">
                <div class="program-icon">📖</div>
                <h3>Tahfidzul Quran</h3>
                <p>Menjaga Al-Quran di dada bukan perlombaan angka juz, melainkan cinta yang ditanam pelan-pelan bersama ustadz hafidz yang sabar mendampingi setiap ayat.</p>
            </div>
            <div class="program-card">
                <div class="program-icon">📚</div>
                <h3>Kajian Kitab Kuning</h3>
                <p>Mewarisi ilmu para ulama lewat sanad yang bersambung — nahwu, shorof, fiqih, dan aqidah dipelajari dengan penuh penghayatan, bukan sekadar hafalan.</p>
            </div>
            <div class="program-card">
                <div class="program-icon">🎓</div>
                <h3>Pendidikan Formal</h3>
                <p>Ijazah negara tetap di tangan, masa depan akademik tetap terbuka — kurikulum nasional terakreditasi berjalan seiring dengan nilai-nilai pesantren.</p>
            </div>
            <div class="program-card">
                <div class="program-icon">🗣️</div>
                <h3>Bahasa Arab & Inggris</h3>
                <p>Membuka pintu dunia bagi santri — agar kelak mereka mampu berbicara, berdakwah, dan menimba ilmu tanpa batas bahasa.</p>
            </div>
            <div class="program-card">
                <div class="program-icon">🤝</div>
                <h3>Pembinaan Akhlak</h3>
                <p>Adab didahulukan sebelum ilmu. Diteladankan lewat keseharian, bukan sekadar diajarkan — karena akhlak tumbuh dari apa yang dilihat dan dirasakan.</p>
            </div>
            <div class="program-card">
                <div class="program-icon">⚽</div>
                <h3>Ekstrakurikuler</h3>
                <p>Ruang bagi santri menemukan bakatnya — berolahraga, berkarya, dan tumbuh percaya diri di luar bangku kelas.</p>
            </div>
        </div>
    </div>
</section>

<section class="section why" id="keunggulan">
    <div class="section-inner">
        <div class="section-header">
            <div class="eyebrow">Mengapa Ayah Bunda Mempercayakan Kami</div>
            <h2>Karena Ketenangan Hati Anda Adalah Prioritas Kami</h2>
        </div>
        <div class="why-grid">
            <div class="why-item"><div class="why-icon">🕌</div><h3>Lingkungan yang Menenangkan</h3><p>Setiap sudut pesantren dirancang agar nilai-nilai keislaman terasa hidup, bukan sekadar diajarkan, dalam keseharian santri.</p></div>
            <div class="why-item"><div class="why-icon">👨‍🏫</div><h3>Dibimbing dengan Ketulusan</h3><p>Asatidz kami bukan hanya berilmu — mereka hadir sebagai orang tua kedua yang mendampingi dengan sabar dan penuh kasih.</p></div>
            <div class="why-item"><div class="why-icon">🏠</div><h3>Rumah Kedua yang Aman</h3><p>Asrama yang bersih dan terjaga, tempat santri merasa nyaman meski jauh dari pelukan keluarga.</p></div>
            <div class="why-item"><div class="why-icon">📈</div><h3>Anda Selalu Tahu Perkembangannya</h3><p>Tak perlu menunggu lama untuk tahu kabar ananda — perkembangan mereka dilaporkan rutin dan transparan kepada Anda.</p></div>
        </div>
    </div>
</section>

<section class="cta-ppdb">
    <div class="badge-open">PINTU KAMI TERBUKA UNTUK KELUARGA ANDA</div>
    <h2>Mari Rawat Harapan Itu Bersama Kami</h2>
    <p>Jadikan putra-putri Anda bagian dari keluarga besar {{ $namaPesantren }} — tempat ilmu, adab, dan cinta tumbuh bersama. Langkah pertama itu kini semudah mengisi satu formulir.</p>
    <a href="{{ route('tenant.ppdb.form') }}" class="btn btn-gold btn-lg">Mulai Langkah Pertama</a>
</section>

<section class="section contact" id="kontak">
    <div class="section-inner">
        <div class="section-header">
            <div class="eyebrow">Hubungi Kami</div>
            <h2>Informasi & Kontak</h2>
        </div>
        <div class="contact-grid">
            @if($phone)
            <div class="contact-card">
                <div class="ci">📱</div><h4>Telepon / WhatsApp</h4>
                <p><a href="https://wa.me/{{ $waNumber }}">{{ $phone }}</a></p>
            </div>
            @endif
            @if($email)
            <div class="contact-card">
                <div class="ci">✉️</div><h4>Email</h4>
                <p><a href="mailto:{{ $email }}">{{ $email }}</a></p>
            </div>
            @endif
            @if($address)
            <div class="contact-card">
                <div class="ci">📍</div><h4>Alamat</h4>
                <p>{{ $address }}</p>
            </div>
            @endif
        </div>
    </div>
</section>

<footer>
    <div class="footer-inner">
        <div class="footer-logo">{{ $namaPesantren }}</div>
        <div class="footer-tag">{{ $tagline }}</div>
        @if($phone || $email)
        <div style="font-size: 0.88rem; opacity: 0.9;">
            @if($phone) {{ $phone }} @endif
            @if($phone && $email) &nbsp;•&nbsp; @endif
            @if($email) {{ $email }} @endif
        </div>
        @endif
        <hr class="footer-divider">
        <div class="footer-copy">&copy; {{ date('Y') }} {{ $namaPesantren }}. Hak cipta dilindungi.</div>
    </div>
</footer>

</body>
</html>
