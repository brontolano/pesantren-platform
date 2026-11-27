<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="Pesantren.online membantu pesantren memulai layanan digital dari kebutuhan yang paling penting, lalu mengembangkannya secara bertahap.">
    <meta name="theme-color" content="#f8fafc">
    <meta property="og:title" content="Pesantren.online — Teknologi yang mengikuti kebutuhan pesantren">
    <meta property="og:description" content="Mulai dari profil dan website resmi. Tambahkan modul ketika pesantren siap.">
    <meta property="og:type" content="website">
    <meta property="og:url" content="https://pesantren.online">
    <link rel="canonical" href="https://pesantren.online">
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=plus-jakarta-sans:400,500,600,700,800" rel="stylesheet">
    <title>Pesantren.online — Mulai dari kebutuhan pesantren</title>
    <style>
        :root {
            --po-brand: #047857;
            --po-brand-hover: #065f46;
            --po-brand-soft: #ecfdf5;
            --po-ink: #0f172a;
            --po-text: #334155;
            --po-muted: #475569;
            --po-border: #e2e8f0;
            --po-surface: #fff;
            --po-canvas: #f8fafc;
            --po-cream: #fffaf3;
            --po-gold: #c9a24a;
            --po-font: 'Plus Jakarta Sans', ui-sans-serif, system-ui, sans-serif;
            --po-shadow: 0 18px 45px -32px rgba(15, 23, 42, .28);
        }

        *, *::before, *::after { box-sizing: border-box; }
        html { scroll-behavior: smooth; scroll-padding-top: 92px; }
        body { margin: 0; color: var(--po-text); background: #fff; font: 400 16px/1.65 var(--po-font); -webkit-font-smoothing: antialiased; }
        a { color: inherit; text-decoration: none; }
        a:hover { color: var(--po-brand); }
        a:focus-visible, summary:focus-visible { outline: 3px solid #d5a637; outline-offset: 3px; border-radius: 8px; }
        img, svg { display: block; }
        .container { width: min(1120px, calc(100% - 40px)); margin-inline: auto; }
        .skip-link { position: fixed; z-index: 100; top: 10px; left: 10px; transform: translateY(-160%); padding: 10px 16px; color: #fff; background: var(--po-ink); border-radius: 8px; }
        .skip-link:focus { transform: translateY(0); color: #fff; }

        .site-header { position: sticky; z-index: 20; top: 0; border-bottom: 1px solid rgba(226, 232, 240, .9); background: rgba(255, 255, 255, .96); backdrop-filter: blur(14px); }
        .nav { min-height: 76px; display: flex; align-items: center; justify-content: space-between; gap: 24px; }
        .brand { display: inline-flex; align-items: center; gap: 10px; color: var(--po-ink); font-size: 17px; font-weight: 800; letter-spacing: -.04em; white-space: nowrap; }
        .brand-mark { width: 34px; height: 34px; display: grid; place-items: center; border-radius: 10px; background: var(--po-ink); color: #fff; font-size: 17px; }
        .brand-dot { color: var(--po-gold); }
        .nav-links, .nav-actions { display: flex; align-items: center; gap: 24px; }
        .nav-links a { color: var(--po-muted); font-size: 14px; font-weight: 600; }
        .nav-links a:hover { color: var(--po-brand); }
        .nav-actions { gap: 10px; }
        .mobile-menu { display: none; position: relative; }
        .mobile-menu summary { cursor: pointer; list-style: none; padding: 10px 12px; border: 1px solid var(--po-border); border-radius: 10px; color: var(--po-ink); font-weight: 700; }
        .mobile-menu summary::-webkit-details-marker { display: none; }
        .mobile-menu nav { position: absolute; top: calc(100% + 8px); right: 0; width: min(280px, calc(100vw - 40px)); display: grid; gap: 4px; padding: 10px; border: 1px solid var(--po-border); border-radius: 14px; background: #fff; box-shadow: var(--po-shadow); }
        .mobile-menu nav a { padding: 11px 12px; border-radius: 8px; font-size: 14px; font-weight: 600; }
        .mobile-menu nav a:hover { background: var(--po-brand-soft); }

        .button { min-height: 46px; display: inline-flex; justify-content: center; align-items: center; gap: 9px; padding: 11px 18px; border: 1px solid transparent; border-radius: 10px; font-size: 14px; font-weight: 700; line-height: 1.2; transition: background-color 160ms ease, border-color 160ms ease, color 160ms ease, transform 160ms ease; cursor: pointer; }
        .button:hover { transform: translateY(-1px); }
        .button-primary { color: #fff; background: var(--po-brand); }
        .button-primary:hover { color: #fff; background: var(--po-brand-hover); }
        .button-secondary { border-color: var(--po-border); color: var(--po-ink); background: #fff; }
        .button-secondary:hover { border-color: var(--po-brand); color: var(--po-brand); }
        .button-white { color: var(--po-ink); background: #fff; }
        .button-white:hover { color: var(--po-brand); background: #f8fafc; }

        .hero { overflow: hidden; padding: 80px 0 76px; background: radial-gradient(ellipse at 78% 16%, rgba(16, 185, 129, .1), transparent 34%), linear-gradient(180deg, #fbfdfc 0%, #fff 100%); }
        .hero-grid { display: grid; grid-template-columns: minmax(0, 1.12fr) minmax(320px, .88fr); align-items: center; gap: clamp(40px, 7vw, 92px); }
        .eyebrow { display: inline-flex; align-items: center; gap: 9px; color: var(--po-brand); font-size: 12px; font-weight: 800; letter-spacing: .09em; text-transform: uppercase; }
        .eyebrow::before { width: 22px; height: 2px; background: var(--po-gold); content: ''; }
        h1, h2, h3, p { margin-top: 0; }
        h1, h2, h3 { color: var(--po-ink); letter-spacing: -.04em; }
        h1 { max-width: 680px; margin: 20px 0 18px; font-size: clamp(38px, 5.2vw, 61px); font-weight: 800; line-height: 1.1; }
        h1 span { color: var(--po-brand); }
        .hero-copy { max-width: 620px; margin: 0; color: var(--po-muted); font-size: clamp(16px, 1.7vw, 18px); }
        .hero-actions { display: flex; flex-wrap: wrap; gap: 12px; margin-top: 30px; }
        .hero-note { display: flex; align-items: flex-start; gap: 9px; max-width: 520px; margin-top: 18px; color: var(--po-muted); font-size: 13px; }
        .hero-note svg { flex: 0 0 auto; margin-top: 3px; color: var(--po-brand); }
        .flow-card { padding: 25px; border: 1px solid var(--po-border); border-radius: 18px; background: rgba(255,255,255,.96); box-shadow: 0 28px 70px -48px rgba(15,23,42,.38); }
        .flow-card-top { display: flex; align-items: center; justify-content: space-between; gap: 16px; padding-bottom: 20px; border-bottom: 1px solid var(--po-border); }
        .flow-label { margin: 0 0 3px; color: var(--po-muted); font-size: 12px; font-weight: 700; }
        .flow-title { margin: 0; font-size: 19px; font-weight: 800; }
        .flow-seal { width: 38px; height: 38px; display: grid; place-items: center; border-radius: 12px; background: var(--po-brand-soft); color: var(--po-brand); }
        .flow-list { display: grid; gap: 0; padding: 18px 0 4px; }
        .flow-step { display: grid; grid-template-columns: 36px 1fr; gap: 13px; padding: 13px 0; }
        .flow-number { width: 34px; height: 34px; display: grid; place-items: center; border: 1px solid #bbf7d0; border-radius: 50%; background: var(--po-brand-soft); color: var(--po-brand); font-size: 13px; font-weight: 800; }
        .flow-step h3 { margin: 1px 0 3px; font-size: 14px; letter-spacing: -.01em; }
        .flow-step p { margin: 0; color: var(--po-muted); font-size: 13px; line-height: 1.5; }
        .flow-foot { padding: 13px 14px; border-radius: 10px; background: var(--po-cream); color: #5e4b24; font-size: 12px; }
        .flow-foot strong { color: var(--po-ink); }

        .section { padding: 78px 0; }
        .section-soft { background: var(--po-canvas); }
        .section-warm { background: var(--po-cream); }
        .section-heading { max-width: 720px; margin: 0 auto 38px; text-align: center; }
        .section-heading .eyebrow { justify-content: center; }
        h2 { margin: 14px 0 12px; font-size: clamp(29px, 3.7vw, 42px); line-height: 1.2; font-weight: 800; }
        .section-heading > p { max-width: 610px; margin: 0 auto; color: var(--po-muted); }
        .grid-3 { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 18px; }
        .feature-card { padding: 23px; border: 1px solid var(--po-border); border-radius: 14px; background: #fff; box-shadow: 0 5px 18px -16px rgba(15,23,42,.24); }
        .feature-mark { width: 42px; height: 42px; display: grid; place-items: center; margin-bottom: 19px; border-radius: 12px; background: var(--po-brand-soft); color: var(--po-brand); }
        .feature-card h3 { margin: 0 0 8px; font-size: 17px; letter-spacing: -.02em; }
        .feature-card p { margin: 0; color: var(--po-muted); font-size: 14px; }

        .two-spaces { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 18px; }
        .space-card { min-height: 230px; display: flex; flex-direction: column; align-items: flex-start; padding: 26px; border: 1px solid var(--po-border); border-radius: 16px; background: #fff; }
        .space-card.dark { border-color: var(--po-ink); color: #e2e8f0; background: var(--po-ink); }
        .space-tag { display: inline-flex; align-items: center; gap: 7px; padding: 6px 10px; border: 1px solid var(--po-border); border-radius: 999px; color: var(--po-muted); font-size: 11px; font-weight: 800; letter-spacing: .05em; text-transform: uppercase; }
        .dark .space-tag { border-color: #475569; color: #cbd5e1; }
        .space-card h3 { margin: 18px 0 8px; font-size: 21px; }
        .dark h3 { color: #fff; }
        .space-card p { max-width: 460px; margin: 0; color: var(--po-muted); font-size: 14px; }
        .dark p { color: #cbd5e1; }

        .steps { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 24px; counter-reset: onboarding; }
        .step { position: relative; padding: 0 20px 0 0; }
        .step::before { counter-increment: onboarding; content: '0' counter(onboarding); display: block; margin-bottom: 18px; color: var(--po-brand); font-size: 14px; font-weight: 800; letter-spacing: .08em; }
        .step h3 { margin-bottom: 8px; font-size: 18px; }
        .step p { margin: 0; color: var(--po-muted); font-size: 14px; }
        .section-action { display: flex; justify-content: center; margin-top: 30px; }

        .faq { max-width: 810px; margin-inline: auto; border-top: 1px solid var(--po-border); }
        .faq details { border-bottom: 1px solid var(--po-border); }
        .faq summary { min-height: 58px; display: flex; align-items: center; justify-content: space-between; gap: 18px; padding: 14px 2px; color: var(--po-ink); font-size: 15px; font-weight: 700; cursor: pointer; list-style: none; }
        .faq summary::-webkit-details-marker { display: none; }
        .faq summary::after { width: 24px; height: 24px; display: grid; place-items: center; border: 1px solid var(--po-border); border-radius: 50%; color: var(--po-brand); content: '+'; font-size: 17px; line-height: 1; }
        .faq details[open] summary::after { content: '−'; }
        .faq details p { max-width: 710px; margin: 0; padding: 0 0 18px; color: var(--po-muted); font-size: 14px; }

        .final-cta { padding: 62px 0; color: #fff; background: var(--po-ink); }
        .cta-inner { display: flex; align-items: center; justify-content: space-between; gap: 30px; }
        .final-cta h2 { max-width: 620px; margin: 0 0 9px; color: #fff; font-size: clamp(26px, 3.5vw, 38px); }
        .final-cta p { max-width: 600px; margin: 0; color: #cbd5e1; }
        .cta-actions { display: flex; flex: 0 0 auto; flex-wrap: wrap; gap: 10px; }
        .button-outline-light { border-color: #64748b; color: #fff; background: transparent; }
        .button-outline-light:hover { border-color: #fff; color: #fff; background: rgba(255,255,255,.08); }

        footer { padding: 42px 0 24px; background: #080f1d; color: #cbd5e1; }
        .footer-top { display: grid; grid-template-columns: 1.5fr 1fr 1fr; gap: 30px; padding-bottom: 28px; border-bottom: 1px solid #273449; }
        .footer-brand .brand { color: #fff; }
        .footer-brand p { max-width: 370px; margin: 14px 0 0; color: #94a3b8; font-size: 13px; }
        .footer-col h3 { margin: 0 0 12px; color: #fff; font-size: 12px; letter-spacing: .06em; text-transform: uppercase; }
        .footer-col a { display: block; width: fit-content; margin: 8px 0; color: #cbd5e1; font-size: 13px; }
        .footer-bottom { display: flex; justify-content: space-between; gap: 12px; padding-top: 18px; color: #94a3b8; font-size: 12px; }
        .footer-bottom a { color: #a7f3d0; }

        @media (max-width: 900px) {
            .nav-links { display: none; }
            .mobile-menu { display: block; }
            .hero-grid { grid-template-columns: 1fr; gap: 36px; }
            .flow-card { max-width: 620px; }
            .grid-3 { grid-template-columns: repeat(2, minmax(0, 1fr)); }
            .cta-inner { align-items: flex-start; flex-direction: column; }
        }
        @media (max-width: 620px) {
            .container { width: min(100% - 32px, 1120px); }
            .nav { min-height: 68px; gap: 8px; }
            .brand { font-size: 15px; gap: 8px; }
            .brand-mark { width: 31px; height: 31px; }
            .nav-actions { gap: 7px; }
            .nav-actions .button { min-height: 42px; padding: 9px 11px; font-size: 12px; }
            .nav-actions .button-secondary { display: none; }
            .hero { padding: 54px 0 56px; }
            .hero-grid { gap: 30px; }
            h1 { font-size: clamp(36px, 10vw, 46px); }
            .hero-actions .button { flex: 1 1 auto; }
            .flow-card { padding: 20px; }
            .section { padding: 58px 0; }
            .grid-3, .two-spaces, .steps { grid-template-columns: 1fr; }
            .feature-card { padding: 20px; }
            .space-card { min-height: 0; }
            .steps { gap: 25px; }
            .step { padding-right: 0; }
            .cta-actions { width: 100%; }
            .cta-actions .button { flex: 1 1 160px; }
            .footer-top { grid-template-columns: 1fr 1fr; gap: 24px 18px; }
            .footer-brand { grid-column: 1 / -1; }
            .footer-bottom { flex-direction: column; }
        }
        @media (prefers-reduced-motion: reduce) {
            *, *::before, *::after { scroll-behavior: auto !important; transition-duration: .01ms !important; animation-duration: .01ms !important; animation-iteration-count: 1 !important; }
        }
    </style>
</head>
<body>
<a class="skip-link" href="#main">Lewati ke isi utama</a>

@php
    $demo = 'https://demo.pesantren.online';
    $wa = 'https://wa.me/6285572711197?text=' . rawurlencode('Assalamu’alaikum, saya ingin berdiskusi tentang kebutuhan digital pesantren.');
@endphp

<header class="site-header">
    <div class="container nav">
        <a class="brand" href="/" aria-label="Pesantren.online, beranda">
            <span class="brand-mark" aria-hidden="true">P</span>
            <span>Pesantren<span class="brand-dot">.</span>online</span>
        </a>
        <nav class="nav-links" aria-label="Navigasi utama">
            <a href="#layanan">Layanan</a>
            <a href="#cara-kerja">Cara kerja</a>
            <a href="#pertanyaan">Pertanyaan</a>
        </nav>
        <details class="mobile-menu">
            <summary aria-label="Buka menu">Menu</summary>
            <nav aria-label="Navigasi ponsel">
                <a href="#layanan">Layanan</a>
                <a href="#cara-kerja">Cara kerja</a>
                <a href="#pertanyaan">Pertanyaan</a>
                <a href="/kontak">Hubungi kami</a>
            </nav>
        </details>
        <div class="nav-actions">
            <a class="button button-secondary" href="/platform">Masuk</a>
            <a class="button button-primary" href="{{ $wa }}" target="_blank" rel="noopener noreferrer">Konsultasi</a>
        </div>
    </div>
</header>

<main id="main">
    <section class="hero">
        <div class="container hero-grid">
            <div>
                <span class="eyebrow">Teknologi untuk pesantren</span>
                <h1>Mulai dari kebutuhan pesantren <span>hari ini.</span></h1>
                <p class="hero-copy">Pesantren.online membantu menata layanan digital pondok secara bertahap. Siapkan profil dan website resmi terlebih dahulu; pilih modul lain ketika kebutuhan dan kesiapan tim sudah jelas.</p>
                <div class="hero-actions">
                    <a class="button button-primary" href="{{ $wa }}" target="_blank" rel="noopener noreferrer">Diskusikan kebutuhan</a>
                    <a class="button button-secondary" href="{{ $demo }}" target="_blank" rel="noopener noreferrer">Lihat demo</a>
                </div>
                <p class="hero-note">
                    <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M12 8v4l2.5 2.5"/><circle cx="12" cy="12" r="9"/></svg>
                    <span>Belum perlu memilih semua modul atau memahami pengaturan teknis sebelum mulai berdiskusi.</span>
                </p>
            </div>

            <aside class="flow-card" aria-label="Gambaran langkah awal">
                <div class="flow-card-top">
                    <div>
                        <p class="flow-label">Gambaran langkah awal</p>
                        <h2 class="flow-title">Mulai dengan yang pokok</h2>
                    </div>
                    <span class="flow-seal" aria-hidden="true">
                        <svg width="21" height="21" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="m5 12 4 4L19 6"/></svg>
                    </span>
                </div>
                <div class="flow-list">
                    <div class="flow-step"><span class="flow-number">1</span><div><h3>Kenali kebutuhan pondok</h3><p>Pilih pekerjaan yang paling ingin dirapikan lebih dulu.</p></div></div>
                    <div class="flow-step"><span class="flow-number">2</span><div><h3>Siapkan identitas dan website</h3><p>Susun informasi resmi agar mudah ditemukan keluarga dan masyarakat.</p></div></div>
                    <div class="flow-step"><span class="flow-number">3</span><div><h3>Tambah layanan saat siap</h3><p>Aktifkan modul sesuai kebutuhan dan kesiapan pengelola.</p></div></div>
                </div>
                <div class="flow-foot"><strong>Anda tetap menentukan urutannya.</strong> Tim kami dapat membantu membahas langkah berikutnya.</div>
            </aside>
        </div>
    </section>

    <section class="section" id="layanan">
        <div class="container">
            <div class="section-heading">
                <span class="eyebrow">Satu ruang layanan</span>
                <h2>Rapikan pekerjaan yang paling penting dahulu.</h2>
                <p>Mulai dari layanan yang dibutuhkan. Bagian lain dapat menyusul setelah alur dan penanggung jawabnya siap.</p>
            </div>
            <div class="grid-3">
                <article class="feature-card">
                    <span class="feature-mark" aria-hidden="true"><svg width="21" height="21" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M4 20h16M6 20V8l6-4 6 4v12M9 11h.01M15 11h.01M9 15h.01M15 15h.01M11 20v-3h2v3"/></svg></span>
                    <h3>Informasi dan website pondok</h3>
                    <p>Kelola profil, kabar, galeri, serta informasi penerimaan melalui website publik pesantren.</p>
                </article>
                <article class="feature-card">
                    <span class="feature-mark" aria-hidden="true"><svg width="21" height="21" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M8 4h8l3 3v13H5V4h3ZM8 10h8M8 14h8M8 17h5"/></svg></span>
                    <h3>Administrasi pesantren</h3>
                    <p>Modul kesantrian, keuangan, akademik, asrama, dan tahfidz tersedia sesuai kebutuhan lembaga.</p>
                </article>
                <article class="feature-card">
                    <span class="feature-mark" aria-hidden="true"><svg width="21" height="21" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M4 5h16v12H8l-4 3V5Z"/><path d="M8 9h8M8 13h5"/></svg></span>
                    <h3>Layanan calon santri dan keluarga</h3>
                    <p>Website dapat menjadi pintu informasi, sementara layanan PPDB dan portal keluarga mengikuti kesiapan pondok.</p>
                </article>
            </div>
        </div>
    </section>

    <section class="section section-soft">
        <div class="container">
            <div class="section-heading">
                <span class="eyebrow">Dua ruang yang berbeda</span>
                <h2>Wajah publik dan ruang kerja tidak dicampur.</h2>
                <p>Pengunjung mendapatkan informasi resmi. Pengelola masuk ke area khusus untuk mengatur layanan pesantren.</p>
            </div>
            <div class="two-spaces">
                <article class="space-card">
                    <span class="space-tag">Terbuka untuk umum</span>
                    <h3>Website pesantren</h3>
                    <p>Tempat keluarga dan masyarakat melihat profil, berita, galeri, dan informasi yang memang dipublikasikan pondok.</p>
                </article>
                <article class="space-card dark">
                    <span class="space-tag">Untuk pengelola</span>
                    <h3>Ruang kerja pesantren</h3>
                    <p>Area masuk untuk staf yang berwenang. Pengaturan dan data operasional mengikuti peran serta layanan yang diaktifkan.</p>
                </article>
            </div>
        </div>
    </section>

    <section class="section section-warm" id="cara-kerja">
        <div class="container">
            <div class="section-heading">
                <span class="eyebrow">Cara memulai</span>
                <h2>Tidak harus beres semuanya sekaligus.</h2>
                <p>Mulai dengan percakapan yang sederhana. Kita petakan proses pesantren sebelum menentukan konfigurasi.</p>
            </div>
            <div class="steps">
                <article class="step"><h3>Ceritakan kondisi pondok</h3><p>Bagian mana yang paling menyita waktu? Siapa yang akan mengelolanya?</p></article>
                <article class="step"><h3>Tentukan langkah awal</h3><p>Susun profil, website, domain, dan kebutuhan layanan yang paling mendesak.</p></article>
                <article class="step"><h3>Tambah kemampuan bertahap</h3><p>Tinjau kesiapan bersama sebelum mengaktifkan modul dan melibatkan lebih banyak pengguna.</p></article>
            </div>
            <div class="section-action"><a class="button button-primary" href="{{ $wa }}" target="_blank" rel="noopener noreferrer">Mulai percakapan</a></div>
        </div>
    </section>

    <section class="section" id="pertanyaan">
        <div class="container">
            <div class="section-heading">
                <span class="eyebrow">Pertanyaan umum</span>
                <h2>Hal yang perlu diketahui sebelum mulai.</h2>
            </div>
            <div class="faq">
                <details><summary>Apakah pesantren harus memakai semua modul?</summary><p>Tidak. Modul dapat ditinjau dan diaktifkan bertahap. Mulailah dari pekerjaan dan kemampuan tim yang paling siap.</p></details>
                <details><summary>Bagaimana jika pondok sudah memiliki website?</summary><p>Ceritakan pengelolaan dan domain yang digunakan. Cara melanjutkan atau menghubungkan layanan akan dibahas berdasarkan kondisi situs saat ini.</p></details>
                <details><summary>Apakah kami harus memahami DNS dan konfigurasi server?</summary><p>Anda tidak perlu mempelajari istilah teknis sebelum berdiskusi. Kami akan menjelaskan langkah yang diperlukan untuk kondisi pesantren Anda.</p></details>
                <details><summary>Bagaimana mengetahui biaya dan dukungan yang tersedia?</summary><p>Hubungi tim Pesantren.online untuk membahas kebutuhan, model layanan, dukungan, dan biaya yang berlaku sebelum memutuskan.</p></details>
            </div>
        </div>
    </section>

    <section class="final-cta">
        <div class="container cta-inner">
            <div>
                <h2>Mulai dari satu kebutuhan yang ingin diringankan.</h2>
                <p>Ceritakan keadaan pesantren. Kita tentukan langkah yang paling masuk akal untuk dimulai.</p>
            </div>
            <div class="cta-actions">
                <a class="button button-white" href="{{ $wa }}" target="_blank" rel="noopener noreferrer">Konsultasi via WhatsApp</a>
                <a class="button button-outline-light" href="{{ $demo }}" target="_blank" rel="noopener noreferrer">Lihat demo</a>
            </div>
        </div>
    </section>
</main>

<footer>
    <div class="container">
        <div class="footer-top">
            <div class="footer-brand">
                <a class="brand" href="/" aria-label="Pesantren.online, beranda"><span class="brand-mark" aria-hidden="true">P</span><span>Pesantren<span class="brand-dot">.</span>online</span></a>
                <p>Teknologi untuk membantu pesantren mengelola layanan digital dengan langkah yang sesuai kebutuhan.</p>
            </div>
            <div class="footer-col"><h3>Produk</h3><a href="#layanan">Layanan</a><a href="#cara-kerja">Cara kerja</a><a href="{{ $demo }}" target="_blank" rel="noopener noreferrer">Demo</a><a href="/platform">Masuk</a></div>
            <div class="footer-col"><h3>Informasi</h3><a href="/kontak">Kontak</a><a href="{{ route('legal.privacy') }}">Kebijakan privasi</a><a href="{{ route('legal.terms') }}">Syarat layanan</a><a href="{{ route('legal.disclaimer') }}">Disclaimer</a></div>
        </div>
        <div class="footer-bottom">
            <span>Pesantren.online &copy; {{ date('Y') }} <a href="https://brontolano.com" target="_blank" rel="noopener noreferrer">PT Rojo Bronto Lano</a></span>
            <span>Assalamu'alaikum warahmatullah</span>
        </div>
    </div>
</footer>
</body>
</html>
