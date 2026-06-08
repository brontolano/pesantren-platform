<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>Slip Gaji — {{ $slip->periode }}</title>
    <style>
        body { font-family: 'DejaVu Sans', Arial, sans-serif; font-size: 12px; color: #1F2A24; margin: 24px; }
        h1 { font-size: 18px; margin: 0; }
        table { width: 100%; border-collapse: collapse; margin-top: 12px; }
        th, td { border: 1px solid #ccc; padding: 6px 8px; }
        td.num { text-align: right; }
        .total td { font-weight: bold; }
        @media print { .noprint { display: none; } }
    </style>
</head>
<body>
    <h1>{{ $tenantName }}</h1>
    <div style="color:#666">Slip Gaji / Honor — Periode {{ $slip->periode }}</div>
    <table>
        <tr><th style="width:40%">Nama PTK</th><td>{{ $slip->ptk?->name }}</td></tr>
    </table>
    <table>
        <tr><th>Komponen</th><th class="num">Nominal (Rp)</th></tr>
        <tr><td>Honor Pokok</td><td class="num">{{ number_format((float) $slip->honor_pokok, 2) }}</td></tr>
        <tr><td>Tunjangan</td><td class="num">{{ number_format((float) $slip->tunjangan, 2) }}</td></tr>
        <tr><td>Honor Poin ({{ $slip->poin }} × {{ number_format((float) $slip->tarif_poin, 0) }})</td><td class="num">{{ number_format((float) $slip->honor_poin, 2) }}</td></tr>
        <tr><td>Honor Output (kegiatan)</td><td class="num">{{ number_format((float) $slip->honor_output, 2) }}</td></tr>
        <tr class="total"><td>Bruto</td><td class="num">{{ number_format((float) $slip->bruto, 2) }}</td></tr>
        <tr><td>PPh 21 dipotong</td><td class="num">({{ number_format((float) $slip->pph21_dipotong, 2) }})</td></tr>
        <tr class="total"><td>Diterima (Neto)</td><td class="num">{{ number_format((float) $slip->neto, 2) }}</td></tr>
    </table>
    <p class="noprint" style="margin-top:24px;"><button onclick="window.print()">Cetak</button></p>
</body>
</html>
