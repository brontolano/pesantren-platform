<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Daftar Peserta Wisuda Tahfidz — {{ $periode->nama }}</title>
    <style>
        body { font-family: Arial, sans-serif; color: #111827; margin: 24px; }
        h1, h2 { margin: 0; }
        .meta { margin-top: 16px; }
        table { width: 100%; border-collapse: collapse; margin-top: 16px; }
        th, td { border: 1px solid #d1d5db; padding: 8px; text-align: left; font-size: 12px; }
        th { background: #f3f4f6; }
        .print-note { margin-top: 20px; font-size: 12px; color: #6b7280; }
    </style>
</head>
<body onload="window.print()">
    <h1>{{ $tenantName }}</h1>
    <h2>Daftar Peserta Wisuda Tahfidz</h2>
    <div class="meta">
        <p><strong>Periode:</strong> {{ $periode->nama }} ({{ $periode->tahun_akademik ?? '-' }})</p>
        <p><strong>Jumlah Peserta:</strong> {{ $peserta->count() }}</p>
    </div>

    <table>
        <thead>
            <tr>
                <th>No</th>
                <th>NIS</th>
                <th>Nama</th>
                <th>Kelas</th>
                <th>Juz Diuji</th>
                <th>Nilai</th>
                <th>Tanggal Munaqasyah</th>
                <th>Penguji</th>
            </tr>
        </thead>
        <tbody>
        @forelse($peserta as $i => $row)
            <tr>
                <td>{{ $i + 1 }}</td>
                <td>{{ $row->santri->nis ?? '-' }}</td>
                <td>{{ $row->santri->nama ?? '-' }}</td>
                <td>{{ $row->santri->kelas->nama_kelas ?? '-' }}</td>
                <td>{{ $row->juz_diuji }}</td>
                <td>{{ $row->nilai !== null ? number_format((float) $row->nilai, 2) : '-' }}</td>
                <td>{{ $row->tanggal?->format('d M Y') ?? '-' }}</td>
                <td>{{ $row->penguji->name ?? '-' }}</td>
            </tr>
        @empty
            <tr><td colspan="8">Belum ada peserta wisuda (lulus munaqasyah + ditandai peserta) pada periode ini.</td></tr>
        @endforelse
        </tbody>
    </table>

    <p class="print-note">Dicetak {{ $printedAt->format('d M Y H:i') }}. Gunakan menu print browser lalu pilih "Save as PDF" untuk menyimpan sebagai PDF.</p>
</body>
</html>
