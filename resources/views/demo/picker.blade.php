<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>Coba Demo — {{ $namaPesantren }}</title>
    <style>
        :root { --brand: {{ $warna }}; }
        * { box-sizing: border-box; }
        body {
            margin: 0; font-family: system-ui, -apple-system, "Segoe UI", Roboto, sans-serif;
            color: #1f2937; background: linear-gradient(180deg,#fffdf9 0%,#f6f7f5 100%);
            min-height: 100vh;
        }
        .wrap { max-width: 1040px; margin: 0 auto; padding: 28px 20px 64px; }
        header { text-align: center; padding: 24px 0 8px; }
        .badge {
            display: inline-block; font-size: 12px; font-weight: 700; letter-spacing: .08em;
            text-transform: uppercase; color: var(--brand); background: color-mix(in srgb, var(--brand) 12%, white);
            padding: 6px 14px; border-radius: 999px;
        }
        h1 { font-size: 28px; margin: 14px 0 6px; }
        .sub { color: #6b7280; font-size: 15px; max-width: 560px; margin: 0 auto; line-height: 1.5; }
        .note {
            margin: 18px auto 0; max-width: 620px; font-size: 13px; color: #6b7280;
            background: #fff; border: 1px solid #eee; border-radius: 14px; padding: 12px 16px;
        }
        section { margin-top: 34px; }
        h2 { font-size: 14px; text-transform: uppercase; letter-spacing: .06em; color: #9ca3af; margin: 0 0 14px; }
        .grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(220px, 1fr)); gap: 14px; }
        .card {
            display: flex; align-items: center; gap: 14px; text-decoration: none; color: inherit;
            background: #fff; border: 1px solid #ececec; border-radius: 16px; padding: 16px 18px;
            transition: transform .12s ease, box-shadow .12s ease, border-color .12s ease;
        }
        .card:hover { transform: translateY(-2px); box-shadow: 0 12px 28px -18px rgba(15,23,42,.35); border-color: color-mix(in srgb, var(--brand) 40%, #ececec); }
        .ikon {
            width: 46px; height: 46px; flex: none; display: grid; place-items: center; font-size: 24px;
            border-radius: 13px; background: color-mix(in srgb, var(--brand) 10%, white);
        }
        .label { font-weight: 700; font-size: 15px; }
        .desc { font-size: 13px; color: #6b7280; margin-top: 2px; }
        footer { text-align: center; margin-top: 40px; }
        .tamu { display: inline-block; text-decoration: none; color: var(--brand); font-weight: 600; font-size: 14px;
                border: 1px solid color-mix(in srgb, var(--brand) 35%, #fff); border-radius: 999px; padding: 10px 22px; }
        .tamu:hover { background: color-mix(in srgb, var(--brand) 8%, white); }
    </style>
</head>
<body>
    <div class="wrap">
        <header>
            <span class="badge">Mode Demo</span>
            <h1>Coba {{ $namaPesantren }}</h1>
            <p class="sub">Pilih peran untuk langsung masuk dan menjelajah aplikasi dari sudut pandang peran tersebut — tanpa perlu kata sandi.</p>
            <p class="note">🔒 Ini lingkungan <strong>demo</strong> berisi data contoh. Silakan klik-klik bebas; perubahan tidak memengaruhi data asli pesantren mana pun.</p>
        </header>

        @foreach ($kelompok as $grup)
            <section>
                <h2>{{ $grup['judul'] }}</h2>
                <div class="grid">
                    @foreach ($grup['item'] as $it)
                        <a class="card" href="/demo-masuk/{{ $it['peran'] }}">
                            <span class="ikon">{{ $it['ikon'] }}</span>
                            <span>
                                <span class="label">{{ $it['label'] }}</span>
                                <span class="desc">{{ $it['desc'] }}</span>
                            </span>
                        </a>
                    @endforeach
                </div>
            </section>
        @endforeach

        <footer>
            <a class="tamu" href="/">Lihat sebagai Tamu (tanpa login) →</a>
        </footer>
    </div>
</body>
</html>
