<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Layanan Sementara Tidak Tersedia</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
            background: #f8fafc;
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
            max-width: 480px;
            width: 100%;
            text-align: center;
            box-shadow: 0 20px 60px -20px rgba(15,23,42,0.15);
        }
        .icon {
            width: 64px;
            height: 64px;
            background: #fef3c7;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 1.5rem;
            font-size: 2rem;
        }
        h1 {
            font-size: 1.5rem;
            font-weight: 700;
            color: #0f172a;
            margin-bottom: 0.75rem;
        }
        p {
            color: #64748b;
            line-height: 1.7;
            margin-bottom: 0.5rem;
        }
        .tenant-name {
            font-weight: 600;
            color: #1e293b;
        }
        .contact {
            margin-top: 2rem;
            padding: 1.25rem;
            background: #f1f5f9;
            border-radius: 1rem;
            font-size: 0.875rem;
            color: #475569;
        }
        .contact strong { display: block; margin-bottom: 0.25rem; color: #1e293b; }
    </style>
</head>
<body>
    <div class="card">
        <div class="icon">⚠️</div>
        <h1>Layanan Sementara Ditangguhkan</h1>
        <p>
            Sistem <span class="tenant-name">{{ $tenantName }}</span> sedang tidak dapat diakses.
        </p>
        <p>
            Hal ini mungkin disebabkan oleh pembaruan sistem atau pembayaran langganan yang tertunda.
        </p>
        <div class="contact">
            <strong>Hubungi Administrator Platform</strong>
            Untuk mengaktifkan kembali layanan, silakan hubungi pengelola platform Anda.
        </div>
    </div>
</body>
</html>
