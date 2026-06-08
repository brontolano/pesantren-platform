<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>Slip BKK — {{ $belanja->uraian }}</title>
    <style>
        body { font-family: 'DejaVu Sans', Arial, sans-serif; font-size: 12px; color: #1F2A24; margin: 24px; }
        h1 { font-size: 18px; margin: 0; }
        .muted { color: #666; }
        table { width: 100%; border-collapse: collapse; margin-top: 12px; }
        th, td { border: 1px solid #ccc; padding: 6px 8px; text-align: left; }
        td.num, th.num { text-align: right; }
        .totals td { font-weight: bold; }
        .sign { margin-top: 48px; display: flex; justify-content: space-between; }
        @media print { .noprint { display: none; } }
    </style>
</head>
<body>
    <div style="display:flex; justify-content:space-between; align-items:flex-start;">
        <div>
            <h1>{{ $tenantName }}</h1>
            <div class="muted">Bukti Kas Keluar (BKK) &middot; e-Faktur Sederhana</div>
        </div>
        <div class="muted">No. Belanja: #{{ $belanja->id }}<br>{{ \Illuminate\Support\Carbon::parse($belanja->tanggal)->format('d M Y') }}</div>
    </div>

    <table>
        <tr><th style="width:35%">Uraian</th><td>{{ $belanja->uraian }}</td></tr>
        <tr><th>Jenis</th><td>{{ ucfirst($belanja->jenis) }}</td></tr>
        <tr><th>Kategori RKAM</th><td>{{ $belanja->kategori_rkam ?? '—' }}</td></tr>
        <tr><th>Vendor ber-NPWP</th><td>{{ $belanja->vendor_npwp ? 'Ya' : 'Tidak' }}</td></tr>
        <tr><th>Status</th><td>{{ ucfirst($belanja->status_approval) }}</td></tr>
    </table>

    <table>
        <thead>
            <tr><th>Komponen</th><th class="num">Nominal (Rp)</th></tr>
        </thead>
        <tbody>
            <tr><td>DPP (Dasar Pengenaan Pajak)</td><td class="num">{{ number_format((float) $belanja->dpp, 2) }}</td></tr>
            <tr><td>PPN (11%)</td><td class="num">{{ number_format((float) $belanja->ppn_dipungut, 2) }}</td></tr>
            <tr><td>PPh dipotong ({{ $belanja->pph_jenis ?? '—' }})</td><td class="num">{{ number_format((float) $belanja->pph_dipungut, 2) }}</td></tr>
            <tr class="totals"><td>Total Dibukukan (DPP + PPN)</td><td class="num">{{ number_format((float) $belanja->total, 2) }}</td></tr>
            <tr class="totals"><td>Dibayar ke Vendor (DPP − PPh)</td><td class="num">{{ number_format((float) $belanja->dpp - (float) $belanja->pph_dipungut, 2) }}</td></tr>
        </tbody>
    </table>

    <div class="sign">
        <div>Pembuat,<br><br><br>(____________)</div>
        <div>Menyetujui,<br><br><br>(____________)</div>
    </div>

    <p class="noprint muted" style="margin-top:24px;">
        <button onclick="window.print()">Cetak</button>
        — atau gunakan Print → Save as PDF.
    </p>
</body>
</html>
