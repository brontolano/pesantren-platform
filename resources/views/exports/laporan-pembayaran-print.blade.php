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
        .verified { color: #16a34a; }
        .unverified { color: #d97706; }
        @media print { body { margin: 12mm; } .print-note { display: none; } }
    </style>
</head>
<body>
    <div class="header">
        <h1>{{ $tenantName }}</h1>
        <h2>{{ $title }}</h2>
        @if($filters['dari'] || $filters['sampai'] || $filters['metode'] || $filters['verified'])
            <p class="meta">
                Filter:
                @if($filters['dari']) Dari={{ $filters['dari'] }} @endif
                @if($filters['sampai']) Sampai={{ $filters['sampai'] }} @endif
                @if($filters['metode']) Metode={{ $filters['metode'] }} @endif
                @if($filters['verified'] !== '') Verified={{ $filters['verified'] === '1' ? 'Ya' : 'Belum' }} @endif
            </p>
        @endif
        <p class="meta">Dicetak: {{ $printedAt->format('d M Y H:i') }}</p>
        <div class="summary-box">
            <span>Total: {{ $pembayaran->count() }} transaksi</span>
            <span>Jumlah: Rp {{ number_format($totalNominal, 0, ',', '.') }}</span>
        </div>
    </div>

    <p class="print-note">Gunakan menu print browser lalu pilih "Save as PDF" untuk menyimpan sebagai PDF.</p>

    <table>
        <thead>
            <tr>
                <th>No</th>
                <th>Tanggal</th>
                <th>NIS</th>
                <th>Nama</th>
                <th>Jenis Tagihan</th>
                <th>Nominal</th>
                <th>Metode</th>
                <th>No. Bukti</th>
                <th>Status</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($pembayaran as $i => $item)
                <tr>
                    <td>{{ $i + 1 }}</td>
                    <td>{{ optional($item->tanggal_pembayaran)?->format('d-m-Y') }}</td>
                    <td>{{ optional($item->santri)->nis }}</td>
                    <td>{{ optional($item->santri)->nama }}</td>
                    <td>{{ optional($item->tagihan)->jenis_tagihan }}</td>
                    <td class="num">{{ number_format($item->nominal, 0, ',', '.') }}</td>
                    <td>{{ $item->metode }}</td>
                    <td>{{ $item->nomor_bukti ?: '-' }}</td>
                    <td class="{{ $item->is_verified ? 'verified' : 'unverified' }}">
                        {{ $item->is_verified ? 'Verified' : 'Belum' }}
                    </td>
                </tr>
            @empty
                <tr><td colspan="9">Tidak ada data pembayaran pada filter ini.</td></tr>
            @endforelse
        </tbody>
    </table>

    <script>window.addEventListener('load', function () { window.print(); });</script>
</body>
</html>
