<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>Daftar Nilai {{ $kelas->nama_kelas }}</title>
    <style>
        body { font-family: Arial, sans-serif; color: #0f172a; margin: 24px; }
        h1, h2, p { margin: 0; }
        .meta { margin-top: 8px; color: #475569; font-size: 12px; }
        table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        th, td { border: 1px solid #cbd5e1; padding: 6px 8px; font-size: 12px; text-align: left; }
        th { background: #f1f5f9; }
        td.num { text-align: right; }
        .no-print { margin-top: 16px; }
        @media print { .no-print { display: none; } body { margin: 0; } }
    </style>
</head>
<body>
    <div class="header">
        <h1>Daftar Nilai — {{ $kelas->nama_kelas }}</h1>
        <p class="meta">{{ $tenantName }} · Periode {{ $periode?->nama ?? 'semua' }} · Dicetak {{ $printedAt->format('d/m/Y H:i') }}</p>
    </div>

    <table>
        <thead>
            <tr><th>Santri (NIS)</th><th>Pelajaran</th><th>Harian</th><th>UTS</th><th>UAS</th><th>Akhir</th></tr>
        </thead>
        <tbody>
            @forelse ($nilai as $n)
                <tr>
                    <td>{{ $n->santri?->nama ?? '-' }} ({{ $n->santri?->nis ?? '-' }})</td>
                    <td>{{ $n->pelajaran?->nama ?? '-' }}</td>
                    <td class="num">{{ $n->nilai_harian ?? '-' }}</td>
                    <td class="num">{{ $n->nilai_uts ?? '-' }}</td>
                    <td class="num">{{ $n->nilai_uas ?? '-' }}</td>
                    <td class="num">{{ $n->nilai_akhir ?? '-' }}</td>
                </tr>
            @empty
                <tr><td colspan="6" style="text-align:center;color:#64748b;">Belum ada nilai.</td></tr>
            @endforelse
        </tbody>
    </table>

    <button class="no-print" onclick="window.print()">Cetak</button>
</body>
</html>
