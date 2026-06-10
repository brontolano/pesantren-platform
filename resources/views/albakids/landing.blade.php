<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Albakids Preschool — Al-Basyariyah Kinder Preschool | Pendaftaran 2026/2027</title>
    <link rel="icon" type="image/png" href="/albakids/logo.png">
    <meta name="description" content="Albakids Preschool — bermain & belajar dengan pendekatan Islamic Montessori berlandaskan tauhid & sunnah Nabi Muhammad SAW. Pendaftaran Tahun Ajaran 2026/2027 dibuka.">
    <meta property="og:title" content="Albakids Preschool — Pendaftaran 2026/2027">
    <meta property="og:description" content="Islamic Montessori Inspired Preschool di Ponpes Al-Basyariyah, Margaasih, Bandung.">
    <meta property="og:image" content="/albakids-brochure/1.jpg">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Baloo+2:wght@500;600;700;800&family=Nunito:wght@400;600;700;800&family=Pacifico&display=swap" rel="stylesheet">
    <style>
        :root{
            --orange:#ec7d2e; --orange-d:#d96a1e;
            --blue:#2ba9d6; --blue-d:#1c8fbb;
            --coral:#f4998c; --coral-d:#ef7c6c;
            --pink:#fbded9; --pink-2:#fcecdf;
            --yellow:#fbd45e; --yellow-soft:#fde9a6;
            --green:#84c344; --green-d:#5fa030; --green-text:#2e7d52;
            --cream:#fffaf2; --sky:#e9f6fc; --sky-2:#d6eefb;
            --ink:#4c4540; --ink-soft:#7c726b; --brown:#b85c2a;
            --shadow:0 18px 40px -22px rgba(80,60,30,.45);
            --shadow-sm:0 10px 24px -16px rgba(80,60,30,.4);
        }
        *{box-sizing:border-box}
        html{scroll-behavior:smooth}
        body{margin:0;font-family:'Nunito',system-ui,sans-serif;color:var(--ink);background:var(--cream);overflow-x:hidden;line-height:1.6}
        h1,h2,h3,h4,.display{font-family:'Baloo 2','Nunito',sans-serif;line-height:1.12;margin:0}
        .script{font-family:'Pacifico',cursive;font-weight:400}
        a{color:inherit;text-decoration:none}
        img{max-width:100%;display:block}
        .wrap{width:min(1140px,92vw);margin-inline:auto}
        section{position:relative}
        .eyebrow{display:inline-block;font-family:'Baloo 2';font-weight:700;letter-spacing:.04em;color:var(--blue);
            background:#fff;border:2px solid var(--sky-2);padding:.35em 1.1em;border-radius:999px;font-size:.82rem;box-shadow:var(--shadow-sm)}
        .sec-title{font-size:clamp(1.7rem,4.4vw,2.7rem);font-weight:800;color:var(--orange);text-align:center}
        .sec-title .b{color:var(--blue)}
        .sec-sub{text-align:center;max-width:620px;margin:.8rem auto 0;color:var(--ink-soft);font-weight:600}
        .pad{padding:clamp(3.2rem,7vw,5.5rem) 0}

        /* buttons */
        .btn{display:inline-flex;align-items:center;gap:.5em;font-family:'Baloo 2';font-weight:700;font-size:1.02rem;
            padding:.8em 1.6em;border-radius:999px;border:none;cursor:pointer;transition:transform .15s ease,box-shadow .15s ease;line-height:1}
        .btn:hover{transform:translateY(-2px)}
        .btn-orange{background:var(--orange);color:#fff;box-shadow:0 12px 22px -10px var(--orange)}
        .btn-blue{background:var(--blue);color:#fff;box-shadow:0 12px 22px -10px var(--blue)}
        .btn-white{background:#fff;color:var(--orange);box-shadow:var(--shadow-sm)}
        .btn-green{background:var(--green);color:#fff;box-shadow:0 12px 22px -10px var(--green)}
        .btn-lg{font-size:1.12rem;padding:.95em 2em}

        /* decorations */
        .cloud{position:absolute;background:#fff;border-radius:100px;opacity:.9;filter:drop-shadow(0 8px 10px rgba(120,160,200,.18))}
        .cloud::before,.cloud::after{content:"";position:absolute;background:#fff;border-radius:50%}
        .star{position:absolute;color:var(--yellow);font-size:1.4rem;filter:drop-shadow(0 2px 1px rgba(0,0,0,.06))}
        .heart{position:absolute;color:var(--coral)}
        .grass{position:absolute;left:0;right:0;bottom:-1px;width:100%;display:block;line-height:0}

        /* ============ NAV ============ */
        header.nav{position:sticky;top:0;z-index:50;background:rgba(255,250,242,.92);backdrop-filter:blur(8px);
            border-bottom:1px solid #f0e4d3}
        .nav-in{display:flex;align-items:center;justify-content:space-between;padding:.6rem 0;gap:1rem}
        .brand{display:flex;align-items:center;gap:.6rem;font-family:'Baloo 2';font-weight:800;font-size:1.25rem}
        .brand .logo{width:48px;height:48px;border-radius:50%;background:#fff;border:2px solid #fbe6cf;display:grid;place-items:center;overflow:hidden;box-shadow:var(--shadow-sm)}
        .brand .logo img{width:100%;height:100%;object-fit:contain;padding:2px}
        .brand .o{color:var(--orange)} .brand .bl{color:var(--blue)}
        .nav-links{display:flex;gap:1.4rem;align-items:center;font-weight:700;font-family:'Baloo 2'}
        .nav-links a{color:var(--ink);transition:color .15s}
        .nav-links a:hover{color:var(--orange)}
        .nav-cta{display:flex;gap:.6rem;align-items:center}
        .burger{display:none;background:none;border:none;font-size:1.6rem;color:var(--orange);cursor:pointer}
        @media(max-width:900px){
            .nav-links{display:none}
            .burger{display:block}
            .nav-cta .btn:not(.burger){display:none}
        }

        /* ============ HERO ============ */
        .hero{background:linear-gradient(180deg,var(--sky) 0%,#f3fbfe 55%,var(--cream) 100%);padding:clamp(2.4rem,6vw,4.5rem) 0 6rem;overflow:hidden}
        .hero-grid{display:grid;grid-template-columns:1.05fr .95fr;gap:2.5rem;align-items:center}
        .hero h1{font-size:clamp(2.6rem,7vw,4.6rem);font-weight:800}
        .hero h1 .a{color:var(--orange);display:block}
        .hero h1 .p{color:var(--blue);display:block}
        .hero .badge{display:inline-block;background:var(--yellow);color:#7a5a12;font-family:'Baloo 2';font-weight:700;
            padding:.4em 1.2em;border-radius:999px;font-size:1rem;margin-bottom:1.1rem;box-shadow:var(--shadow-sm)}
        .hero p.lead{font-size:1.12rem;color:var(--ink-soft);font-weight:600;margin:1.3rem 0 1.8rem;max-width:34ch}
        .hero-cta{display:flex;gap:.8rem;flex-wrap:wrap}
        .hero-art{position:relative}
        .hero-art .photo{border-radius:36px 36px 36px 90px;overflow:hidden;box-shadow:var(--shadow);border:6px solid #fff;rotate:1.5deg}
        .hero-art .photo img{width:100%;aspect-ratio:3/3.2;object-fit:cover}
        .hero-pill{position:absolute;background:#fff;border-radius:18px;padding:.7rem 1rem;box-shadow:var(--shadow);display:flex;align-items:center;gap:.55rem;font-family:'Baloo 2';font-weight:700}
        .hero-pill .ic{width:34px;height:34px;border-radius:10px;display:grid;place-items:center;color:#fff;font-size:1.05rem}
        @media(max-width:860px){
            .hero-grid{grid-template-columns:1fr;text-align:center}
            .hero p.lead{margin-inline:auto}
            .hero-cta{justify-content:center}
            .hero-art{max-width:420px;margin:0 auto}
        }

        /* ============ MARQUEE values ============ */
        .strip{background:var(--orange);color:#fff;padding:.9rem 0;overflow:hidden;white-space:nowrap}
        .strip .track{display:inline-flex;gap:2.5rem;animation:slide 22s linear infinite;font-family:'Baloo 2';font-weight:700}
        .strip span{display:inline-flex;align-items:center;gap:.6rem}
        @keyframes slide{from{transform:translateX(0)}to{transform:translateX(-50%)}}

        /* ============ cloud card ============ */
        .cloudcard{background:#fff;border-radius:34px;padding:2rem 1.8rem;box-shadow:var(--shadow);position:relative;text-align:center}
        .info-grid{display:grid;grid-template-columns:1fr 1fr;gap:1.5rem;margin-top:2.6rem}
        .info-grid .cloudcard h3{font-size:1.5rem;color:var(--orange);margin-bottom:.5rem}
        .info-grid .big{font-size:2.2rem;font-weight:800;font-family:'Baloo 2';color:var(--blue)}
        .info-ic{width:64px;height:64px;border-radius:20px;display:grid;place-items:center;margin:0 auto 1rem;font-size:1.8rem;color:#fff}
        @media(max-width:680px){.info-grid{grid-template-columns:1fr}}

        /* ============ curriculum ============ */
        .curri{background:linear-gradient(180deg,var(--cream),#fff)}
        .curri-grid{display:grid;grid-template-columns:1.1fr .9fr;gap:2.2rem;align-items:center;margin-top:2.4rem}
        .curri-card{background:var(--yellow-soft);border-radius:30px;padding:2rem;box-shadow:var(--shadow-sm)}
        .curri-card ul{list-style:none;margin:0;padding:0;display:grid;gap:.85rem}
        .curri-card li{display:flex;gap:.7rem;align-items:flex-start;font-weight:700;color:#6b5a2a}
        .curri-card li .tick{flex:none;width:26px;height:26px;border-radius:50%;background:var(--orange);color:#fff;display:grid;place-items:center;font-size:.85rem;margin-top:1px}
        .curri-photos{display:grid;grid-template-columns:1fr 1fr;gap:1rem}
        .curri-photos img{border-radius:24px;border:5px solid #fff;box-shadow:var(--shadow-sm);aspect-ratio:1;object-fit:cover;width:100%}
        .curri-photos img:first-child{grid-column:1/-1;aspect-ratio:2/1.05}
        @media(max-width:780px){.curri-grid{grid-template-columns:1fr}}

        /* ============ why grid ============ */
        .why{background:linear-gradient(180deg,#fff,var(--sky))}
        .why-grid{display:grid;grid-template-columns:repeat(4,1fr);gap:1.2rem;margin-top:2.6rem}
        .why-card{background:#fff;border-radius:24px;padding:1.5rem 1.2rem;box-shadow:var(--shadow-sm);text-align:center;transition:transform .18s}
        .why-card:hover{transform:translateY(-6px)}
        .why-card .emo{width:58px;height:58px;border-radius:18px;display:grid;place-items:center;font-size:1.6rem;margin:0 auto .9rem}
        .why-card h4{font-size:1.05rem;color:var(--ink)}
        .why-card p{font-size:.92rem;color:var(--ink-soft);margin:.4rem 0 0;font-weight:600}
        @media(max-width:980px){.why-grid{grid-template-columns:repeat(2,1fr)}}
        @media(max-width:520px){.why-grid{grid-template-columns:1fr}}

        /* ============ fees ============ */
        .fees{background:linear-gradient(180deg,var(--sky),#fff)}
        .fee-tabs{display:flex;gap:.6rem;justify-content:center;margin:1.8rem 0 1.6rem;flex-wrap:wrap}
        .fee-tab{font-family:'Baloo 2';font-weight:700;padding:.6em 1.4em;border-radius:999px;border:2px solid var(--coral);
            background:#fff;color:var(--coral-d);cursor:pointer;transition:.15s}
        .fee-tab.active{background:var(--coral);color:#fff}
        .fee-card{max-width:620px;margin:0 auto;background:#fff;border-radius:28px;overflow:hidden;box-shadow:var(--shadow)}
        .fee-row{display:flex;justify-content:space-between;gap:1rem;padding:.95rem 1.5rem;font-weight:700}
        .fee-row:nth-child(odd){background:var(--pink)}
        .fee-row:nth-child(even){background:#fff5f2}
        .fee-head{background:var(--coral)!important;color:#fff;font-family:'Baloo 2';font-size:1.1rem;letter-spacing:.03em}
        .fee-total{background:var(--coral)!important;color:#fff;font-family:'Baloo 2';font-size:1.25rem}
        .fee-row .amt{font-variant-numeric:tabular-nums;white-space:nowrap}
        .fee-nb{max-width:620px;margin:1.1rem auto 0;text-align:center;color:var(--ink-soft);font-weight:600;font-size:.95rem}
        .fee-pane{display:none}.fee-pane.active{display:block}

        /* ============ steps ============ */
        .steps{background:#fff}
        .steps-grid{display:grid;grid-template-columns:repeat(2,1fr);gap:1.4rem;margin-top:2.4rem}
        .step{display:flex;gap:1rem;background:var(--cream);border-radius:24px;padding:1.5rem;box-shadow:var(--shadow-sm)}
        .step .num{flex:none;width:46px;height:46px;border-radius:14px;background:var(--blue);color:#fff;display:grid;place-items:center;font-family:'Baloo 2';font-weight:800;font-size:1.25rem}
        .step h4{color:var(--orange);font-size:1.1rem;margin-bottom:.25rem}
        .step p{margin:0;color:var(--ink-soft);font-weight:600;font-size:.96rem}
        .bank{margin-top:1.8rem;background:var(--blue);color:#fff;border-radius:24px;padding:1.6rem;text-align:center;box-shadow:var(--shadow)}
        .bank .acc{font-family:'Baloo 2';font-weight:800;font-size:1.6rem;letter-spacing:.06em;margin:.3rem 0}
        @media(max-width:680px){.steps-grid{grid-template-columns:1fr}}

        /* ============ gallery ============ */
        .gallery{background:linear-gradient(180deg,#fff,var(--sky))}
        .gal-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:1rem;margin-top:2.4rem}
        .gal-grid a{border-radius:20px;overflow:hidden;box-shadow:var(--shadow-sm);border:5px solid #fff;transition:transform .18s}
        .gal-grid a:hover{transform:scale(1.02)}
        .gal-grid img{aspect-ratio:3/4;object-fit:cover;width:100%}
        @media(max-width:680px){.gal-grid{grid-template-columns:repeat(2,1fr)}}

        /* ============ form ============ */
        .reg{background:linear-gradient(180deg,var(--sky),var(--cream))}
        .form-card{max-width:820px;margin:2.4rem auto 0;background:#fff;border-radius:32px;padding:clamp(1.4rem,4vw,2.6rem);box-shadow:var(--shadow)}
        .form-group-title{font-family:'Baloo 2';font-weight:700;color:var(--blue);font-size:1.15rem;margin:1.6rem 0 .9rem;display:flex;align-items:center;gap:.5rem}
        .form-group-title:first-child{margin-top:0}
        .grid2{display:grid;grid-template-columns:1fr 1fr;gap:1rem}
        .grid3{display:grid;grid-template-columns:1fr 1fr 1fr;gap:1rem}
        .field{display:flex;flex-direction:column;gap:.35rem;margin-bottom:.2rem}
        .field.col-2{grid-column:1/-1}
        .field label{font-weight:700;font-size:.9rem;color:var(--ink)}
        .field label .req{color:var(--coral-d)}
        .field input,.field select,.field textarea{font-family:inherit;font-size:1rem;padding:.7em .9em;border:2px solid #ece3d6;border-radius:14px;background:#fffdf9;transition:border-color .15s;width:100%}
        .field input:focus,.field select:focus,.field textarea:focus{outline:none;border-color:var(--blue)}
        .field input[type=file]{padding:.5em;background:#fff}
        .hint{font-size:.8rem;color:var(--ink-soft)}
        .alert{border-radius:16px;padding:1rem 1.2rem;margin-bottom:1.4rem;font-weight:700}
        .alert-ok{background:#e7f8ec;color:#1f7a44;border:2px solid #b7e6c6}
        .alert-err{background:#fdecec;color:#c0392b;border:2px solid #f3c2bd}
        .alert-err ul{margin:.5rem 0 0;padding-left:1.2rem;font-weight:600}
        @media(max-width:680px){.grid2,.grid3{grid-template-columns:1fr}}

        /* ============ footer ============ */
        footer.ft{background:var(--green-text);color:#eafaf0;padding:clamp(3rem,6vw,4.5rem) 0 2rem;position:relative}
        .ft-grid{display:grid;grid-template-columns:1.3fr 1fr 1fr;gap:2rem}
        .ft h3.script{font-size:2rem}
        .ft h3 .a{color:var(--yellow)}
        .ft-sub{font-family:'Pacifico';color:#cdeed8;font-size:1.2rem;margin-top:-.2rem}
        .ft h4{color:#fff;font-size:1.05rem;margin-bottom:.6rem;font-family:'Baloo 2'}
        .ft a:hover{color:var(--yellow)}
        .ft .row{display:flex;gap:.6rem;margin-bottom:.5rem;color:#d4efdd;font-weight:600}
        .ft-bottom{border-top:1px solid rgba(255,255,255,.18);margin-top:2.2rem;padding-top:1.2rem;text-align:center;color:#bfe6cb;font-size:.88rem}
        @media(max-width:760px){.ft-grid{grid-template-columns:1fr;text-align:center}.ft .row{justify-content:center}}

        .float-wa{position:fixed;right:18px;bottom:18px;z-index:60;background:#25d366;color:#fff;width:58px;height:58px;border-radius:50%;
            display:grid;place-items:center;font-size:1.7rem;box-shadow:0 12px 26px -8px rgba(37,211,102,.7);transition:transform .15s}
        .float-wa:hover{transform:scale(1.08)}
    </style>
</head>
<body>
@php
    $wa = '6282260901399';
    $waText = rawurlencode('Assalamualaikum, saya ingin bertanya tentang pendaftaran Albakids Preschool 2026/2027.');
@endphp

<!-- ===================== NAV ===================== -->
<header class="nav">
    <div class="wrap nav-in">
        <a href="#top" class="brand">
            <span class="logo"><img src="/albakids/logo.png" alt="Logo Albakids Preschool"></span>
            <span><span class="o">Alba</span><span class="bl">kids</span></span>
        </a>
        <nav class="nav-links">
            <a href="#tentang">Tentang</a>
            <a href="#kurikulum">Kurikulum</a>
            <a href="#keunggulan">Keunggulan</a>
            <a href="#biaya">Biaya</a>
            <a href="#galeri">Galeri</a>
            <a href="#alur">Alur</a>
        </nav>
        <div class="nav-cta">
            <a href="#daftar" class="btn btn-orange">Daftar Sekarang</a>
            <button class="burger" onclick="document.getElementById('mnav').classList.toggle('open')" aria-label="Menu">☰</button>
        </div>
    </div>
    <div id="mnav" style="display:none" class="wrap"></div>
</header>

<!-- ===================== HERO ===================== -->
<section class="hero" id="top">
    <span class="cloud" style="width:120px;height:40px;top:8%;left:6%"></span>
    <span class="cloud" style="width:90px;height:32px;top:18%;right:10%"></span>
    <span class="star" style="top:12%;left:30%">★</span>
    <span class="heart" style="top:24%;right:24%;font-size:1.3rem">❤</span>
    <div class="wrap hero-grid">
        <div>
            <span class="badge">Tahun Ajaran 2026 / 2027</span>
            <h1><span class="a script" style="font-family:'Pacifico'">Albakids</span><span class="p">Preschool</span></h1>
            <p class="lead">Bermain & belajar dengan pendekatan <b>Islamic Montessori</b> berlandaskan nilai tauhid dan sunnah Nabi Muhammad ﷺ.</p>
            <div class="hero-cta">
                <a href="#daftar" class="btn btn-orange btn-lg">📝 Daftar Sekarang</a>
                <a href="#biaya" class="btn btn-white btn-lg">Lihat Biaya</a>
            </div>
            <div style="margin-top:1.6rem;display:flex;gap:1.6rem;flex-wrap:wrap;font-weight:700;color:var(--ink-soft)">
                <span>👶 Usia 3–5 tahun</span>
                <span>📅 Selasa &amp; Sabtu</span>
                <span>📍 Margaasih, Bandung</span>
            </div>
        </div>
        <div class="hero-art">
            <div class="photo"><img src="/albakids-brochure/1.jpg" alt="Anak-anak Albakids Preschool"></div>
            <div class="hero-pill" style="bottom:-18px;left:-10px"><span class="ic" style="background:var(--green)">🌱</span> Montessori Inspired</div>
            <div class="hero-pill" style="top:-16px;right:-6px"><span class="ic" style="background:var(--blue)">🕌</span> Tauhid &amp; Sunnah</div>
        </div>
    </div>
    <svg class="grass" viewBox="0 0 1440 90" preserveAspectRatio="none" height="70"><path fill="#84c344" d="M0,40 C180,90 360,10 540,45 C720,80 900,15 1080,45 C1260,75 1380,35 1440,50 L1440,90 L0,90 Z"></path></svg>
</section>

<!-- ===================== STRIP ===================== -->
<div class="strip">
    <div class="track">
        <span>✦ Islamic Montessori Inspired</span><span>✦ Cooking Class di Pabrik Albakery</span><span>✦ Basic English &amp; Arabic</span><span>✦ Hafalan Surat Pendek</span><span>✦ Outing Class</span>
        <span>✦ Islamic Montessori Inspired</span><span>✦ Cooking Class di Pabrik Albakery</span><span>✦ Basic English &amp; Arabic</span><span>✦ Hafalan Surat Pendek</span><span>✦ Outing Class</span>
    </div>
</div>

<!-- ===================== TENTANG ===================== -->
<section class="pad" id="tentang">
    <div class="wrap" style="text-align:center">
        <span class="eyebrow">Learn &amp; Play with Albakids</span>
        <h2 class="sec-title" style="margin-top:1rem">Tempat Tumbuh Pertama <span class="b">Si Buah Hati</span></h2>
        <p class="sec-sub">Albakids Preschool adalah Kinder Preschool di lingkungan Pondok Pesantren Al-Basyariyah. Kami memadukan kegembiraan bermain dengan penanaman tauhid sejak dini, agar ananda tumbuh ceria, mandiri, dan berakhlak.</p>
    </div>
    <div class="wrap info-grid" id="informasi">
        <div class="cloudcard">
            <div class="info-ic" style="background:var(--orange)">👶</div>
            <h3>Syarat Usia Anak</h3>
            <div class="big">3 – 5 tahun</div>
            <p style="color:var(--ink-soft);font-weight:600;margin:.5rem 0 0">Masa emas tumbuh kembang ananda.</p>
        </div>
        <div class="cloudcard">
            <div class="info-ic" style="background:var(--blue)">📅</div>
            <h3>Jadwal Sekolah</h3>
            <div class="big" style="font-size:1.7rem">Selasa &amp; Sabtu</div>
            <p style="color:var(--ink-soft);font-weight:600;margin:.5rem 0 0">Pukul 09.00 – 11.00 · 2× seminggu, agar tidak memberatkan anak usia preschool.</p>
        </div>
    </div>
</section>

<!-- ===================== KURIKULUM ===================== -->
<section class="pad curri" id="kurikulum">
    <div class="wrap" style="text-align:center">
        <span class="eyebrow">Kurikulum</span>
        <h2 class="sec-title" style="margin-top:1rem">Anak Bunda Akan <span class="b">Dibekali</span></h2>
    </div>
    <div class="wrap curri-grid">
        <div class="curri-card">
            <ul>
                <li><span class="tick">✓</span> Penerapan pemahaman <b>tauhid</b></li>
                <li><span class="tick">✓</span> Penerapan <b>sunnah Nabi Muhammad ﷺ</b> di kehidupan sehari-hari</li>
                <li><span class="tick">✓</span> Bermain kreatif untuk menstimulasi sensorik, motorik halus &amp; kasar</li>
                <li><span class="tick">✓</span> <b>Basic English</b></li>
                <li><span class="tick">✓</span> <b>Basic Arabic</b></li>
                <li><span class="tick">✓</span> Hafalan surat pendek, sholawat &amp; doa sehari-hari</li>
                <li><span class="tick">✓</span> Pengenalan membaca &amp; berhitung dasar</li>
            </ul>
        </div>
        <div class="curri-photos">
            <img src="/albakids/g1.jpg" alt="Cooking class Albakids">
            <img src="/albakids/g2.jpg" alt="Kegiatan Albakids">
            <img src="/albakids/g3.jpg" alt="Kebersamaan Albakids">
        </div>
    </div>
</section>

<!-- ===================== KEUNGGULAN ===================== -->
<section class="pad why" id="keunggulan">
    <div class="wrap" style="text-align:center">
        <span class="eyebrow">Why Albakids?</span>
        <h2 class="sec-title" style="margin-top:1rem">Kenapa Memilih <span class="b">Albakids Preschool?</span></h2>
    </div>
    <div class="wrap why-grid">
        <div class="why-card"><div class="emo" style="background:#fdeede">🧩</div><h4>Islamic Montessori</h4><p>Metode pendekatan Islamic Montessori Inspired.</p></div>
        <div class="why-card"><div class="emo" style="background:#e7f6fb">🍪</div><h4>Cooking Class</h4><p>Program memasak seru di Pabrik Albakery.</p></div>
        <div class="why-card"><div class="emo" style="background:#eaf7e0">⚽</div><h4>Olahraga &amp; Outing</h4><p>Olahraga berkeliling area pesantren &amp; outing class.</p></div>
        <div class="why-card"><div class="emo" style="background:#fdeede">🕌</div><h4>Lingkungan Pesantren</h4><p>Mengenalkan anak dengan suasana pesantren.</p></div>
        <div class="why-card"><div class="emo" style="background:#e7f6fb">⏰</div><h4>Jadwal Ramah Anak</h4><p>Belajar 2× seminggu mulai 09.00, tidak memberatkan.</p></div>
        <div class="why-card"><div class="emo" style="background:#eaf7e0">📖</div><h4>Tauhid &amp; Sunnah</h4><p>Pondasi iman &amp; akhlak sejak usia dini.</p></div>
        <div class="why-card"><div class="emo" style="background:#fdeede">🚗</div><h4>Lokasi Strategis</h4><p>5 menit dari Gerbang Tol Margaasih.</p></div>
        <div class="why-card"><div class="emo" style="background:#e7f6fb">🏫</div><h4>Kelas Montessori Modern</h4><p>Fasilitas ruang kelas modern <i>(tahap pembangunan)</i>.</p></div>
    </div>
</section>

<!-- ===================== BIAYA ===================== -->
<section class="pad fees" id="biaya">
    <div class="wrap" style="text-align:center">
        <span class="eyebrow">Tahun Ajaran 2026/2027</span>
        <h2 class="sec-title" style="margin-top:1rem">Rincian <span class="b">Biaya Pendaftaran</span></h2>
    </div>
    <div class="wrap" style="margin-top:2rem">
        <div class="fee-card">
            <div class="fee-row fee-head"><span>Kategori</span><span class="amt">Biaya</span></div>
            <div class="fee-row"><span>Administrasi Pendaftaran</span><span class="amt">Rp 250.000</span></div>
            <div class="fee-row"><span>Infaq Sarana Prasarana</span><span class="amt">Rp 500.000</span></div>
            <div class="fee-row"><span>SPP Bulanan</span><span class="amt">Rp 200.000</span></div>
            <div class="fee-row"><span>2 Set Seragam</span><span class="amt">Rp 300.000</span></div>
            <div class="fee-row"><span>Media Pembelajaran untuk 1 Tahun</span><span class="amt">Rp 400.000</span></div>
            <div class="fee-row"><span>Raport untuk 2 Semester</span><span class="amt">Rp 135.000</span></div>
            <div class="fee-row"><span>Program Outing Class / tahun</span><span class="amt">Insidental</span></div>
            <div class="fee-row fee-total"><span>Total</span><span class="amt">Rp 1.885.000</span></div>
        </div>
        <p class="fee-nb">NB: Khusus bagi putra-putri guru/staff Al-Basyariyah berlaku biaya tersendiri. Silakan menghubungi admin.</p>
    </div>
</section>

<!-- ===================== GALERI ===================== -->
<section class="pad gallery" id="galeri">
    <div class="wrap" style="text-align:center">
        <span class="eyebrow">Photo Gallery</span>
        <h2 class="sec-title" style="margin-top:1rem">Keseruan <span class="b">di Albakids</span></h2>
        <p class="sec-sub">Sebagian momen keseruan kegiatan ananda di Albakids Preschool.</p>
    </div>
    <div class="wrap gal-grid">
        @foreach(['g1','g2','g3','g4','g5','g6'] as $g)
            <a href="/albakids/{{ $g }}.jpg" target="_blank" rel="noopener"><img src="/albakids/{{ $g }}.jpg" alt="Galeri Albakids" loading="lazy"></a>
        @endforeach
    </div>
</section>

<!-- ===================== ALUR ===================== -->
<section class="pad steps" id="alur">
    <div class="wrap" style="text-align:center">
        <span class="eyebrow">Mudah &amp; Cepat</span>
        <h2 class="sec-title" style="margin-top:1rem">Alur <span class="b">Pendaftaran</span></h2>
    </div>
    <div class="wrap steps-grid">
        <div class="step"><div class="num">1</div><div><h4>Transfer Biaya Pendaftaran</h4><p>Lakukan transfer biaya pendaftaran sebesar <b>Rp 1.885.000</b> ke rekening resmi Albakids di bawah.</p></div></div>
        <div class="step"><div class="num">2</div><div><h4>Isi Formulir &amp; Upload Bukti</h4><p>Lengkapi formulir pendaftaran online dan unggah bukti transfer langsung di halaman ini.</p></div></div>
        <div class="step"><div class="num">3</div><div><h4>Konfirmasi ke Admin</h4><p>Konfirmasi pendaftaran ke admin Albakids via WhatsApp <b>+62 822 6090 1399</b>.</p></div></div>
        <div class="step"><div class="num">4</div><div><h4>Ada Pertanyaan?</h4><p>Apabila ada pertanyaan, jangan ragu menghubungi admin kami. Kami siap membantu 😊</p></div></div>
    </div>
    <div class="wrap"><div class="bank">
        <div style="font-weight:700;opacity:.9">Rekening Resmi Pendaftaran</div>
        <div class="acc">1300005641850</div>
        <div style="font-family:'Baloo 2';font-weight:700;font-size:1.1rem">Bank Mandiri · a.n. Inna Siti Nurhasanah</div>
    </div></div>
</section>

<!-- ===================== FORM DAFTAR ===================== -->
<section class="pad reg" id="daftar">
    <div class="wrap" style="text-align:center">
        <span class="eyebrow">Formulir Pendaftaran</span>
        <h2 class="sec-title" style="margin-top:1rem">Daftarkan <span class="b">Ananda Sekarang</span></h2>
        <p class="sec-sub">Pastikan sudah melakukan transfer biaya pendaftaran sebelum mengisi formulir &amp; mengunggah bukti transfer.</p>
    </div>

    <form class="form-card" method="POST" action="{{ route('tenant.landing.register') }}" enctype="multipart/form-data">
        @csrf
        {{-- Default tersembunyi — mengikuti payload form versi awal agar controller tidak undefined-key --}}
        <input type="hidden" name="program_diminati" value="Preschool">
        <input type="hidden" name="kelas_tujuan" value="Kelompok Bermain">
        <input type="hidden" name="nama_wali" value="">
        <input type="hidden" name="hubungan_wali" value="Ibu">
        <input type="hidden" name="no_hp_wali" value="">
        <input type="hidden" name="nama_pengirim_transfer" value="">
        <input type="hidden" name="bank_pengirim" value="Bank Mandiri">

        @if(session('success'))
            <div class="alert alert-ok">✅ {{ session('success') }}</div>
        @endif
        @if($errors->any())
            <div class="alert alert-err">⚠️ Mohon periksa kembali isian berikut:
                <ul>@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
            </div>
        @endif

        <div class="grid2">
            <div class="field col-2"><label>NAMA LENGKAP (Wajib kapital/huruf besar) <span class="req">*</span></label><input type="text" name="nama_anak" value="{{ old('nama_anak') }}" style="text-transform:uppercase" required></div>

            <div class="field"><label>Jenis Kelamin <span class="req">*</span></label>
                <select name="jenis_kelamin" required>
                    <option value="">— Pilih —</option>
                    <option value="L" @selected(old('jenis_kelamin')==='L')>Laki-laki</option>
                    <option value="P" @selected(old('jenis_kelamin')==='P')>Perempuan</option>
                </select>
            </div>
            <div class="field"><label>Tempat, tanggal lahir <span class="req">*</span></label><input type="text" name="tempat_lahir" value="{{ old('tempat_lahir') }}" placeholder="Tempat lahir" required></div>
            <div class="field"><label>Tanggal Lahir <span class="req">*</span></label><input type="date" name="tanggal_lahir" value="{{ old('tanggal_lahir') }}" required></div>
            <div class="field"><label>Anak ke- <span class="req">*</span></label><input type="text" name="child_order" value="{{ old('child_order') }}" required></div>

            <div class="field"><label>Nama Ayah (Lengkap) <span class="req">*</span></label><input type="text" name="nama_ayah" value="{{ old('nama_ayah') }}" required></div>
            <div class="field"><label>Nama Ibu (Lengkap) <span class="req">*</span></label><input type="text" name="nama_ibu" value="{{ old('nama_ibu') }}" required></div>
            <div class="field"><label>Pekerjaan Ayah <span class="req">*</span></label><input type="text" name="pekerjaan_ayah" value="{{ old('pekerjaan_ayah') }}" required></div>
            <div class="field"><label>Pekerjaan Ibu <span class="req">*</span></label><input type="text" name="pekerjaan_ibu" value="{{ old('pekerjaan_ibu') }}" required></div>
            <div class="field"><label>No WhatsApp Ayah <span class="req">*</span></label><input type="tel" name="no_hp_ayah" value="{{ old('no_hp_ayah') }}" placeholder="628xxxxxxxxx" required></div>
            <div class="field"><label>No WhatsApp Ibu <span class="req">*</span></label><input type="tel" name="no_hp_ibu" value="{{ old('no_hp_ibu') }}" placeholder="628xxxxxxxxx" required></div>

            <div class="field col-2"><label>Alamat Lengkap (Jalan, Desa/Kelurahan, Kecamatan, Kabupaten/Kota, Provinsi) <span class="req">*</span></label><textarea name="alamat" rows="3" required>{{ old('alamat') }}</textarea></div>

            <div class="field col-2"><label>Upload Bukti Transfer Pendaftaran <span class="req">*</span></label>
                <input type="file" name="bukti_transfer" accept=".jpg,.jpeg,.png,.pdf,.webp" required>
                <span class="hint">Sudah transfer Rp 1.885.000 ke Bank Mandiri a.n. Inna Siti Nurhasanah · 1300005641850. Format JPG/PNG/PDF/WEBP, maks 5 MB.</span>
            </div>
        </div>

        <div style="text-align:center;margin-top:1.8rem">
            <button type="submit" class="btn btn-orange btn-lg">🚀 Kirim Pendaftaran</button>
            <p class="hint" style="margin-top:.8rem">Dengan mendaftar, Anda menyetujui konfirmasi data melalui WhatsApp admin Albakids.</p>
        </div>
    </form>
</section>

<!-- ===================== FOOTER ===================== -->
<footer class="ft" id="kontak">
    <svg class="grass" style="top:-1px;bottom:auto;transform:rotate(180deg)" viewBox="0 0 1440 90" preserveAspectRatio="none" height="50"><path fill="#fffaf2" d="M0,40 C180,90 360,10 540,45 C720,80 900,15 1080,45 C1260,75 1380,35 1440,50 L1440,90 L0,90 Z"></path></svg>
    <div class="wrap ft-grid" style="margin-top:1.5rem">
        <div>
            <img src="/albakids/logo.png" alt="Logo Albakids" style="width:72px;height:72px;background:#fff;border-radius:50%;padding:5px;margin-bottom:.7rem;box-shadow:0 8px 18px -10px rgba(0,0,0,.4)">
            <h3 class="script"><span class="a">Alba</span>kids</h3>
            <div class="ft-sub">Al-Basyariyah Kinder Preschool</div>
            <p style="color:#d4efdd;font-weight:600;margin-top:1rem;max-width:34ch">Bermain &amp; belajar dengan pendekatan Islamic Montessori berlandaskan tauhid &amp; sunnah.</p>
        </div>
        <div>
            <h4>📍 Alamat</h4>
            <p style="color:#d4efdd;font-weight:600;margin:0">Pondok Pesantren Al-Basyariyah<br>Jl. Mahmud, Cigondewah Hilir, Margaasih<br>Kabupaten Bandung</p>
        </div>
        <div>
            <h4>📞 Informasi Pendaftaran</h4>
            <div class="row">💬 <a href="https://wa.me/{{ $wa }}?text={{ $waText }}" target="_blank" rel="noopener">+62 822 6090 1399</a></div>
            <div class="row">📷 <a href="https://instagram.com/albakids_preschool.bdg" target="_blank" rel="noopener">@albakids_preschool.bdg</a></div>
            <a href="#daftar" class="btn btn-green" style="margin-top:.8rem">Daftar Sekarang</a>
        </div>
    </div>
    <div class="wrap ft-bottom">© {{ date('Y') }} Albakids Preschool · Al-Basyariyah Kinder Preschool · Margaasih, Bandung</div>
</footer>

<a class="float-wa" href="https://wa.me/{{ $wa }}?text={{ $waText }}" target="_blank" rel="noopener" aria-label="WhatsApp Admin">💬</a>

<script>
    function feeTab(btn,pane){
        document.querySelectorAll('.fee-tab').forEach(b=>b.classList.remove('active'));
        document.querySelectorAll('.fee-pane').forEach(p=>p.classList.remove('active'));
        btn.classList.add('active');
        document.getElementById('pane-'+pane).classList.add('active');
    }
    // simple mobile nav
    document.querySelector('.burger')?.addEventListener('click',()=>{
        const m=document.getElementById('mnav');
        if(m.style.display==='none'||!m.style.display){
            m.style.display='block';
            m.innerHTML='<div style="display:flex;flex-direction:column;gap:.4rem;padding:.6rem 0 1rem;font-family:Baloo 2;font-weight:700">'+
              ['#tentang|Tentang','#kurikulum|Kurikulum','#keunggulan|Keunggulan','#biaya|Biaya','#galeri|Galeri','#alur|Alur','#daftar|Daftar Sekarang']
              .map(x=>{const[h,t]=x.split('|');return '<a href="'+h+'" style="padding:.4rem .2rem;color:#4c4540">'+t+'</a>'}).join('')+'</div>';
        } else { m.style.display='none'; }
    });
</script>
</body>
</html>
