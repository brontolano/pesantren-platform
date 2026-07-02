<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title') — Pesantren Platform</title>
    <meta name="robots" content="noindex, follow">
    <link rel="canonical" href="{{ url()->current() }}">
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=plus-jakarta-sans:400,500,600,700|jost:500,600,700" rel="stylesheet">
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: 'Plus Jakarta Sans', system-ui, sans-serif;
            background: #fffaf3; color: #1e293b;
            line-height: 1.7; -webkit-font-smoothing: antialiased;
        }
        a { color: #059669; }
        a:hover { text-decoration: underline; }
        nav {
            background: rgba(255,250,243,0.9); backdrop-filter: blur(8px); border-bottom: 1px solid #efe7da;
            padding: 0 2rem; position: sticky; top: 0; z-index: 50;
        }
        .nav-inner {
            max-width: 800px; margin: 0 auto;
            display: flex; align-items: center; justify-content: space-between;
            height: 56px;
        }
        .nav-logo { font-family: 'Jost', sans-serif; font-size: 1rem; font-weight: 700; color: #111; text-decoration: none; }
        .nav-logo span { color: #c9a24a; }
        .nav-back { font-size: 0.85rem; color: #64748b; text-decoration: none; }
        .nav-back:hover { color: #059669; }
        main {
            max-width: 800px; margin: 0 auto;
            padding: 3rem 2rem 5rem;
        }
        h1 { font-family: 'Jost', sans-serif; font-size: 1.75rem; font-weight: 700; margin-bottom: 0.5rem; color: #0f172a; letter-spacing: -0.01em; }
        .updated { font-size: 0.85rem; color: #94a3b8; margin-bottom: 2.5rem; }
        h2 { font-family: 'Jost', sans-serif; font-size: 1.2rem; font-weight: 700; margin-top: 2.5rem; margin-bottom: 0.75rem; color: #0f172a; letter-spacing: -0.01em; }
        h3 { font-size: 1.05rem; font-weight: 600; margin-top: 1.5rem; margin-bottom: 0.5rem; }
        p { margin-bottom: 1rem; color: #334155; font-size: 0.95rem; }
        ul, ol { margin-bottom: 1rem; padding-left: 1.5rem; }
        li { margin-bottom: 0.4rem; color: #334155; font-size: 0.95rem; }
        .info-box {
            background: #ecfdf5; border-left: 4px solid #059669;
            padding: 1rem 1.25rem; border-radius: 0 8px 8px 0;
            margin: 1.5rem 0; font-size: 0.9rem;
        }
        footer {
            text-align: center; padding: 2rem;
            color: #94a3b8; font-size: 0.8rem;
            border-top: 1px solid #efe7da;
        }
        footer a { color: #059669; }
    </style>
</head>
<body>

<nav>
    <div class="nav-inner">
        <a href="/" class="nav-logo">Pesantren<span>.</span>online</a>
        <a href="/" class="nav-back">&larr; Kembali ke Beranda</a>
    </div>
</nav>

<main>
    @yield('content')
</main>

<footer>
    Pesantren Platform &copy; {{ date('Y') }} <a href="https://brontolano.com" target="_blank" rel="noopener">PT Rojo Bronto Lano</a>
</footer>

</body>
</html>
