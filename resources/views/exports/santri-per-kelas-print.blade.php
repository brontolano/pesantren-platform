<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>Daftar Santri {{ $kelas->nama_kelas }}</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            color: #0f172a;
            margin: 24px;
        }

        h1, h2, p {
            margin: 0;
        }

        .header {
            margin-bottom: 20px;
        }

        .meta {
            margin-top: 8px;
            color: #475569;
            font-size: 12px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
            font-size: 12px;
        }

        th, td {
            border: 1px solid #cbd5e1;
            padding: 8px;
            text-align: left;
            vertical-align: top;
        }

        th {
            background: #e2e8f0;
        }

        .summary {
            display: flex;
            gap: 18px;
            margin-top: 12px;
            font-size: 12px;
        }

        @media print {
            body {
                margin: 12mm;
            }

            .print-note {
                display: none;
            }
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>{{ $tenantName }}</h1>
        <h2>Daftar Santri Kelas {{ $kelas->nama_kelas }}</h2>
        <p class="meta">Status filter: {{ $status !== '' ? ucfirst($status) : 'Semua status' }}</p>
        <p class="meta">Dicetak: {{ $printedAt->format('d M Y H:i') }}</p>
        <div class="summary">
            <span>Total santri: {{ $santri->count() }}</span>
            <span>Tingkat: {{ $kelas->tingkat }}</span>
            <span>Jurusan: {{ $kelas->jurusan ?: '-' }}</span>
        </div>
    </div>

    <p class="print-note">Gunakan menu print browser lalu pilih "Save as PDF" untuk menyimpan sebagai PDF.</p>

    <table>
        <thead>
            <tr>
                <th>No</th>
                <th>NIS</th>
                <th>Nama</th>
                <th>L/P</th>
                <th>Tanggal Lahir</th>
                <th>Status</th>
                <th>Angkatan</th>
                <th>Wali</th>
                <th>No. HP Wali</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($santri as $index => $item)
                <tr>
                    <td>{{ $index + 1 }}</td>
                    <td>{{ $item->nis }}</td>
                    <td>{{ $item->nama }}</td>
                    <td>{{ $item->jenis_kelamin }}</td>
                    <td>{{ optional($item->tanggal_lahir)->format('d-m-Y') }}</td>
                    <td>{{ ucfirst($item->status) }}</td>
                    <td>{{ $item->tahun_angkatan ?: '-' }}</td>
                    <td>{{ $item->nama_ayah ?: $item->nama_ibu ?: '-' }}</td>
                    <td>{{ $item->no_hp_wali ?: '-' }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="9">Belum ada data santri pada filter ini.</td>
                </tr>
            @endforelse
        </tbody>
    </table>

    <script>
        window.addEventListener('load', function () {
            window.print();
        });
    </script>
</body>
</html>
