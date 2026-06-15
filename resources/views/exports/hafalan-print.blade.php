<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>Progres Hafalan</title>
    <style>
        body { font-family: Arial, sans-serif; color: #0f172a; margin: 24px; }
        h1, h2, p { margin: 0; }
        .meta { margin-top: 8px; color: #475569; font-size: 12px; }
        h2 { margin-top: 20px; font-size: 14px; }
        table { width: 100%; border-collapse: collapse; margin-top: 8px; }
        th, td { border: 1px solid #cbd5e1; padding: 6px 8px; font-size: 12px; text-align: left; }
        th { background: #f1f5f9; }
        .no-print { margin-top: 16px; }
        @media print { .no-print { display: none; } body { margin: 0; } }
    </style>
</head>
<body>
    <div class="header">
        <h1>Progres Hafalan</h1>
        <p class="meta">{{ $tenantName }} · Dicetak {{ $printedAt->format('d/m/Y H:i') }}</p>
    </div>
    @forelse ($perHalaqah as $namaHalaqah => $rows)
        <h2>{{ $namaHalaqah }}</h2>
        <table>
            <thead><tr><th>NIS</th><th>Santri</th><th>Surah</th><th>Ayat</th><th>Status</th></tr></thead>
            <tbody>
                @foreach ($rows as $h)
                    <tr>
                        <td>{{ $h->santri?->nis ?? '-' }}</td>
                        <td>{{ $h->santri?->nama ?? '-' }}</td>
                        <td>{{ $h->surah ?? '-' }}</td>
                        <td>{{ $h->ayat_awal }}–{{ $h->ayat_akhir }}</td>
                        <td style="text-transform: capitalize;">{{ $h->status }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @empty
        <p style="margin-top:20px;color:#64748b;">Belum ada data hafalan.</p>
    @endforelse
    <button class="no-print" onclick="window.print()">Cetak</button>
</body>
</html>
