<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Penghapusan Akun &amp; Data — {{ $nama }}</title>
    <meta name="robots" content="noindex, follow">
    <style>
        body { font-family: system-ui, -apple-system, sans-serif; background: #fffaf3; color: #1c1917; margin: 0; }
        body.tenant-albakids { background: #f8faf9; } /* AlbaKids: pertahankan netral asli */
        main { max-width: 760px; margin: 0 auto; padding: 48px 20px 80px; }
        h1 { font-size: 1.5rem; color: #064e3b; }
        .kartu { background: #fff; border: 1px solid #e7e5e4; border-radius: 16px; padding: 28px; margin-top: 20px; }
        pre { white-space: pre-wrap; font-family: inherit; font-size: .95rem; line-height: 1.7; margin: 0; }
        .kontak { background: #ecfdf5; border: 1px solid #a7f3d0; border-radius: 16px; padding: 20px 24px; margin-top: 20px; }
        .kontak h2 { font-size: 1.05rem; color: #065f46; margin: 0 0 12px; }
        .kontak ul { margin: 0; padding-left: 18px; }
        .kontak li { margin-bottom: 6px; font-size: .92rem; }
        .kontak a { color: #047857; }
        .kontak p { margin: 0; font-size: .92rem; }
        .tautan { margin-top: 18px; font-size: .85rem; }
        .tautan a { color: #047857; }
        footer { margin-top: 28px; font-size: .8rem; color: #78716c; }
    </style>
</head>
<body class="{{ \App\Support\TenantCustomizationRegistry::bodyClass() }}">
    <main>
        <h1>Penghapusan Akun &amp; Data<br><span style="font-size:1rem;color:#57534e">{{ $nama }}</span></h1>

        <div class="kartu"><pre>{{ $isi }}</pre></div>

        <div class="kontak">
            <h2>Kanal Permintaan</h2>
            @if($kontak['whatsapp'] || $kontak['email'] || $kontak['telepon'] || $kontak['alamat'])
                <ul>
                    @if($kontak['whatsapp'])
                        <li>WhatsApp admin:
                            <a href="{{ $kontak['whatsapp_link'] }}" target="_blank" rel="noopener">{{ $kontak['whatsapp'] }}</a>
                        </li>
                    @endif
                    @if($kontak['email'])
                        <li>Email: <a href="mailto:{{ $kontak['email'] }}">{{ $kontak['email'] }}</a></li>
                    @endif
                    @if($kontak['telepon'])
                        <li>Telepon: {{ $kontak['telepon'] }}</li>
                    @endif
                    @if($kontak['alamat'])
                        <li>Kantor: {{ $kontak['alamat'] }}</li>
                    @endif
                </ul>
            @else
                <p>Silakan hubungi kantor atau admin pesantren {{ $nama }} secara langsung untuk mengajukan penghapusan akun dan data Anda.</p>
            @endif
        </div>

        <div class="tautan"><a href="/kebijakan-privasi">&larr; Kebijakan Privasi</a></div>

        <footer>Sistem dikelola dengan platform pesantren.online · Setiap permintaan penghapusan diverifikasi sebelum diproses.</footer>
    </main>
</body>
</html>
