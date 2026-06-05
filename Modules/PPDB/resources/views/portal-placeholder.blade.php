{{-- Placeholder dashboard peserta (F1.1.6) — DIGANTI halaman Inertia+Vue di F1.2.1/F1.2.4. --}}
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Portal SPMB</title>
    <style>
        body { font-family: system-ui, sans-serif; display: flex; min-height: 100vh; margin: 0;
               align-items: center; justify-content: center; background: #f8fafc; color: #0f172a; }
        .kartu { text-align: center; padding: 2rem; max-width: 22rem; }
    </style>
</head>
<body>
    <div class="kartu">
        <h1 style="font-size:1.25rem">Assalamu'alaikum, {{ auth()->user()?->name }}</h1>
        <p>Akun Anda sudah aktif. Halaman status pendaftaran sedang disiapkan — silakan kembali lagi nanti.</p>
        <form method="POST" action="{{ route('spmb.logout') }}">
            @csrf
            <button type="submit" style="padding:.5rem 1rem;cursor:pointer">Keluar</button>
        </form>
    </div>
</body>
</html>
