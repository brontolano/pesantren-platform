<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="robots" content="noindex">
    <title>{{ $pesantren }} — Portal Sistem</title>
    <style>
        *,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
        :root{color-scheme:dark}
        body{
            font-family:system-ui,-apple-system,"Segoe UI",Roboto,Helvetica,Arial,sans-serif;
            min-height:100dvh;color:#e9eef3;
            background:
                radial-gradient(1100px 600px at 50% -8%, rgba(45,212,191,.16), transparent 60%),
                radial-gradient(900px 500px at 100% 110%, rgba(56,189,248,.12), transparent 60%),
                linear-gradient(160deg,#0b1220 0%,#0e1a2b 45%,#0b1a1f 100%);
            display:flex;flex-direction:column;
        }
        .wrap{width:100%;max-width:780px;margin:0 auto;padding:48px 22px 28px;flex:1;display:flex;flex-direction:column}
        header{text-align:center;margin-bottom:42px}
        .badge{
            width:74px;height:74px;margin:0 auto 18px;border-radius:20px;display:grid;place-items:center;
            background:linear-gradient(135deg,rgba(255,255,255,.14),rgba(255,255,255,.04));
            border:1px solid rgba(255,255,255,.14);box-shadow:0 10px 30px rgba(0,0,0,.35);backdrop-filter:blur(8px)
        }
        .badge svg{width:38px;height:38px;stroke:#5eead4;fill:none;stroke-width:1.5}
        h1{font-size:clamp(1.05rem,4.4vw,1.55rem);font-weight:700;line-height:1.3;letter-spacing:-.01em}
        .subtitle{margin-top:8px;font-size:.86rem;color:#9fb0c0;font-weight:500}
        .grid{
            display:grid;grid-template-columns:repeat(auto-fit,minmax(132px,1fr));
            gap:26px 14px;max-width:520px;margin:0 auto;width:100%
        }
        .tile{text-decoration:none;color:inherit;display:flex;flex-direction:column;align-items:center;gap:11px;-webkit-tap-highlight-color:transparent}
        .icon{
            width:78px;height:78px;border-radius:23px;display:grid;place-items:center;position:relative;
            box-shadow:0 12px 26px rgba(0,0,0,.40),inset 0 1px 0 rgba(255,255,255,.25);
            transition:transform .18s cubic-bezier(.2,.8,.2,1),box-shadow .18s
        }
        .icon::after{content:"";position:absolute;inset:0;border-radius:inherit;background:linear-gradient(180deg,rgba(255,255,255,.22),transparent 42%);pointer-events:none}
        .icon svg{width:39px;height:39px;stroke:#fff;fill:none;stroke-width:1.6;stroke-linecap:round;stroke-linejoin:round}
        .tile:hover .icon,.tile:focus-visible .icon{transform:translateY(-5px) scale(1.05);box-shadow:0 18px 34px rgba(0,0,0,.5),inset 0 1px 0 rgba(255,255,255,.3)}
        .tile:active .icon{transform:translateY(-1px) scale(.99)}
        .label{font-weight:600;font-size:.96rem}
        .desc{font-size:.73rem;color:#90a1b3;text-align:center;line-height:1.35;max-width:128px}
        footer{margin-top:46px;text-align:center;font-size:.74rem;color:#6c7c8c;padding-top:18px}
        footer a{color:#8aa0b2;text-decoration:none}
        @media (max-width:380px){.grid{grid-template-columns:repeat(2,1fr);gap:24px 10px}}
    </style>
</head>
<body>
    @php
        $apps = [
            [
                'label' => 'Administrasi',
                'desc'  => 'Pengurus &amp; staf pesantren',
                'url'   => '/admin',
                'grad'  => ['#38bdf8', '#0369a1'],
                'icon'  => '<path d="M9 12.75 11.25 15 15 9.75m-3-7.036A11.96 11.96 0 0 1 3.6 6 12 12 0 0 0 3 9.75c0 5.59 3.82 10.29 9 11.62 5.18-1.33 9-6.03 9-11.62 0-1.31-.21-2.57-.6-3.75h-.15c-3.2 0-6.1-1.25-8.25-3.29Z"/>',
            ],
            [
                'label' => 'Portal Peserta',
                'desc'  => 'Calon santri &middot; PPDB',
                'url'   => '/portal',
                'grad'  => ['#2dd4bf', '#0f766e'],
                'icon'  => '<path d="M4.26 10.15a60 60 0 0 0-.49 6.35A48.6 48.6 0 0 1 12 20.9a48.6 48.6 0 0 1 8.23-4.4 60 60 0 0 0-.49-6.35m-15.48 0a51 51 0 0 0-2.66-.81A59.9 59.9 0 0 1 12 3.49a59.9 59.9 0 0 1 10.4 5.84c-.9.25-1.78.52-2.66.82m-15.48 0A50.7 50.7 0 0 1 12 13.49a50.7 50.7 0 0 1 7.74-3.34M6.75 15a.75.75 0 1 0 0-1.5.75.75 0 0 0 0 1.5Zm0 0v-3.68A55 55 0 0 1 12 8.44"/>',
            ],
            [
                'label' => 'Portal Wali',
                'desc'  => 'Orang tua / wali santri',
                'url'   => '/wali',
                'grad'  => ['#a78bfa', '#6d28d9'],
                'icon'  => '<path d="M15 19.13a9.4 9.4 0 0 0 2.63.37 9.34 9.34 0 0 0 4.12-.95 4.13 4.13 0 0 0-7.53-2.5M15 19.13v0c0-1.11-.29-2.16-.79-3.07M15 19.13v.1A12.3 12.3 0 0 1 8.62 21c-2.33 0-4.51-.65-6.37-1.77v-.1a6.38 6.38 0 0 1 11.96-3.07M12 6.38a3.38 3.38 0 1 1-6.75 0 3.38 3.38 0 0 1 6.75 0Zm8.25 2.25a2.63 2.63 0 1 1-5.25 0 2.63 2.63 0 0 1 5.25 0Z"/>',
            ],
            [
                'label'    => 'Website',
                'desc'     => 'Profil &amp; informasi publik',
                'url'      => 'https://asy-syifaa.com',
                'grad'     => ['#fbbf24', '#b45309'],
                'external' => true,
                'icon'     => '<path d="M12 21a9 9 0 0 0 8.72-6.75M12 21a9 9 0 0 1-8.72-6.75M12 21c2.49 0 4.5-4.03 4.5-9S14.49 3 12 3m0 18c-2.49 0-4.5-4.03-4.5-9S9.51 3 12 3m0 0a9 9 0 0 1 7.84 4.58M12 3a9 9 0 0 0-7.84 4.58m15.68 0A11.95 11.95 0 0 1 12 10.5c-3 0-5.74-1.1-7.84-2.92m15.68 0A8.96 8.96 0 0 1 21 12c0 .78-.1 1.53-.28 2.25m0 0A17.9 17.9 0 0 1 12 16.5c-3.16 0-6.13-.82-8.72-2.25m0 0A9 9 0 0 1 3 12c0-1.6.42-3.11 1.16-4.42"/>',
            ],
        ];
    @endphp

    <div class="wrap">
        <header>
            <div class="badge" aria-hidden="true">
                <svg viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 21v-8.25M15.75 21v-8.25M8.25 21v-8.25M3 9l9-6 9 6m-1.5 12V10.33A48.4 48.4 0 0 0 12 9.75c-2.55 0-5.06.2-7.5.58V21M3 21h18M12 6.75h.01v.01H12V6.75Z"/></svg>
            </div>
            <h1>{{ $pesantren }}</h1>
            <p class="subtitle">Portal Sistem — pilih aplikasi</p>
        </header>

        <main class="grid">
            @foreach ($apps as $a)
                <a class="tile" href="{{ $a['url'] }}" @if (! empty($a['external'])) target="_blank" rel="noopener noreferrer" @endif>
                    <span class="icon" style="background:linear-gradient(135deg,{{ $a['grad'][0] }},{{ $a['grad'][1] }})">
                        <svg viewBox="0 0 24 24">{!! $a['icon'] !!}</svg>
                    </span>
                    <span class="label">{{ $a['label'] }}</span>
                    <span class="desc">{!! $a['desc'] !!}</span>
                </a>
            @endforeach
        </main>

        <footer>
            &copy; {{ date('Y') }} {{ $pesantren }} &middot; Sistem Manajemen Pesantren
        </footer>
    </div>
</body>
</html>
