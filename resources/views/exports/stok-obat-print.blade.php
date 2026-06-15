<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>Stok Obat UKS</title>
    <style>
        body { font-family: Arial, sans-serif; color: #0f172a; margin: 24px; }
        h1, p { margin: 0; }
        .meta { margin-top: 8px; color: #475569; font-size: 12px; }
        table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        th, td { border: 1px solid #cbd5e1; padding: 6px 8px; font-size: 12px; text-align: left; }
        th { background: #f1f5f9; }
        td.num { text-align: right; }
        tr.menipis td { background: #fef2f2; }
        .no-print { margin-top: 16px; }
        @media print { .no-print { display: none; } body { margin: 0; } }
    </style>
</head>
<body>
    <div class="header">
        <h1>Stok Obat UKS</h1>
        <p class="meta">{{ $tenantName }} · Dicetak {{ $printedAt->format('d/m/Y H:i') }}</p>
    </div>

    <table>
        <thead>
            <tr><th>Nama Obat</th><th>Satuan</th><th>Stok</th><th>Ambang Menipis</th><th>Status</th></tr>
        </thead>
        <tbody>
            @forelse ($obat as $o)
                <tr class="{{ ((int) $o->stok <= (int) $o->ambang_menipis) ? 'menipis' : '' }}">
                    <td>{{ $o->nama }}</td>
                    <td>{{ $o->satuan }}</td>
                    <td class="num">{{ $o->stok }}</td>
                    <td class="num">{{ $o->ambang_menipis }}</td>
                    <td>{{ ((int) $o->stok <= (int) $o->ambang_menipis) ? 'Menipis' : 'Aman' }}</td>
                </tr>
            @empty
                <tr><td colspan="5" style="text-align:center;color:#64748b;">Belum ada data obat.</td></tr>
            @endforelse
        </tbody>
    </table>

    <button class="no-print" onclick="window.print()">Cetak</button>
</body>
</html>
