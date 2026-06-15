<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>Daftar Pegawai</title>
    <style>
        body { font-family: Arial, sans-serif; color: #0f172a; margin: 24px; }
        h1, p { margin: 0; }
        .meta { margin-top: 8px; color: #475569; font-size: 12px; }
        table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        th, td { border: 1px solid #cbd5e1; padding: 6px 8px; font-size: 12px; text-align: left; }
        th { background: #f1f5f9; }
        .no-print { margin-top: 16px; }
        @media print { .no-print { display: none; } body { margin: 0; } }
    </style>
</head>
<body>
    <div class="header">
        <h1>Daftar Pegawai</h1>
        <p class="meta">{{ $tenantName }} · Dicetak {{ $printedAt->format('d/m/Y H:i') }}</p>
    </div>
    <table>
        <thead><tr><th>NIP</th><th>Nama</th><th>Jabatan</th><th>Status</th></tr></thead>
        <tbody>
            @forelse ($pegawai as $p)
                <tr>
                    <td>{{ $p->nip ?? '-' }}</td>
                    <td>{{ $p->nama }}</td>
                    <td>{{ $p->jabatan ?? '-' }}</td>
                    <td style="text-transform: capitalize;">{{ $p->status_kepegawaian ?? '-' }}</td>
                </tr>
            @empty
                <tr><td colspan="4" style="text-align:center;color:#64748b;">Belum ada pegawai.</td></tr>
            @endforelse
        </tbody>
    </table>
    <button class="no-print" onclick="window.print()">Cetak</button>
</body>
</html>
