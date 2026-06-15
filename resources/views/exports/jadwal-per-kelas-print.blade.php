<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>Jadwal Kelas {{ $kelas->nama_kelas }}</title>
    <style>
        body { font-family: Arial, sans-serif; color: #0f172a; margin: 24px; }
        h1, h2, p { margin: 0; }
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
        <h1>Jadwal Pelajaran — {{ $kelas->nama_kelas }}</h1>
        <p class="meta">{{ $tenantName }} · Dicetak {{ $printedAt->format('d/m/Y H:i') }}</p>
    </div>

    <table>
        <thead>
            <tr><th>Hari</th><th>Jam</th><th>Pelajaran</th><th>Periode</th><th>Ruangan</th></tr>
        </thead>
        <tbody>
            @forelse ($jadwal as $j)
                <tr>
                    <td style="text-transform: capitalize;">{{ $j->hari }}</td>
                    <td>{{ $j->jam_mulai }}–{{ $j->jam_selesai }}</td>
                    <td>{{ $j->pelajaran?->nama ?? '-' }}</td>
                    <td>{{ $j->periodeAkademik?->tahun_akademik ?? '-' }}</td>
                    <td>{{ $j->ruangan ?? '-' }}</td>
                </tr>
            @empty
                <tr><td colspan="5" style="text-align:center;color:#64748b;">Belum ada jadwal.</td></tr>
            @endforelse
        </tbody>
    </table>

    <button class="no-print" onclick="window.print()">Cetak</button>
</body>
</html>
