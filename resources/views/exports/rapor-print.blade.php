<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Rapor {{ $rapor->santri->nama }}</title>
    <style>
        body { font-family: Arial, sans-serif; color: #111827; margin: 24px; }
        h1, h2, h3 { margin: 0; }
        .meta, .summary { margin-top: 16px; }
        table { width: 100%; border-collapse: collapse; margin-top: 16px; }
        th, td { border: 1px solid #d1d5db; padding: 8px; text-align: left; font-size: 12px; }
        th { background: #f3f4f6; }
        .print-note { margin-top: 20px; font-size: 12px; color: #6b7280; }
    </style>
</head>
<body onload="window.print()">
    <h1>{{ $tenantName }}</h1>
    <h2>Rapor Akademik</h2>
    <div class="meta">
        <p><strong>Nama:</strong> {{ $rapor->santri->nama }}</p>
        <p><strong>NIS:</strong> {{ $rapor->santri->nis }}</p>
        <p><strong>Kelas:</strong> {{ $rapor->santri->kelas->nama_kelas ?? '-' }}</p>
        <p><strong>Periode:</strong> {{ $periode?->nama ?? '-' }} ({{ $periode?->tahun_akademik ?? '-' }})</p>
    </div>

    <table>
        <thead>
            <tr>
                <th>Pelajaran</th>
                <th>Kode</th>
                <th>SKS</th>
                <th>Harian</th>
                <th>UTS</th>
                <th>UAS</th>
                <th>Akhir</th>
            </tr>
        </thead>
        <tbody>
        @foreach($rapor->ringkasan_nilai ?? [] as $item)
            <tr>
                <td>{{ $item['pelajaran'] ?? '-' }}</td>
                <td>{{ $item['kode'] ?? '-' }}</td>
                <td>{{ $item['sks'] ?? '-' }}</td>
                <td>{{ $item['nilai_harian'] ?? '-' }}</td>
                <td>{{ $item['nilai_uts'] ?? '-' }}</td>
                <td>{{ $item['nilai_uas'] ?? '-' }}</td>
                <td>{{ $item['nilai_akhir'] ?? '-' }}</td>
            </tr>
        @endforeach
        </tbody>
    </table>

    <div class="summary">
        <p><strong>Total SKS:</strong> {{ number_format((float) $rapor->total_sks, 2) }}</p>
        <p><strong>IPK / Rata-rata:</strong> {{ number_format((float) $rapor->ipk, 2) }}</p>
        <p><strong>Peringkat Kelas:</strong> {{ $rapor->rank_kelas ?? '-' }}</p>
        <p><strong>Catatan Wali Kelas:</strong> {{ $rapor->catatan_walikelas ?: '-' }}</p>
    </div>

    <p class="print-note">Gunakan menu print browser lalu pilih "Save as PDF" untuk menyimpan sebagai PDF.</p>
</body>
</html>
