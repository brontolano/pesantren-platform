<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ config('app.name') }}</title>
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: 'Segoe UI', system-ui, -apple-system, sans-serif;
            background: #0f172a;
            color: #e2e8f0;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }
        .hero {
            flex: 1;
            display: flex;
            align-items: center;
            justify-content: center;
            text-align: center;
            padding: 2rem;
        }
        .hero-content { max-width: 640px; }
        .hero h1 {
            font-size: 2.5rem;
            font-weight: 700;
            color: #fff;
            margin-bottom: 0.75rem;
        }
        .hero h1 span { color: #10b981; }
        .hero p {
            font-size: 1.125rem;
            color: #94a3b8;
            line-height: 1.7;
            margin-bottom: 2rem;
        }
        .tenants {
            display: flex;
            flex-wrap: wrap;
            gap: 1rem;
            justify-content: center;
            margin-bottom: 2rem;
        }
        .tenant-card {
            background: #1e293b;
            border: 1px solid #334155;
            border-radius: 12px;
            padding: 1.5rem 2rem;
            text-decoration: none;
            color: #e2e8f0;
            transition: all 0.2s;
            min-width: 220px;
        }
        .tenant-card:hover {
            border-color: #10b981;
            background: #1a2536;
            transform: translateY(-2px);
        }
        .tenant-card h3 {
            font-size: 1.125rem;
            color: #fff;
            margin-bottom: 0.25rem;
        }
        .tenant-card small {
            color: #64748b;
            font-size: 0.85rem;
        }
        .tenant-card .arrow {
            color: #10b981;
            font-size: 0.85rem;
            margin-top: 0.75rem;
            display: block;
        }
        .badge {
            display: inline-block;
            background: #10b981;
            color: #fff;
            font-size: 0.75rem;
            font-weight: 600;
            padding: 0.2rem 0.6rem;
            border-radius: 9999px;
            margin-bottom: 1rem;
        }
        footer {
            text-align: center;
            padding: 1.5rem;
            color: #475569;
            font-size: 0.85rem;
            border-top: 1px solid #1e293b;
        }
    </style>
</head>
<body>
    <div class="hero">
        <div class="hero-content">
            <div class="badge">Tahap T0 — Fondasi</div>
            <h1>Pesantren <span>Platform</span></h1>
            <p>
                Sistem administrasi pesantren terpadu berbasis cloud.
                Satu platform untuk semua kebutuhan pengelolaan — dari santri, keuangan,
                hingga komunikasi dengan wali.
            </p>

            <div class="tenants">
                @foreach($tenants as $tenant)
                <a href="http://{{ $tenant->id }}.localhost:8000/admin" class="tenant-card">
                    <h3>{{ $tenant->name }}</h3>
                    <small>{{ $tenant->id }}.localhost</small>
                    <span class="arrow">Masuk Panel Admin →</span>
                </a>
                @endforeach
            </div>
        </div>
    </div>

    <footer>
        &copy; {{ date('Y') }} Pesantren Platform &mdash; Multi-Tenant SaaS
    </footer>
</body>
</html>
