<!DOCTYPE html>
<html lang="id">
<head>
    @include('tenant.partials.launcher-head', ['pesantren' => $pesantren, 'primaryColor' => $primaryColor, 'title' => 'Beranda'])
    <style>
        body{display:flex;flex-direction:column;align-items:center}
        .shell{width:100%;max-width:680px;flex:1;display:flex;flex-direction:column;padding:0 20px 40px}
        /* ── Header hijau ── */
        .hero{position:relative;overflow:hidden;color:#fff;border-radius:0 0 28px 28px;padding:28px 24px 24px;
            box-shadow:var(--shadow-soft);background:linear-gradient(160deg,var(--brand),var(--brand-strong))}
        .hero .row{position:relative;z-index:2;display:flex;align-items:center;gap:14px}
        .hero .brandmark{width:52px;height:52px;padding:6px;flex-shrink:0}
        .hero .who{flex:1;min-width:0}
        .hero .salam{font-size:.8rem;opacity:.85}
        .hero .name{font-weight:800;font-size:1.05rem;line-height:1.2;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
        .hero .logout{appearance:none;cursor:pointer;font-family:inherit;color:#fff;font-size:.78rem;font-weight:600;
            border:1px solid rgba(255,255,255,.4);background:rgba(255,255,255,.12);border-radius:9999px;padding:7px 14px}
        .hero .logout:hover{background:rgba(255,255,255,.2)}
        .hero .tag{position:relative;z-index:2;margin-top:14px;font-size:.84rem;opacity:.9}
        /* ── Kartu tujuan ── */
        .cards{display:grid;grid-template-columns:1fr;gap:15px;margin-top:24px}
        .tile{display:flex;align-items:center;gap:15px;background:#fff;border:1px solid var(--border);
            border-radius:var(--radius);padding:17px;box-shadow:var(--shadow-soft);transition:transform .16s,box-shadow .16s}
        .tile:hover{transform:translateY(-3px)}
        .tile .ic{width:54px;height:54px;border-radius:16px;display:grid;place-items:center;flex-shrink:0}
        .tile .ic svg{width:27px;height:27px;stroke:#fff;fill:none;stroke-width:1.6;stroke-linecap:round;stroke-linejoin:round}
        .tile .meta{flex:1;min-width:0}
        .tile .meta .t{font-weight:700;font-size:1rem}
        .tile .meta .d{font-size:.79rem;color:var(--muted);margin-top:2px}
        .tile .arrow{color:var(--muted);flex-shrink:0;font-size:1.1rem}
        .tile.app .ic{background:linear-gradient(135deg,var(--brand),var(--brand-strong))}
        .tile.admin .ic{background:linear-gradient(135deg,var(--gold),#a9822f)}
        .tile.web .ic{background:linear-gradient(135deg,#0ea5b7,#0f766e)}
        .desk-hint{display:none;margin-left:8px;font-size:.62rem;font-weight:700;color:#8a6a22;
            background:var(--gold-soft);padding:2px 8px;border-radius:9999px;vertical-align:middle;white-space:nowrap}
        .empty{background:#fff;border:1px dashed var(--border);border-radius:var(--radius);padding:24px;text-align:center;color:var(--muted);font-size:.88rem}
        footer{margin-top:30px;text-align:center;font-size:.72rem;color:var(--muted)}
        /* ── Desktop ── */
        @media(min-width:900px){
            .cards{grid-template-columns:1fr 1fr}
            .tile.web{grid-column:1/-1}
        }
        /* ── Mobile: Panel Administrasi diredup (optimal di desktop) ── */
        @media(max-width:899px){
            .tile.admin{opacity:.62}
            .tile.admin .desk-hint{display:inline-block}
        }
    </style>
</head>
<body>
<div class="shell">
    <header class="hero">
        @include('tenant.partials.islamic-bg', ['id' => 'pat-hub', 'color' => '#ffffff', 'opacity' => 0.08])
        <div class="row">
            <div class="brandmark">@include('tenant.partials.brandmark', ['logoUrl' => $logoUrl, 'pesantren' => $pesantren])</div>
            <div class="who">
                <div class="salam">Assalamu'alaikum,</div>
                <div class="name">{{ $userName }}</div>
            </div>
            <form method="POST" action="{{ route('tenant.masuk.logout') }}">
                @csrf
                <button type="submit" class="logout">Keluar</button>
            </form>
        </div>
        <div class="tag">{{ $pesantren }} — pilih tujuan</div>
    </header>

    <main class="cards">
        @if ($pwaHome)
            <a class="tile app" href="{{ $pwaHome['url'] }}">
                <span class="ic"><svg viewBox="0 0 24 24">{!! $pwaHome['icon'] !!}</svg></span>
                <span class="meta"><span class="t">{{ $pwaHome['label'] }}</span><span class="d">Aplikasi Anda</span></span>
                <span class="arrow" aria-hidden="true">&rarr;</span>
            </a>
        @endif

        @if ($adminPanelUrl)
            <a class="tile admin" href="{{ $adminPanelUrl }}">
                <span class="ic"><svg viewBox="0 0 24 24"><path d="M9 12.75 11.25 15 15 9.75m-3-7.036A11.96 11.96 0 0 1 3.6 6 12 12 0 0 0 3 9.75c0 5.59 3.82 10.29 9 11.62 5.18-1.33 9-6.03 9-11.62 0-1.31-.21-2.57-.6-3.75h-.15c-3.2 0-6.1-1.25-8.25-3.29Z"/></svg></span>
                <span class="meta"><span class="t">Panel Administrasi<span class="desk-hint">Optimal di desktop</span></span><span class="d">Pengelolaan, data &amp; laporan</span></span>
                <span class="arrow" aria-hidden="true">&rarr;</span>
            </a>
        @endif

        @if (! $pwaHome && ! $adminPanelUrl)
            <div class="empty">Belum ada aplikasi untuk akun Anda.<br>Silakan hubungi admin pesantren.</div>
        @endif

        @if ($websiteUrl)
            <a class="tile web" href="{{ $websiteUrl }}" target="_blank" rel="noopener noreferrer">
                <span class="ic"><svg viewBox="0 0 24 24"><path d="M12 21a9 9 0 0 0 8.72-6.75M12 21a9 9 0 0 1-8.72-6.75M12 21c2.49 0 4.5-4.03 4.5-9S14.49 3 12 3m0 18c-2.49 0-4.5-4.03-4.5-9S9.51 3 12 3m0 0a9 9 0 0 1 8.72 6.75M12 3a9 9 0 0 0-8.72 6.75m0 0A9 9 0 0 0 3 12c0 1.6.42 3.11 1.16 4.42m15.68-8.84A9 9 0 0 1 21 12c0 1.6-.42 3.11-1.16 4.42"/></svg></span>
                <span class="meta"><span class="t">Website</span><span class="d">Profil &amp; informasi publik</span></span>
                <span class="arrow" aria-hidden="true">&rarr;</span>
            </a>
        @endif
    </main>

    <footer>&copy; {{ date('Y') }} {{ $pesantren }} &middot; Sistem Manajemen Pesantren</footer>
</div>
</body>
</html>
