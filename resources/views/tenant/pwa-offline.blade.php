<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sedang Offline — {{ $name }}</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
            background: {{ $backgroundColor }};
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 2rem;
        }
        .card {
            background: white;
            border-radius: 2rem;
            padding: 3rem 2.5rem;
            max-width: 460px;
            width: 100%;
            text-align: center;
            box-shadow: inset 0 0 0 1px rgba(15,23,42,0.08), 0 16px 44px -24px rgba(17,17,17,0.22);
        }
        .icon {
            width: 64px;
            height: 64px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 1.5rem;
            font-size: 2rem;
            background: {{ $primaryColor }}22;
        }
        h1 {
            font-size: 1.4rem;
            font-weight: 700;
            color: #0f172a;
            margin-bottom: 0.75rem;
        }
        p {
            color: #64748b;
            line-height: 1.7;
            margin-bottom: 1.5rem;
        }
        .tenant-name { font-weight: 600; color: #1e293b; }
        button.coba-lagi {
            display: inline-block;
            padding: 0.75rem 1.75rem;
            border-radius: 0.75rem;
            border: none;
            background: {{ $primaryColor }};
            color: #fff;
            font-weight: 600;
            font-size: 0.95rem;
            cursor: pointer;
        }
        /* AlbaKids (tenant custom): tampilan asli, jangan ikut skin lain. */
        body.tenant-albakids { background: #f8fafc; }
        body.tenant-albakids .card { box-shadow: 0 20px 60px -20px rgba(15,23,42,0.15); }
    </style>
</head>
<body class="{{ \App\Support\TenantCustomizationRegistry::bodyClass() }}">
    <div class="card">
        <div class="icon">📡</div>
        <h1>Anda Sedang Offline</h1>
        <p>
            Aplikasi <span class="tenant-name">{{ $name }}</span> tidak dapat memuat halaman ini
            karena tidak ada koneksi internet. Periksa jaringan Anda lalu coba lagi.
        </p>
        <button class="coba-lagi" onclick="window.location.reload()">Coba Lagi</button>
    </div>
</body>
</html>
