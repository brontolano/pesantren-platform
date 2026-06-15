<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Kebijakan Privasi Sistem — {{ $nama }}</title>
    <style>
        body { font-family: system-ui, -apple-system, sans-serif; background: #f8faf9; color: #1c1917; margin: 0; }
        main { max-width: 760px; margin: 0 auto; padding: 48px 20px 80px; }
        h1 { font-size: 1.5rem; color: #064e3b; }
        .kartu { background: #fff; border: 1px solid #e7e5e4; border-radius: 16px; padding: 28px; margin-top: 20px; }
        pre { white-space: pre-wrap; font-family: inherit; font-size: .95rem; line-height: 1.7; margin: 0; }
        .tautan { margin-top: 18px; font-size: .85rem; }
        .tautan a { color: #047857; }
        footer { margin-top: 28px; font-size: .8rem; color: #78716c; }
    </style>
</head>
<body>
    <main>
        <h1>Kebijakan Privasi Sistem<br><span style="font-size:1rem;color:#57534e">{{ $nama }}</span></h1>
        <div class="kartu"><pre>{{ $isi }}</pre></div>
        <div class="tautan"><a href="/hapus-akun">Penghapusan Akun &amp; Data &rarr;</a></div>
        <footer>Sistem dikelola dengan platform pesantren.online · Hubungi kantor pesantren untuk pertanyaan data.</footer>
    </main>
</body>
</html>
