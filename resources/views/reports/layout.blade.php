{{-- F8 — kerangka laporan dengan kop brand tenant. --}}
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>{{ $judul ?? 'Laporan' }}</title>
    <style>
        * { font-family: DejaVu Sans, sans-serif; }
        body { color: #1f2937; font-size: 12px; margin: 24px; }
        .kop { border-bottom: 3px solid {{ $brand['warna'] ?? '#0f766e' }}; padding-bottom: 12px; margin-bottom: 18px; display: flex; align-items: center; }
        .kop img { height: 56px; margin-right: 16px; }
        .kop .nama { font-size: 20px; font-weight: 700; color: {{ $brand['warna'] ?? '#0f766e' }}; }
        .kop .tagline { font-size: 11px; color: #6b7280; }
        .judul { font-size: 15px; font-weight: 700; text-align: center; margin: 8px 0 16px; }
        table { width: 100%; border-collapse: collapse; margin-top: 8px; }
        th, td { border: 1px solid #d1d5db; padding: 6px 8px; text-align: left; }
        th { background: #f3f4f6; }
        .ttd { margin-top: 48px; text-align: right; }
        .footer { margin-top: 24px; font-size: 10px; color: #9ca3af; text-align: center; }
    </style>
</head>
<body>
    <div class="kop">
        @if(!empty($brand['logo_url']))
            <img src="{{ $brand['logo_url'] }}" alt="logo">
        @endif
        <div>
            <div class="nama">{{ $brand['nama'] ?? 'Pesantren' }}</div>
            @if(!empty($brand['tagline']))
                <div class="tagline">{{ $brand['tagline'] }}</div>
            @endif
        </div>
    </div>

    @yield('isi')

    <div class="footer">Dicetak melalui Sistem Informasi Pesantren — {{ $brand['nama'] ?? '' }}</div>
</body>
</html>
