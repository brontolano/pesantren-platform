<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>Syahadah — {{ $alumni->santri?->nama }}</title>
    <style>
        body { font-family: 'DejaVu Sans', Georgia, serif; font-size: 14px; color: #1F2A24; margin: 48px; max-width: 760px; }
        h1 { font-size: 18px; text-align: center; margin: 0 0 4px; letter-spacing: 2px; }
        .kop { text-align: center; border-bottom: 3px double #1F2A24; padding-bottom: 8px; margin-bottom: 24px; }
        .isi { white-space: pre-wrap; line-height: 1.8; }
        @media print { .noprint { display: none; } }
    </style>
</head>
<body>
    <div class="kop">
        <h1>{{ $tenantName }}</h1>
        <div style="color:#666">Syahadah / Sertifikat Kelulusan {{ $alumni->lembaga?->nama }}</div>
    </div>
    <div class="isi">{{ $isi }}</div>
    <p class="noprint" style="margin-top:24px;"><button onclick="window.print()">Cetak</button></p>
</body>
</html>
