<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Cetak QR Area Piket</title>
    <style>
        @page { margin: 12mm; }
        * { box-sizing: border-box; }
        body { font-family: -apple-system, "Segoe UI", Roboto, Arial, sans-serif; margin: 0; color: #111; }
        .head { text-align: center; margin: 0 0 8mm; }
        .head h2 { font-size: 15pt; margin: 0 0 2mm; }
        .head p { font-size: 10pt; color: #555; margin: 0; }
        .grid { display: flex; flex-wrap: wrap; gap: 10mm; justify-content: flex-start; }
        .card { width: 62mm; text-align: center; border: 1px dashed #999; border-radius: 8px; padding: 8mm 4mm; page-break-inside: avoid; }
        .card img { width: 46mm; height: 46mm; }
        .card .nama { font-weight: 700; font-size: 13pt; margin: 4mm 0 1mm; }
        .card .kode { font-family: monospace; font-size: 11pt; color: #444; word-break: break-all; }
        .noprint { margin-top: 4mm; }
        .noprint button { font-size: 11pt; padding: 6px 18px; cursor: pointer; }
        @media print { .noprint { display: none; } }
    </style>
</head>
<body onload="window.print()">
    <div class="head">
        <h2>QR Area Piket</h2>
        <p>{{ $namaPesantren }}</p>
        <div class="noprint"><button type="button" onclick="window.print()">Cetak / Print</button></div>
    </div>

    <div class="grid">
        @foreach ($areas as $area)
            <div class="card">
                <img src="{{ $area['qr'] }}" alt="QR {{ $area['kode'] }}">
                <div class="nama">{{ $area['nama'] }}</div>
                <div class="kode">{{ $area['kode'] }}</div>
            </div>
        @endforeach
    </div>
</body>
</html>
