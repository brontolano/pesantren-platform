<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>Paket Kelayakan Kredit — {{ $pengajuan->bank_tujuan }}</title>
    <style>
        body { font-family: 'DejaVu Sans', Arial, sans-serif; font-size: 12px; color: #1F2A24; margin: 24px; }
        h1 { font-size: 18px; margin: 0; } h3 { margin-bottom: 4px; }
        table { width: 100%; border-collapse: collapse; margin-top: 8px; }
        th, td { border: 1px solid #ccc; padding: 6px 8px; }
        td.num { text-align: right; }
        .zona-aman { color: #0A5C36; } .zona-waspada { color: #B8860B; } .zona-bahaya { color: #B3261E; }
        @media print { .noprint { display:none; } }
    </style>
</head>
<body>
    <h1>{{ $tenantName }}</h1>
    <div style="color:#666">L3 — Paket Kelayakan Kredit Bank Syariah</div>

    <h3 style="margin-top:16px;">Ringkasan DSCR</h3>
    <table>
        <tr><th style="width:40%">Bank / Produk</th><td>{{ $pengajuan->bank_tujuan }} — {{ $pengajuan->produk }}</td></tr>
        <tr><th>Plafon / Tenor</th><td>Rp {{ number_format((float) $pengajuan->plafon, 0) }} / {{ $pengajuan->tenor }} bln</td></tr>
        <tr><th>EBITDA Tahunan</th><td>Rp {{ number_format((float) $pengajuan->ebitda_tahunan, 0) }}</td></tr>
        <tr><th>DSCR</th><td class="zona-{{ $pengajuan->zona }}"><strong>{{ number_format((float) $pengajuan->dscr, 2) }}× — {{ strtoupper($pengajuan->zona ?? '-') }}</strong> (ambang 1,25×)</td></tr>
        <tr><th>Agunan (non-wakaf)</th><td>{{ $pengajuan->agunan?->nama_aset ?? '—' }} — Rp {{ number_format((float) ($pengajuan->agunan?->nilai ?? 0), 0) }}</td></tr>
        <tr><th>Persetujuan Pembina</th><td>{{ $pengajuan->status === 'disetujui' ? 'DISETUJUI (TTE: '.$pengajuan->esignature_pembina.')' : 'Belum disetujui' }}</td></tr>
    </table>

    <h3 style="margin-top:16px;">Ringkasan Keuangan (L6)</h3>
    <table>
        <tr><th style="width:40%">Total Aset</th><td class="num">Rp {{ number_format($neraca['total_aset'], 0) }}</td></tr>
        <tr><th>Total Pendapatan</th><td class="num">Rp {{ number_format($labaRugi['total_pendapatan'], 0) }}</td></tr>
        <tr><th>Total Beban</th><td class="num">Rp {{ number_format($labaRugi['total_beban'], 0) }}</td></tr>
        <tr><th>Surplus / (Defisit)</th><td class="num">Rp {{ number_format($labaRugi['surplus_defisit'], 0) }}</td></tr>
    </table>
    <p style="margin-top:12px; font-size:11px; color:#666;">Aset wakaf tidak pernah dijadikan agunan (UU 41/2004 Ps 40). Final hanya setelah persetujuan Pembina (UU Yayasan Ps 37).</p>
    <p class="noprint"><button onclick="window.print()">Cetak</button></p>
</body>
</html>
