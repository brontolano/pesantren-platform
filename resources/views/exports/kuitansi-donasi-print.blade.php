<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Kuitansi {{ $donasi->nomor_kuitansi }}</title>
    <style>
        body { font-family: system-ui, -apple-system, sans-serif; color: #1c1917; margin: 0; padding: 32px; }
        .kuitansi { max-width: 640px; margin: 0 auto; border: 2px solid #064e3b; border-radius: 12px; padding: 28px; }
        h1 { font-size: 1.1rem; color: #064e3b; margin: 0; }
        .nomor { font-size: .9rem; color: #57534e; margin-top: 2px; }
        table { width: 100%; margin-top: 18px; border-collapse: collapse; font-size: .95rem; }
        td { padding: 6px 0; vertical-align: top; }
        td:first-child { width: 38%; color: #57534e; }
        .nominal { font-size: 1.2rem; font-weight: 700; color: #064e3b; }
        .ttd { margin-top: 36px; font-size: .9rem; text-align: right; }
        @media print { body { padding: 0; } }
    </style>
</head>
<body onload="window.print()">
<div class="kuitansi">
    <h1>KUITANSI DONASI — {{ $namaPesantren }}</h1>
    <div class="nomor">Nomor: {{ $donasi->nomor_kuitansi }}</div>
    <table>
        <tr><td>Telah diterima dari</td><td><strong>{{ $donasi->nama_donatur }}</strong></td></tr>
        <tr><td>Program</td><td>{{ $donasi->program?->nama }}</td></tr>
        <tr><td>Sumber dana</td><td>{{ $donasi->program?->sumberDana?->nama_sumber }}</td></tr>
        <tr><td>Jumlah</td><td class="nominal">Rp {{ number_format((float) $donasi->nominal, 0, ',', '.') }}</td></tr>
        <tr><td>Tanggal konfirmasi</td><td>{{ $donasi->dikonfirmasi_at?->translatedFormat('d F Y H:i') }}</td></tr>
        @if ($donasi->pesan)<tr><td>Pesan</td><td>{{ $donasi->pesan }}</td></tr>@endif
    </table>
    <div class="ttd">
        Bendahara,<br><br><br>
        <strong>{{ $donasi->konfirmator?->name ?? '________________' }}</strong>
    </div>
</div>
</body>
</html>
