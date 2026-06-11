<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>SK Penugasan — {{ $penugasan->employee?->nama }}</title>
    <style>
        body { font-family: 'DejaVu Sans', Arial, sans-serif; font-size: 13px; color: #1F2A24; margin: 32px; max-width: 720px; }
        h1 { font-size: 16px; text-align: center; margin: 0 0 4px; }
        .kop { text-align: center; border-bottom: 3px double #1F2A24; padding-bottom: 8px; margin-bottom: 18px; }
        .isi { white-space: pre-wrap; line-height: 1.6; }
        @media print { .noprint { display: none; } }
    </style>
</head>
<body>
    <div class="kop">
        <h1>{{ $tenantName }}</h1>
        <div style="color:#666">Surat Keputusan Penugasan</div>
    </div>
    <div class="isi">{{ $isi }}</div>
    <p class="noprint" style="margin-top:24px;"><button onclick="window.print()">Cetak</button></p>
</body>
</html>
