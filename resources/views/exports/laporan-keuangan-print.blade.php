<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>{{ $title }} — {{ $tenantName }}</title>
    <style>
        body { font-family: Arial, sans-serif; color: #0f172a; margin: 24px; }
        h1, h2, p { margin: 0; }
        .header { margin-bottom: 20px; }
        .meta { margin-top: 4px; color: #475569; font-size: 12px; }
        .summary-box { display: flex; gap: 24px; margin-top: 12px; font-size: 13px; font-weight: bold; }
        .summary-box span { padding: 6px 10px; background: #f1f5f9; border-radius: 4px; }
        table { width: 100%; border-collapse: collapse; margin-top: 16px; font-size: 11px; }
        th, td { border: 1px solid #cbd5e1; padding: 6px 8px; text-align: left; vertical-align: top; }
        th { background: #e2e8f0; }
        td.num { text-align: right; font-variant-numeric: tabular-nums; }
        .status-lunas { color: #16a34a; }
        .status-sebagian { color: #d97706; }
        .status-belum { color: #dc2626; }
        @media print { body { margin: 12mm; } .print-note { display: none; } }
    </style>
</head>
<body>
    <div class="header">
        <h1>{{ $tenantName }}</h1>
        <h2>{{ $title }}</h2>
        @if($filters['status'] || $filters['jenis'] || $filters['periode'] || $filters['dari'] || $filters['sampai'])
            <p class="meta">
                Filter:
                @if($filters['status']) Status={{ ucfirst($filters['status']) }} @endif
                @if($filters['jenis']) Jenis={{ $filters['jenis'] }} @endif
                @if($filters['periode']) Periode={{ $filters['periode'] }} @endif
                @if($filters['dari']) Dari={{ $filters['dari'] }} @endif
                @if($filters['sampai']) Sampai={{ $filters['sampai'] }} @endif
            </p>
        @endif
        <p class="meta">Dicetak: {{ $printedAt->format('d M Y H:i') }}</p>
        <div class="summary-box">
            <span>Total Tagihan: Rp {{ number_format($summary['total_tagihan'], 0, ',', '.') }}</span>
            <span>Terkumpul: Rp {{ number_format($summary['total_dibayar'], 0, ',', '.') }}</span>
            <span>Piutang: Rp {{ number_format($summary['total_piutang'], 0, ',', '.') }}</span>
        </div>
    </div>

    <p class="print-note">Gunakan menu print browser lalu pilih "Save as PDF" untuk menyimpan sebagai PDF.</p>

    <table>
        <thead>
            <tr>
                <th>No</th>
                <th>NIS</th>
                <th>Nama</th>
                <th>Jenis</th>
                <th>Nominal</th>
                <th>Diskon</th>
                <th>Total</th>
                <th>Dibayar</th>
                <th>Sisa</th>
                <th>Status</th>
                <th>Jatuh Tempo</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($tagihan as $i => $item)
                <tr>
                    <td>{{ $i + 1 }}</td>
                    <td>{{ optional($item->santri)->nis }}</td>
                    <td>{{ optional($item->santri)->nama }}</td>
                    <td>{{ $item->jenis_tagihan }}</td>
                    <td class="num">{{ number_format($item->nominal, 0, ',', '.') }}</td>
                    <td class="num">{{ number_format($item->diskon, 0, ',', '.') }}</td>
                    <td class="num">{{ number_format($item->nominal_akhir, 0, ',', '.') }}</td>
                    <td class="num">{{ number_format($item->total_dibayar, 0, ',', '.') }}</td>
                    <td class="num">{{ number_format($item->sisa_tagihan, 0, ',', '.') }}</td>
                    <td class="status-{{ $item->status === 'lunas' ? 'lunas' : ($item->status === 'sebagian' ? 'sebagian' : 'belum') }}">
                        {{ ucfirst(str_replace('_', ' ', $item->status)) }}
                    </td>
                    <td>{{ optional($item->jatuh_tempo)?->format('d-m-Y') }}</td>
                </tr>
            @empty
                <tr><td colspan="11">Tidak ada data tagihan pada filter ini.</td></tr>
            @endforelse
        </tbody>
    </table>

    <script>window.addEventListener('load', function () { window.print(); });</script>
</body>
</html>
