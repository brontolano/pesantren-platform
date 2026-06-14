<!DOCTYPE html>
<html lang="id">
<head>
    @include('tenant.partials.launcher-head', ['pesantren' => $pesantren, 'primaryColor' => $primaryColor, 'title' => 'Masuk'])
    <style>
        body{display:flex}
        .split{display:flex;width:100%;min-height:100dvh}
        /* ── Panel kiri (hero hijau) — desktop saja ── */
        .aside{position:relative;display:none;flex:1.25;overflow:hidden;color:#fff;padding:56px 52px;
            background:linear-gradient(160deg,var(--brand) 0%,var(--brand-strong) 100%)}
        .aside .mosque{position:absolute;left:0;right:0;bottom:0;z-index:1;color:rgba(255,255,255,.10)}
        .aside .content{position:relative;z-index:2;display:flex;flex-direction:column;height:100%}
        .aside .brand{display:flex;align-items:center;gap:14px}
        .aside .brandmark{width:58px;height:58px;padding:7px;box-shadow:0 10px 30px rgba(0,0,0,.25)}
        .aside .pname{font-weight:800;font-size:1.15rem;line-height:1.25}
        .aside .hero{margin-top:auto}
        .aside .bismillah{font-size:1.45rem;opacity:.92;margin-bottom:18px}
        .aside h2{font-size:clamp(1.5rem,2.4vw,2.05rem);font-weight:800;line-height:1.2;letter-spacing:-.01em}
        .aside .lead{margin-top:12px;opacity:.85;font-size:.95rem;max-width:38ch;line-height:1.6}
        .aside .orn{margin-top:26px;max-width:280px}
        /* ── Panel kanan (form) ── */
        .main{flex:1;display:flex;flex-direction:column;align-items:center;justify-content:center;padding:40px 22px}
        .card{width:100%;max-width:398px}
        .top{text-align:center}
        .top .brandmark{width:72px;height:72px;margin:0 auto 14px;padding:9px;box-shadow:var(--shadow-soft);border:1px solid var(--border)}
        .top h1{font-size:1.1rem;font-weight:800}
        .top .sub{font-size:.85rem;color:var(--muted);margin-top:3px}
        form{margin-top:22px;display:flex;flex-direction:column;gap:14px}
        .field label{display:block;font-size:.8rem;font-weight:600;margin-bottom:6px}
        .field input{width:100%;border:1px solid var(--border);background:#fff;border-radius:13px;padding:12px 14px;
            font-size:.95rem;font-family:inherit;color:var(--ink);transition:border-color .15s,box-shadow .15s}
        .field input:focus{outline:none;border-color:var(--brand);box-shadow:0 0 0 3px color-mix(in srgb,var(--brand) 18%,transparent)}
        .remember{display:flex;align-items:center;gap:8px;font-size:.83rem;color:var(--muted);font-weight:500;cursor:pointer}
        .remember input{width:16px;height:16px;accent-color:var(--brand)}
        .err{background:#fef2f2;border:1px solid #fecaca;color:#b91c1c;border-radius:11px;padding:10px 13px;font-size:.83rem;margin-top:18px}
        .btn{appearance:none;border:none;cursor:pointer;width:100%;border-radius:9999px;padding:13px 18px;font-size:.96rem;
            font-weight:700;font-family:inherit;color:#fff;min-height:48px;box-shadow:var(--shadow-brand);
            background:linear-gradient(135deg,var(--brand),var(--brand-strong));transition:transform .15s}
        .btn:hover{transform:translateY(-1px)}.btn:active{transform:translateY(0)}
        .alt{margin-top:24px}
        .alt-grid{display:grid;grid-template-columns:1fr 1fr;gap:10px;margin-top:14px}
        .alt-card{display:flex;flex-direction:column;gap:3px;border:1px solid var(--border);border-radius:14px;
            padding:12px 13px;background:#fff;transition:border-color .15s,transform .15s}
        .alt-card:hover{border-color:var(--gold);transform:translateY(-2px)}
        .alt-card .ttl{font-weight:700;font-size:.85rem;display:flex;align-items:center;gap:7px}
        .alt-card .ds{font-size:.71rem;color:var(--muted);line-height:1.35}
        .alt-card svg{width:18px;height:18px;stroke:var(--brand);fill:none;stroke-width:1.6;flex-shrink:0}
        footer{margin-top:26px;text-align:center;font-size:.72rem;color:var(--muted)}
        footer a{color:var(--brand);font-weight:600}
        @media(min-width:900px){.aside{display:block}}
    </style>
</head>
<body>
<div class="split">
    <aside class="aside">
        @include('tenant.partials.islamic-bg', ['id' => 'pat-login', 'color' => '#ffffff', 'opacity' => 0.07])
        <div class="mosque">@include('tenant.partials.mosque')</div>
        <div class="content">
            <div class="brand">
                <div class="brandmark">@include('tenant.partials.brandmark', ['logoUrl' => $logoUrl, 'pesantren' => $pesantren])</div>
                <div class="pname">{{ $pesantren }}</div>
            </div>
            <div class="hero">
                <div class="arabic bismillah">بِسْمِ اللَّهِ الرَّحْمَٰنِ الرَّحِيمِ</div>
                <h2>Selamat datang di Portal Sistem</h2>
                <p class="lead">Pendidikan adab, ilmu, dan layanan santri dalam satu sistem terpadu.</p>
                <div class="orn">@include('tenant.partials.ornament', ['color' => 'var(--gold)'])</div>
            </div>
        </div>
    </aside>

    <main class="main">
        <div class="card">
            <div class="top">
                <div class="brandmark">@include('tenant.partials.brandmark', ['logoUrl' => $logoUrl, 'pesantren' => $pesantren])</div>
                <h1>Masuk</h1>
                <div class="sub">Portal Sistem {{ $pesantren }}</div>
            </div>

            @if ($errors->any())
                <div class="err">{{ $errors->first() }}</div>
            @endif

            <form method="POST" action="{{ route('tenant.masuk.store') }}">
                @csrf
                <div class="field">
                    <label for="email">Email</label>
                    <input id="email" name="email" type="email" autocomplete="username" required autofocus value="{{ old('email') }}" placeholder="nama@pesantren.id">
                </div>
                <div class="field">
                    <label for="password">Kata Sandi</label>
                    <input id="password" name="password" type="password" autocomplete="current-password" required placeholder="••••••••">
                </div>
                <label class="remember"><input type="checkbox" name="remember" value="1"> Ingat saya di perangkat ini</label>
                <button class="btn" type="submit">Masuk</button>
            </form>

            <div class="alt">
                @include('tenant.partials.ornament', ['color' => 'var(--gold-soft)'])
                <div class="alt-grid">
                    <a class="alt-card" href="{{ $waliLoginUrl }}">
                        <span class="ttl">
                            <svg viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19.13a9.4 9.4 0 0 0 2.63.37 9.34 9.34 0 0 0 4.12-.95 4.13 4.13 0 0 0-7.53-2.5M15 19.13v.1A12.3 12.3 0 0 1 8.62 21c-2.33 0-4.51-.65-6.37-1.77v-.1a6.38 6.38 0 0 1 11.96-3.07M12 6.38a3.38 3.38 0 1 1-6.75 0 3.38 3.38 0 0 1 6.75 0Zm8.25 2.25a2.63 2.63 0 1 1-5.25 0 2.63 2.63 0 0 1 5.25 0Z"/></svg>
                            Wali Santri
                        </span>
                        <span class="ds">Orang tua / wali santri aktif</span>
                    </a>
                    <a class="alt-card" href="{{ $ppdbUrl }}">
                        <span class="ttl">
                            <svg viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4.26 10.15a60 60 0 0 0-.49 6.35A48.6 48.6 0 0 1 12 20.9a48.6 48.6 0 0 1 8.23-4.4 60 60 0 0 0-.49-6.35M12 13.49a50.7 50.7 0 0 1 7.74-3.34L12 3.49 4.26 10.15A50.7 50.7 0 0 1 12 13.49Z"/></svg>
                            Calon Santri
                        </span>
                        <span class="ds">Pendaftaran santri baru (PPDB)</span>
                    </a>
                </div>
            </div>

            <footer>&copy; {{ date('Y') }} {{ $pesantren }} &middot; <a href="/kebijakan-privasi">Kebijakan Privasi</a></footer>
        </div>
    </main>
</div>
</body>
</html>
