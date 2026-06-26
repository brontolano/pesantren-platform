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
    <link rel="icon" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 64 64'><rect width='64' height='64' rx='14' fill='%230f172a'/><text x='13' y='47' font-family='Arial,sans-serif' font-weight='800' font-size='42' fill='white'>P</text><circle cx='49' cy='45' r='7' fill='%2310b981'/></svg>">
    <meta property="og:title" content="Pesantren Platform — Sistem Administrasi Pesantren Terpadu">
    <meta property="og:description" content="Platform SaaS untuk manajemen pesantren modern. Satu sistem untuk semua kebutuhan pengelolaan.">
    <meta property="og:url" content="https://pesantren.online">
    <meta property="og:type" content="website">
    <meta property="og:locale" content="id_ID">
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=jost:400,500,600,700,800|plus-jakarta-sans:400,500,600,700,800" rel="stylesheet">
<style>
  /* Token Design System "Pixio" (inline — selaras tokens/colors·typography·spacing). */
  :root{
    --brand-primary:#059669; --brand-primary-strong:#047857; --brand-gold:#c9a24a;
    --pst-emerald-50:#ecfdf5; --pst-emerald-200:#a7f3d0; --pst-emerald-400:#34d399; --pst-emerald-500:#10b981;
    --pst-slate-900:#0f172a; --pst-slate-800:#1e293b; --pst-slate-700:#334155; --pst-slate-500:#64748b; --pst-slate-200:#e2e8f0; --pst-slate-50:#f8fafc;
    --pst-cream:#fffaf3; --pst-gold:#c9a24a; --pst-danger-bg:#fff1f2; --pst-danger-ink:#be123c;
    --text-strong:#111111; --text-muted:#64748b; --text-faint:#94a3b8; --text-accent:#047857;
    --font-display:'Jost','Plus Jakarta Sans',ui-sans-serif,system-ui,sans-serif;
    --font-body:'Plus Jakarta Sans','Instrument Sans',ui-sans-serif,system-ui,sans-serif;
    --shadow-brand:0 8px 20px -12px rgba(5,150,105,.45);
    --shadow-soft:0 6px 24px -16px rgba(17,17,17,.18);
    --elevation-card:inset 0 0 0 1px #efe7da,0 6px 24px -16px rgba(17,17,17,.18);
    --ring-card:inset 0 0 0 1px #efe7da;
    --brand-grad:linear-gradient(135deg,#10b981 0%,#059669 100%);
    --gold-hairline:linear-gradient(90deg,transparent 0%,rgba(201,162,74,0) 8%,#c9a24a 50%,rgba(201,162,74,0) 92%,transparent 100%);
    --dur-base:200ms; --ease-standard:cubic-bezier(.4,0,.2,1);
  }
  *,*::before,*::after { box-sizing: border-box; }
  body { margin: 0; font-family: var(--font-body); background: #ffffff; color: var(--pst-slate-700); line-height: 1.6; -webkit-font-smoothing: antialiased; }
  a { color: inherit; text-decoration: none; }
  img { max-width: 100%; }
  .wrap { max-width: 1160px; margin: 0 auto; padding: 0 28px; }
  h1,h2,h3 { font-family: var(--font-display); color: var(--pst-slate-900); letter-spacing: -0.02em; }
  .eyebrow { font-family: var(--font-display); font-size: 12.5px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.12em; color: var(--brand-primary); }

  /* Buttons */
  .btn { display: inline-flex; align-items: center; gap: 8px; padding: 12px 24px; border-radius: 9999px; font-family: var(--font-display); font-size: 14.5px; font-weight: 600; cursor: pointer; border: none; transition: transform var(--dur-base) var(--ease-standard), background var(--dur-base), box-shadow var(--dur-base); white-space: nowrap; }
  .btn:active { transform: scale(0.97); }
  .btn-primary { background: var(--brand-primary); color: #fff; box-shadow: var(--shadow-brand); }
  .btn-primary:hover { background: var(--brand-primary-strong); }
  .btn-outline { background: #fff; color: var(--text-strong); box-shadow: inset 0 0 0 1.5px var(--pst-slate-200); }
  .btn-outline:hover { box-shadow: inset 0 0 0 1.5px var(--brand-primary); color: var(--brand-primary); }
  .btn-ghost-light { background: rgba(255,255,255,0.12); color: #fff; box-shadow: inset 0 0 0 1.5px rgba(255,255,255,0.3); }
  .btn-ghost-light:hover { background: rgba(255,255,255,0.2); }

  /* Nav */
  nav { position: sticky; top: 0; z-index: 50; background: rgba(255,255,255,0.85); backdrop-filter: blur(14px); border-bottom: 1px solid var(--pst-slate-200); }
  .nav-inner { display: flex; align-items: center; justify-content: space-between; height: 68px; gap: 24px; }
  .logo { display: inline-flex; align-items: center; gap: 9px; font-family: var(--font-display); font-size: 18px; font-weight: 700; letter-spacing: -0.01em; color: var(--text-strong); }
  .logo .mark { height: 30px; width: 30px; border-radius: 8px; background: var(--pst-slate-900); display: flex; align-items: center; justify-content: center; position: relative; flex-shrink: 0; }
  .logo .mark b { color: #fff; font-size: 16px; font-weight: 800; }
  .logo .mark i { position: absolute; right: 5px; bottom: 5px; height: 6px; width: 6px; border-radius: 9999px; background: var(--brand-primary); }
  .logo .dot { color: var(--brand-gold); }
  .nav-links { display: flex; gap: 28px; align-items: center; }
  .nav-links a { font-size: 14px; font-weight: 500; color: var(--text-muted); }
  .nav-links a:hover { color: var(--brand-primary); }
  .nav-cta { display: flex; gap: 10px; align-items: center; }
  @media (max-width: 880px) { .nav-links { display: none; } }

  /* Hero */
  .hero { position: relative; overflow: hidden; padding: 76px 0 48px; text-align: center; background:
      radial-gradient(70% 60% at 50% -8%, var(--pst-emerald-50) 0%, transparent 60%),
      linear-gradient(180deg, #fbfdfc, #ffffff); }
  .hero .badge { display: inline-flex; align-items: center; gap: 8px; background: #fff; box-shadow: var(--elevation-card); color: var(--brand-primary-strong); font-family: var(--font-display); font-size: 12.5px; font-weight: 600; padding: 7px 16px; border-radius: 9999px; }
  .hero .badge .pulse { height: 7px; width: 7px; border-radius: 9999px; background: var(--brand-primary); box-shadow: 0 0 0 0 rgba(5,150,105,0.5); animation: pulse 2.2s infinite; }
  @keyframes pulse { 0%{box-shadow:0 0 0 0 rgba(5,150,105,0.45)} 70%{box-shadow:0 0 0 8px rgba(5,150,105,0)} 100%{box-shadow:0 0 0 0 rgba(5,150,105,0)} }
  .hero h1 { font-size: 45px; font-weight: 800; line-height: 1.2; margin: 22px auto 0; max-width: 860px; letter-spacing: -0.01em; }
  .hero h1 .l1, .hero h1 .g { display: block; }
  .hero h1 .g { color: var(--brand-primary); }
  .hero p.sub { font-size: 18.5px; color: var(--text-muted); line-height: 1.65; margin: 22px auto 0; max-width: 600px; }
  .hero-actions { display: flex; gap: 13px; justify-content: center; margin-top: 32px; flex-wrap: wrap; }
  .hero .micro { margin-top: 18px; font-size: 13px; color: var(--text-faint); display: flex; gap: 16px; justify-content: center; flex-wrap: wrap; }
  .hero .micro span { display: inline-flex; align-items: center; gap: 6px; }
  .hero .micro svg { color: var(--brand-primary); }

  /* Product preview */
  .preview { margin: 46px auto 0; max-width: 980px; }
  .browser { border-radius: 16px 16px 14px 14px; background: #fff; box-shadow: 0 40px 80px -40px rgba(15,23,42,0.4), inset 0 0 0 1px var(--pst-slate-200); overflow: hidden; }
  .browser-bar { display: flex; align-items: center; gap: 7px; padding: 11px 14px; background: var(--pst-slate-50); border-bottom: 1px solid var(--pst-slate-200); }
  .browser-bar .d { height: 11px; width: 11px; border-radius: 9999px; }
  .browser-url { margin-left: 12px; flex: 1; max-width: 320px; background: #fff; border: 1px solid var(--pst-slate-200); border-radius: 7px; padding: 4px 12px; font-size: 11.5px; color: var(--text-faint); text-align: left; }
  .dash { display: grid; grid-template-columns: 188px 1fr; min-height: 320px; text-align: left; }
  .dash-side { background: var(--pst-slate-900); padding: 16px 12px; }
  .dash-side .brand { color: #fff; font-family: var(--font-display); font-weight: 700; font-size: 13px; margin: 0 8px 16px; }
  .dash-side .nav-i { display: flex; align-items: center; gap: 9px; padding: 8px 10px; border-radius: 8px; font-family: var(--font-display); font-size: 12px; font-weight: 500; color: #94a3b8; margin-bottom: 2px; }
  .dash-side .nav-i.on { background: rgba(16,185,129,0.16); color: #6ee7b7; }
  .dash-side .nav-i .dt { height: 7px; width: 7px; border-radius: 2px; background: currentColor; opacity: 0.8; }
  .dash-main { padding: 18px 20px; background: #f8fafc; }
  .dash-h { font-family: var(--font-display); font-weight: 700; font-size: 15px; color: var(--pst-slate-900); }
  .dash-stats { display: grid; grid-template-columns: repeat(3,1fr); gap: 10px; margin-top: 12px; }
  .dash-stat { background: #fff; border: 1px solid var(--pst-slate-200); border-radius: 11px; padding: 12px; }
  .dash-stat .l { font-size: 10px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.06em; color: var(--text-faint); }
  .dash-stat .v { font-family: var(--font-display); font-weight: 800; font-size: 19px; color: var(--pst-slate-900); margin-top: 5px; }
  .dash-stat .v.g { color: var(--brand-primary); }
  .dash-chart { background: #fff; border: 1px solid var(--pst-slate-200); border-radius: 11px; padding: 14px; margin-top: 10px; display: flex; align-items: flex-end; gap: 8px; height: 116px; }
  .dash-chart .bar { flex: 1; background: linear-gradient(180deg, var(--pst-emerald-400), var(--brand-primary)); border-radius: 5px 5px 0 0; }

  /* Sections */
  section.block { padding: 84px 0; }
  .block.tint { background: var(--pst-cream); }
  .block.alt { background: #f8fafc; }
  .head { text-align: center; margin-bottom: 48px; }
  .head h2 { font-size: 36px; font-weight: 800; margin: 12px 0 0; }
  .head p { font-size: 17px; color: var(--text-muted); max-width: 620px; margin: 14px auto 0; }

  /* Trust band */
  .trust { border-top: 1px solid var(--pst-slate-200); border-bottom: 1px solid var(--pst-slate-200); background: #fff; }
  .trust-inner { display: flex; justify-content: space-around; flex-wrap: wrap; gap: 24px; padding: 30px 0; }
  .trust .t { text-align: center; }
  .trust .t .n { font-family: var(--font-display); font-size: 34px; font-weight: 800; color: var(--brand-primary); letter-spacing: -0.02em; }
  .trust .t .l { font-size: 13px; color: var(--text-muted); margin-top: 2px; }

  /* Grids */
  .grid { display: grid; gap: 18px; }
  .g3 { grid-template-columns: repeat(3,1fr); }
  .g4 { grid-template-columns: repeat(4,1fr); }
  .g2 { grid-template-columns: repeat(2,1fr); }
  @media (max-width: 920px) { .g3,.g4 { grid-template-columns: repeat(2,1fr); } .dash { grid-template-columns: 1fr; } .dash-side { display: none; } }
  @media (max-width: 600px) { .g3,.g4,.g2 { grid-template-columns: 1fr; } .hero h1 { font-size: 38px; } .head h2 { font-size: 28px; } }

  .card { background: #fff; border-radius: 18px; padding: 24px; box-shadow: var(--elevation-card); transition: transform var(--dur-base) var(--ease-standard), box-shadow var(--dur-base); }
  .card:hover { transform: translateY(-3px); box-shadow: 0 22px 44px -26px rgba(5,150,105,0.28), inset 0 0 0 1px var(--pst-emerald-200); }
  .card .ic { display: inline-flex; align-items: center; justify-content: center; height: 46px; width: 46px; border-radius: 13px; background: var(--pst-emerald-50); font-size: 24px; }
  .card h3 { font-size: 17px; font-weight: 700; margin: 14px 0 6px; }
  .card p { font-size: 14px; color: var(--text-muted); line-height: 1.6; margin: 0; }

  /* Pain cards */
  .pain { background: #fff; border-radius: 18px; padding: 24px; box-shadow: var(--ring-card); }
  .pain .x { display: inline-flex; height: 40px; width: 40px; align-items: center; justify-content: center; border-radius: 11px; background: var(--pst-danger-bg); color: var(--pst-danger-ink); }
  .pain h3 { font-size: 16px; margin: 13px 0 6px; }
  .pain p { font-size: 14px; color: var(--text-muted); margin: 0; }
  .arrow-down { text-align: center; margin: 30px 0 -10px; color: var(--brand-primary); }

  /* AI section */
  .ai { background: var(--pst-slate-900); color: #fff; position: relative; overflow: hidden; }
  .ai .head h2 { color: #fff; }
  .ai .head p { color: #cbd5e1; }
  .ai .pattern { position: absolute; inset: 0; opacity: 0.05; pointer-events: none; }
  .ai-card { background: rgba(255,255,255,0.04); border: 1px solid rgba(255,255,255,0.1); border-radius: 18px; padding: 24px; transition: background var(--dur-base); }
  .ai-card:hover { background: rgba(255,255,255,0.07); }
  .ai-card .ic { display: inline-flex; height: 46px; width: 46px; align-items: center; justify-content: center; border-radius: 13px; background: rgba(16,185,129,0.16); color: #6ee7b7; }
  .ai-card h3 { color: #fff; font-size: 16.5px; margin: 14px 0 6px; }
  .ai-card p { color: #94a3b8; font-size: 14px; margin: 0; line-height: 1.6; }
  .ai-quote { text-align: center; max-width: 680px; margin: 44px auto 0; font-family: var(--font-display); font-size: 22px; font-weight: 600; line-height: 1.4; color: #e2e8f0; }
  .ai-quote .g { color: #6ee7b7; }

  /* Steps */
  .steps { counter-reset: s; display: grid; grid-template-columns: repeat(3,1fr); gap: 22px; }
  @media (max-width:760px){ .steps { grid-template-columns: 1fr; } }
  .step { position: relative; padding-left: 60px; }
  .step .num { position: absolute; left: 0; top: 0; height: 44px; width: 44px; border-radius: 13px; background: var(--brand-primary); color: #fff; display: flex; align-items: center; justify-content: center; font-family: var(--font-display); font-weight: 800; font-size: 18px; box-shadow: var(--shadow-brand); }
  .step h3 { font-size: 17px; margin: 4px 0 6px; }
  .step p { font-size: 14px; color: var(--text-muted); margin: 0; }

  /* Pricing */
  .price { background: #fff; border-radius: 20px; padding: 26px; box-shadow: var(--elevation-card); display: flex; flex-direction: column; position: relative; }
  .price.pop { box-shadow: inset 0 0 0 2px var(--brand-primary), 0 24px 50px -28px rgba(5,150,105,0.4); }
  .price .pop-tag { position: absolute; top: -12px; left: 50%; transform: translateX(-50%); background: var(--brand-primary); color: #fff; font-family: var(--font-display); font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.06em; padding: 4px 14px; border-radius: 9999px; }
  .price .pn { font-family: var(--font-display); font-size: 18px; font-weight: 700; color: var(--pst-slate-900); }
  .price .pp { font-family: var(--font-display); font-weight: 800; color: var(--pst-slate-900); margin: 12px 0 2px; letter-spacing: -0.01em; }
  .price .pp .amt { font-size: 30px; } .price .pp .u { font-size: 13px; font-weight: 500; color: var(--text-muted); }
  .price .pd { font-size: 13px; color: var(--text-muted); min-height: 36px; }
  .price ul { list-style: none; padding: 0; margin: 16px 0 20px; display: flex; flex-direction: column; gap: 9px; flex: 1; }
  .price li { display: flex; align-items: center; gap: 9px; font-size: 13.5px; color: var(--pst-slate-700); }
  .price li svg { color: var(--brand-primary); flex-shrink: 0; }

  /* Testimonials */
  .testi { background: #fff; border-radius: 18px; padding: 26px; box-shadow: var(--elevation-card); }
  .testi .stars { color: var(--brand-gold); font-size: 14px; letter-spacing: 2px; }
  .testi p { font-size: 15.5px; color: var(--pst-slate-800); line-height: 1.65; margin: 12px 0 18px; }
  .testi .who { display: flex; align-items: center; gap: 11px; }
  .testi .av { height: 42px; width: 42px; border-radius: 9999px; background: var(--brand-grad); color: #fff; display: flex; align-items: center; justify-content: center; font-family: var(--font-display); font-weight: 700; }
  .testi .who b { font-family: var(--font-display); font-size: 14px; color: var(--pst-slate-900); display: block; }
  .testi .who span { font-size: 12.5px; color: var(--text-muted); }

  /* FAQ */
  .faq { max-width: 760px; margin: 0 auto; }
  .qa { background: #fff; border-radius: 14px; box-shadow: var(--ring-card); margin-bottom: 12px; overflow: hidden; }
  .qa summary { list-style: none; cursor: pointer; padding: 18px 20px; font-family: var(--font-display); font-weight: 600; font-size: 15.5px; color: var(--pst-slate-900); display: flex; justify-content: space-between; align-items: center; gap: 12px; }
  .qa summary::-webkit-details-marker { display: none; }
  .qa summary .pm { color: var(--brand-primary); font-size: 20px; transition: transform var(--dur-base); flex-shrink: 0; }
  .qa[open] summary .pm { transform: rotate(45deg); }
  .qa .a { padding: 0 20px 18px; font-size: 14.5px; color: var(--text-muted); line-height: 1.65; }

  /* Final CTA */
  .gold-line { height: 2px; max-width: 1160px; margin: 0 auto; background-image: var(--gold-hairline); }
  .cta { background: var(--pst-slate-900); color: #fff; text-align: center; padding: 84px 0; position: relative; overflow: hidden; }
  .cta .pattern { position: absolute; inset: 0; opacity: 0.06; }
  .cta h2 { color: #fff; font-size: 38px; font-weight: 800; margin: 0; position: relative; }
  .cta p { font-size: 17px; color: #cbd5e1; max-width: 540px; margin: 16px auto 30px; position: relative; }

  /* Footer */
  footer { background: #0b1220; color: #94a3b8; padding: 56px 0 28px; }
  .foot-top { display: grid; grid-template-columns: 1.6fr 1fr 1fr 1fr; gap: 30px; padding-bottom: 36px; border-bottom: 1px solid #1e293b; }
  @media (max-width: 760px){ .foot-top { grid-template-columns: 1fr 1fr; } }
  .foot-col h4 { font-family: var(--font-display); font-size: 12px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.08em; color: #e2e8f0; margin: 0 0 14px; }
  .foot-col a { display: block; font-size: 13.5px; color: #94a3b8; margin-bottom: 9px; }
  .foot-col a:hover { color: #fff; }
  .foot-brand p { font-size: 13.5px; max-width: 280px; margin: 12px 0 0; line-height: 1.6; }
  .foot-bottom { display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 12px; padding-top: 22px; font-size: 13px; }
  .foot-bottom a { color: var(--pst-emerald-500); }
  .reveal { opacity: 1; transform: none; }
  @media (prefers-reduced-motion: reduce) { .hero .badge .pulse { animation: none; } }
</style>
</head>
<body>

@php
    $demo = 'https://demo.pesantren.online';
    $wa = "https://wa.me/6285572711197?text=Assalamu'alaikum,%20saya%20tertarik%20dengan%20Pesantren%20Platform";
@endphp

<nav><div class="wrap nav-inner">
  <a href="/" class="logo"><span class="mark"><b>P</b><i></i></span>Pesantren<span class="dot">.</span>online</a>
  <div class="nav-links">
    <a href="#empati">Kenapa Kami</a>
    <a href="#modul">Modul</a>
    <a href="#ai">Teknologi</a>
    <a href="#harga">Harga</a>
    <a href="#cerita">Cerita</a>
  </div>
  <div class="nav-cta">
    <a href="/platform" class="btn btn-outline" style="padding:9px 18px">Masuk</a>
    <a href="#harga" class="btn btn-primary" style="padding:9px 18px">Coba gratis</a>
  </div>
</div></nav>

<header class="hero"><div class="wrap">
  <span class="badge reveal"><span class="pulse"></span> Platform manajemen pesantren di era AI</span>
  <h1 class="reveal"><span class="l1">Urus pesantren dengan tenang,</span> <span class="g">layani umat sepenuh hati</span></h1>
  <p class="sub reveal">pesantren.online menyatukan data santri, keuangan, akademik, dan komunikasi wali dalam satu sistem cloud — agar pengasuh &amp; staf fokus mendidik, bukan sibuk mengurus berkas.</p>
  <div class="hero-actions reveal">
    <a href="{{ $demo }}" target="_blank" rel="noopener" class="btn btn-primary">Coba demo gratis</a>
    <a href="#cerita" class="btn btn-outline">Lihat cerita pesantren</a>
  </div>
  <div class="micro reveal">
    <span><svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="m5 13 4 4L19 7"/></svg> Gratis untuk mulai</span>
    <span><svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="m5 13 4 4L19 7"/></svg> Tanpa kartu kredit</span>
    <span><svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="m5 13 4 4L19 7"/></svg> Siap dalam 1 hari</span>
  </div>

  <div class="preview reveal">
    <div class="browser">
      <div class="browser-bar">
        <span class="d" style="background:#f87171"></span><span class="d" style="background:#fbbf24"></span><span class="d" style="background:#34d399"></span>
        <span class="browser-url">sistem.pesantren.online/admin</span>
      </div>
      <div class="dash">
        <div class="dash-side">
          <p class="brand">Ponpes Asy-Syifaa</p>
          <div class="nav-i on"><span class="dt"></span> Dashboard</div>
          <div class="nav-i"><span class="dt"></span> Santri</div>
          <div class="nav-i"><span class="dt"></span> Keuangan</div>
          <div class="nav-i"><span class="dt"></span> Akademik</div>
          <div class="nav-i"><span class="dt"></span> PPDB</div>
          <div class="nav-i"><span class="dt"></span> Tahfidz</div>
        </div>
        <div class="dash-main">
          <div class="dash-h">Assalamu'alaikum, Pengurus 👋</div>
          <div class="dash-stats">
            <div class="dash-stat"><div class="l">Total Santri</div><div class="v">842</div></div>
            <div class="dash-stat"><div class="l">Kas Masuk</div><div class="v g">Rp 312jt</div></div>
            <div class="dash-stat"><div class="l">Tagihan Lunas</div><div class="v">79%</div></div>
          </div>
          <div class="dash-chart">
            <div class="bar" style="height:54%"></div><div class="bar" style="height:68%"></div><div class="bar" style="height:60%"></div><div class="bar" style="height:82%"></div><div class="bar" style="height:71%"></div><div class="bar" style="height:90%"></div><div class="bar" style="height:78%"></div><div class="bar" style="height:96%"></div>
          </div>
        </div>
      </div>
    </div>
  </div>
</div></header>

<div class="trust"><div class="wrap trust-inner">
  <div class="t reveal"><div class="n">40+</div><div class="l">Pesantren mitra</div></div>
  <div class="t reveal"><div class="n">25.000+</div><div class="l">Santri terkelola</div></div>
  <div class="t reveal"><div class="n">12</div><div class="l">Modul terintegrasi</div></div>
  <div class="t reveal"><div class="n">99,9%</div><div class="l">Uptime cloud</div></div>
</div></div>

<section class="block tint" id="empati"><div class="wrap">
  <div class="head reveal">
    <span class="eyebrow">Kami paham</span>
    <h2>Mengelola pesantren itu berat.</h2>
    <p>Ratusan santri, puluhan tagihan, data tersebar di buku dan Excel — sementara wali terus bertanya kabar ananda. Anda tidak sendirian.</p>
  </div>
  <div class="grid g3">
    <div class="pain reveal"><span class="x"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"><path d="M19 5 5 19M5 5l14 14"/></svg></span><h3>Data tercecer</h3><p>Buku induk, Excel, dan catatan kertas yang sulit dicari dan rawan hilang saat dibutuhkan.</p></div>
    <div class="pain reveal"><span class="x"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"><path d="M19 5 5 19M5 5l14 14"/></svg></span><h3>Keuangan sulit dipantau</h3><p>Tagihan SPP, tunggakan, dan tabungan santri yang menyita waktu dan rawan selisih.</p></div>
    <div class="pain reveal"><span class="x"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"><path d="M19 5 5 19M5 5l14 14"/></svg></span><h3>Wali sulit dapat kabar</h3><p>Orang tua menunggu informasi tagihan dan perkembangan anak yang datang terlambat.</p></div>
  </div>
  <div class="arrow-down reveal"><svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 5v14M19 12l-7 7-7-7"/></svg></div>
  <div class="head reveal" style="margin-bottom:0">
    <h2 style="font-size:30px">Satu platform menata semuanya — rapi, aman, dan terhubung.</h2>
  </div>
</div></section>

<section class="block" id="modul"><div class="wrap">
  <div class="head reveal">
    <span class="eyebrow">Modul lengkap</span>
    <h2>Semua kebutuhan pesantren, satu sistem.</h2>
    <p>Aktifkan modul sesuai kebutuhan. <b style="color:var(--brand-primary-strong)">Bayar hanya yang dipakai.</b></p>
  </div>
  <div class="grid g3" id="modgrid"></div>
</div></section>

<section class="block ai" id="ai"><div class="pattern" id="aipat"></div><div class="wrap" style="position:relative">
  <div class="head reveal">
    <span class="eyebrow" style="color:#6ee7b7">Teknologi yang memuliakan</span>
    <h2>AI yang melayani, bukan merepotkan.</h2>
    <p>Di era kecerdasan buatan, kami percaya teknologi harus meringankan manusia — bukan menggantikan peran pengasuh dan staf. Otomasi mengurus yang rutin, agar Anda fokus pada yang bermakna.</p>
  </div>
  <div class="grid g4">
    <div class="ai-card reveal"><span class="ic">✨</span><h3>AI Khadim Digital</h3><p>Asisten cerdas membantu rekap, ringkasan laporan, dan menjawab pertanyaan operasional.</p></div>
    <div class="ai-card reveal"><span class="ic">⚡</span><h3>Tagihan otomatis</h3><p>Terbit, hitung, dan rekonsiliasi pembayaran berjalan sendiri — minim salah, hemat waktu.</p></div>
    <div class="ai-card reveal"><span class="ic">💬</span><h3>Broadcast WhatsApp</h3><p>Info tagihan, kegiatan, dan pengumuman langsung sampai ke wali, terjadwal otomatis.</p></div>
    <div class="ai-card reveal"><span class="ic">🕌</span><h3>Konten dakwah</h3><p>Jadwal sholat, artikel, dan kabar pondok terbit otomatis ke aplikasi jemaah.</p></div>
  </div>
  <p class="ai-quote reveal">"Staf Anda, <span class="g">ditenagai teknologi</span> — bukan digantikan olehnya."</p>
</div></section>

<section class="block alt"><div class="wrap">
  <div class="head reveal">
    <span class="eyebrow">Mudah dimulai</span>
    <h2>Jalan dalam 3 langkah.</h2>
    <p>Tanpa server, tanpa tim IT. Kami yang mengelola, Anda tinggal pakai.</p>
  </div>
  <div class="steps">
    <div class="step reveal"><span class="num">1</span><h3>Daftar &amp; verifikasi</h3><p>Buat akun pesantren dalam 5 menit. Tim kami bantu siapkan dari awal.</p></div>
    <div class="step reveal"><span class="num">2</span><h3>Konfigurasi pesantren</h3><p>Pilih modul, atur domain, logo, dan warna sendiri — sepenuhnya milik Anda.</p></div>
    <div class="step reveal"><span class="num">3</span><h3>Undang staf &amp; wali</h3><p>Bagikan akses sesuai peran. Aplikasi wali &amp; santri langsung aktif.</p></div>
  </div>
</div></section>

<section class="block" id="keunggulan"><div class="wrap">
  <div class="head reveal">
    <span class="eyebrow">Amanah &amp; tepercaya</span>
    <h2>Dibangun khusus untuk pesantren Indonesia.</h2>
    <p>Bukan software umum yang dipaksakan — setiap detail disesuaikan dengan cara pesantren bekerja.</p>
  </div>
  <div class="grid g4" id="whygrid"></div>
</div></section>

<section class="block tint" id="cerita"><div class="wrap">
  <div class="head reveal">
    <span class="eyebrow">Cerita pesantren</span>
    <h2>Dipercaya pengasuh di berbagai daerah.</h2>
  </div>
  <div class="grid g3" id="testigrid"></div>
</div></section>

<section class="block alt" id="harga"><div class="wrap">
  <div class="head reveal">
    <span class="eyebrow">Harga jujur</span>
    <h2>Bayar hanya yang dipakai.</h2>
    <p>Per santri, per bulan. Mulai gratis, naik kelas saat pesantren tumbuh.</p>
  </div>
  <div class="grid g4" id="pricegrid"></div>
  <p class="reveal" style="text-align:center;margin-top:26px;font-size:13.5px;color:var(--text-faint)">Semua paket termasuk hosting, keamanan, pembaruan, dan dukungan. Tanpa biaya tersembunyi.</p>
</div></section>

<section class="block"><div class="wrap">
  <div class="head reveal">
    <span class="eyebrow">Masih ragu?</span>
    <h2>Pertanyaan yang sering ditanya.</h2>
  </div>
  <div class="faq" id="faq"></div>
</div></section>

<div class="gold-line"></div>
<section class="cta"><div class="pattern" id="ctapat"></div><div class="wrap">
  <h2 class="reveal">Siap memuliakan pengelolaan pesantren Anda?</h2>
  <p class="reveal">Coba langsung demonya, atau ngobrol gratis dengan tim kami via WhatsApp.</p>
  <div class="hero-actions reveal" style="margin-top:0">
    <a href="{{ $demo }}" target="_blank" rel="noopener" class="btn btn-primary">Coba demo gratis</a>
    <a href="{{ $wa }}" class="btn btn-ghost-light"><svg width="17" height="17" viewBox="0 0 24 24" fill="currentColor"><path d="M12 2a10 10 0 0 0-8.6 15l-1.3 4.7 4.8-1.3A10 10 0 1 0 12 2Zm0 18a8 8 0 0 1-4.1-1.1l-.3-.2-2.8.8.8-2.8-.2-.3A8 8 0 1 1 12 20Z"/></svg> Chat via WhatsApp</a>
  </div>
</div></section>

<footer><div class="wrap">
  <div class="foot-top">
    <div class="foot-col foot-brand">
      <a href="/" class="logo" style="color:#fff"><span class="mark"><b>P</b><i></i></span>Pesantren<span class="dot">.</span>online</a>
      <p>Platform administrasi pesantren terpadu — dari operator hingga wali santri, dalam satu sistem yang amanah.</p>
    </div>
    <div class="foot-col"><h4>Produk</h4><a href="#modul">Modul</a><a href="#harga">Harga</a><a href="{{ $demo }}" target="_blank" rel="noopener">Demo</a><a href="/platform">Masuk</a></div>
    <div class="foot-col"><h4>Sumber daya</h4><a href="/dokumentasi/">Pusat panduan</a><a href="#cerita">Cerita pesantren</a><a href="#faq">FAQ</a><a href="{{ $wa }}">Kontak WhatsApp</a></div>
    <div class="foot-col"><h4>Perusahaan</h4><a href="{{ route('legal.privacy') }}">Kebijakan privasi</a><a href="{{ route('legal.terms') }}">Syarat layanan</a><a href="{{ route('legal.disclaimer') }}">Disclaimer</a><a href="{{ route('legal.contact') }}">Kontak</a></div>
  </div>
  <div class="foot-bottom">
    <span>pesantren.online &copy; {{ date('Y') }} <a href="https://brontolano.com" target="_blank" rel="noopener">PT Rojo Bronto Lano</a>. Dibuat dengan amanah di Indonesia.</span>
    <span>Assalamu'alaikum warahmatullah</span>
  </div>
</div></footer>

<script>
  const DEMO = @json($demo), WA = @json($wa);
  const MODULES = [
    ['🎓','Kesantrian','Data santri, penempatan kelas, mutasi, alumni. Engine kelas fleksibel (Mu’adalah/linear).'],
    ['💰','Keuangan','Tagihan SPP (Masehi/Hijriah), pembayaran, tabungan, laporan keuangan otomatis.'],
    ['📝','PPDB Online','Pendaftaran santri baru, unggah dokumen, ujian seleksi, registrasi ulang digital.'],
    ['📚','Akademik','Periode akademik, pelajaran, jadwal, penilaian, rapor otomatis per kelas.'],
    ['🏠','Asrama','Blok, kamar, kapasitas, penempatan santri, biaya asrama terhubung keuangan.'],
    ['📖','Tahfidz','Pencatatan hafalan, ziyadah/muraja’ah, penilaian setoran, progres per santri.'],
    ['❤️','Kesehatan & Konseling','Riwayat kesehatan santri, sesi konseling, catatan medis terstruktur.'],
    ['📅','Kegiatan & Perizinan','Kegiatan pondok, izin keluar santri, kunjungan wali dengan persetujuan digital.'],
    ['📱','Notifikasi WhatsApp','Broadcast tagihan, info kegiatan, pengumuman langsung ke WhatsApp wali.'],
    ['🌐','Website Pesantren','Website publik dengan CMS. Profil, artikel, galeri, PPDB — domain sendiri.'],
    ['👨‍👩‍👦','Portal Wali','Dashboard wali santri: tagihan, pembayaran, perkembangan anak real-time.'],
    ['📊','Laporan & Analitik','Dashboard interaktif, ekspor Excel/PDF, laporan keuangan, statistik kesantrian.'],
  ];
  const WHY = [
    ['🔒','Data aman & terisolasi','Setiap pesantren punya ruang data terpisah. Data Anda tidak tercampur tenant lain.'],
    ['🏷️','White-label 100%','Domain, logo, dan warna sendiri. Tanpa watermark platform — sepenuhnya identitas pesantren.'],
    ['☁️','Cloud — tanpa instal','Akses dari mana saja. Tanpa server atau tim IT. Pembaruan & keamanan kami yang urus.'],
    ['🔧','Bisa disesuaikan','Periode Masehi/Hijriah, skala nilai, jenis tagihan, istilah pondok — semua mengikuti Anda.'],
  ];
  const TESTI = [
    ['Sejak pakai pesantren.online, rekap tagihan yang dulu berhari-hari kini selesai dalam hitungan menit. Wali pun tenang karena selalu dapat kabar.','AK','KH. Abdul Karim','Pimpinan, Ponpes Darul Falah'],
    ['Yang paling terasa: wali santri jadi mudah memantau pembayaran dan hafalan anaknya lewat aplikasi. Kepercayaan ke pondok meningkat.','SM','Ust. Mahmud','Bendahara, Ponpes Asy-Syifaa'],
    ['Migrasi dari Excel ke sistem ternyata mulus. Tim mereka mendampingi sampai semua staf kami lancar memakainya.','HN','Hj. Nurhayati','Tata Usaha, Ponpes Nurul Iman'],
  ];
  const PLANS = [
    ['Gratis','Rp 0','','Untuk pondok kecil & uji coba.',['Modul Kesantrian','Website (CMS)','Hingga 100 santri','Dukungan komunitas'],false,'Mulai gratis'],
    ['Dasar','Rp 2.000','/santri/bln','Operasional inti pesantren.',['Kesantrian & Keuangan','PPDB & Website','Notifikasi WhatsApp','Dukungan prioritas'],true,'Pilih Dasar'],
    ['Pro','Rp 3.500','/santri/bln','Lengkap dengan akademik.',['Semua di Dasar','Akademik & Rapor','Asrama, Tahfidz, Kesehatan','Laporan & analitik'],false,'Pilih Pro'],
    ['Custom','Hubungi','','Untuk yayasan & multi-unit.',['Semua modul','Akuntansi (Buku Besar)','SLA & onboarding khusus','Integrasi & API'],false,'Hubungi kami'],
  ];
  const FAQ = [
    ['Apakah data pesantren kami aman?','Sangat aman. Setiap pesantren mendapat ruang data terpisah (terisolasi), terenkripsi, dengan cadangan rutin. Data Anda tidak pernah tercampur dengan pesantren lain.'],
    ['Perlu server atau tim IT sendiri?','Tidak. pesantren.online berbasis cloud — kami yang mengelola server, keamanan, dan pembaruan. Anda cukup membuka lewat browser atau aplikasi.'],
    ['Bisa migrasi data lama dari Excel?','Bisa. Tim kami membantu impor data santri, kelas, dan keuangan dari Excel saat onboarding, lalu mendampingi sampai staf lancar.'],
    ['Apakah benar-benar bisa pakai nama & domain sendiri?','Ya, 100% white-label. Domain, logo, warna, bahkan istilah pondok bisa disesuaikan. Tampil sepenuhnya sebagai sistem milik pesantren Anda.'],
    ['Berapa lama sampai bisa dipakai?','Umumnya siap dalam 1 hari kerja untuk konfigurasi dasar. Pendampingan penuh tersedia untuk migrasi data yang lebih besar.'],
  ];

  const esc = (s) => String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;');
  const modcard = ([ic,h,p]) => `<div class="card reveal"><span class="ic">${ic}</span><h3>${esc(h)}</h3><p>${esc(p)}</p></div>`;
  const why = WHY.map(([ic,h,p]) => `<div class="card reveal" style="text-align:center;box-shadow:var(--ring-card)"><span class="ic" style="margin:0 auto">${ic}</span><h3>${esc(h)}</h3><p>${esc(p)}</p></div>`).join('');
  const check = '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.6" stroke-linecap="round" stroke-linejoin="round"><path d="m5 13 4 4L19 7"/></svg>';
  const testi = TESTI.map(([q,iv,n,r]) => `<div class="testi reveal"><div class="stars">★★★★★</div><p>"${esc(q)}"</p><div class="who"><span class="av">${iv}</span><span><b>${esc(n)}</b><span>${esc(r)}</span></span></div></div>`).join('');
  const price = PLANS.map(([n,amt,u,d,feats,pop,cta]) => `<div class="price reveal${pop?' pop':''}">${pop?'<span class="pop-tag">Paling populer</span>':''}<div class="pn">${esc(n)}</div><div class="pp"><span class="amt">${amt}</span><span class="u">${u}</span></div><div class="pd">${esc(d)}</div><ul>${feats.map(f=>`<li>${check}${esc(f)}</li>`).join('')}</ul><a class="btn ${pop?'btn-primary':'btn-outline'}" style="justify-content:center" href="${cta.includes('Hubungi')?WA:DEMO}"${cta.includes('Hubungi')?'':' target="_blank" rel="noopener"'}>${esc(cta)}</a></div>`).join('');
  const faq = FAQ.map(([q,a]) => `<details class="qa reveal"><summary>${esc(q)}<span class="pm">+</span></summary><div class="a">${esc(a)}</div></details>`).join('');

  document.getElementById('modgrid').innerHTML = MODULES.map(modcard).join('');
  document.getElementById('whygrid').innerHTML = why;
  document.getElementById('testigrid').innerHTML = testi;
  document.getElementById('pricegrid').innerHTML = price;
  document.getElementById('faq').innerHTML = faq;

  const pat = `<svg width="100%" height="100%"><defs><pattern id="rh" width="52" height="52" patternUnits="userSpaceOnUse"><g transform="translate(26 26)" fill="none" stroke="#fff" stroke-width="1"><rect x="-11" y="-11" width="22" height="22"/><rect x="-11" y="-11" width="22" height="22" transform="rotate(45)"/></g></pattern></defs><rect width="100%" height="100%" fill="url(#rh)"/></svg>`;
  ['aipat','ctapat'].forEach(id => { const el = document.getElementById(id); if (el) el.innerHTML = pat; });
</script>
</body>
</html>
