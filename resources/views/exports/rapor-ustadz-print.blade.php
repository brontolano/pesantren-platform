{{-- WP-20 — Cetak Rapor Ustadz Tahunan. Rekap kuantitatif READ-ONLY dari
     jurnal/jadwal/nilai/presensi + penilaian kualitatif mudir (penilaian_ustadz).
     Diakses via route tenant.payroll.rapor-ustadz (gate mudir ATAU pegawai ybs). --}}
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Rapor Ustadz {{ $employee->nama }} — {{ $rekap['tahun'] }}</title>
    <style>
        body { font-family: Arial, sans-serif; color: #111827; margin: 24px; }
        h1, h2, h3 { margin: 0; }
        h3 { margin-top: 20px; }
        .meta { margin-top: 16px; }
        table { width: 100%; border-collapse: collapse; margin-top: 8px; }
        th, td { border: 1px solid #d1d5db; padding: 8px; text-align: left; font-size: 12px; }
        th { background: #f3f4f6; }
        .empty { font-size: 12px; color: #6b7280; margin-top: 4px; }
        .print-note { margin-top: 20px; font-size: 12px; color: #6b7280; }
    </style>
</head>
<body onload="window.print()">
    <h1>{{ $namaPesantren }}</h1>
    <h2>Rapor Ustadz Tahunan — {{ $rekap['tahun'] }}</h2>
    <div class="meta">
        <p><strong>Nama:</strong> {{ $employee->nama }}</p>
        <p><strong>NIP:</strong> {{ $employee->nip ?? '-' }}</p>
        <p><strong>Status:</strong> {{ \Modules\Payroll\Models\Employee::labelStatusKepegawaian($employee->status_kepegawaian) }}</p>
    </div>

    <h3>Rekap Kinerja (Kuantitatif)</h3>
    <table>
        <thead><tr><th>Indikator</th><th>Angka</th><th>Persentase</th></tr></thead>
        <tbody>
            <tr>
                <td>Kehadiran sesi mengajar (jurnal terlaksana / total jurnal)</td>
                <td>{{ $rekap['jurnal_terlaksana'] }} / {{ $rekap['jurnal_total'] }}</td>
                <td>{{ $rekap['kehadiran_sesi_pct'] !== null ? $rekap['kehadiran_sesi_pct'].'%' : '-' }}</td>
            </tr>
            <tr>
                <td>Jurnal terisi (jurnal / ekspektasi {{ $rekap['slot_jadwal_mingguan'] }} slot mingguan × 52)</td>
                <td>{{ $rekap['jurnal_total'] }} / {{ $rekap['jurnal_ekspektasi'] ?? '-' }}</td>
                <td>{{ $rekap['jurnal_terisi_pct'] !== null ? $rekap['jurnal_terisi_pct'].'%' : '-' }}</td>
            </tr>
            <tr>
                <td>Input nilai santri (baris nilai diinput tahun ini)</td>
                <td>{{ $rekap['nilai_input_total'] }}</td>
                <td>-</td>
            </tr>
            <tr>
                <td>Presensi staf (hadir / total tercatat)</td>
                <td>{{ $rekap['presensi_hadir'] }} / {{ $rekap['presensi_total'] }}</td>
                <td>{{ $rekap['presensi_pct'] !== null ? $rekap['presensi_pct'].'%' : '-' }}</td>
            </tr>
        </tbody>
    </table>

    <h3>Penilaian Kualitatif Mudir</h3>
    @if($rekap['penilaian'])
        <table>
            <thead><tr><th>Kriteria</th><th>Skor</th></tr></thead>
            <tbody>
                @foreach(($rekap['penilaian']->skor ?? []) as $kriteria => $skor)
                    <tr><td>{{ $kriteria }}</td><td>{{ $skor }}</td></tr>
                @endforeach
                <tr><th>Rata-rata</th><th>{{ $rekap['penilaian']->rataRata() ?? '-' }}</th></tr>
            </tbody>
        </table>
        @if($rekap['penilaian']->catatan)
            <p style="font-size:12px; margin-top:8px;"><strong>Catatan mudir:</strong> {{ $rekap['penilaian']->catatan }}</p>
        @endif
        <p class="empty">Dinilai oleh: {{ $rekap['penilaian']->penilai?->name ?? '-' }}</p>
    @else
        <p class="empty">Belum ada penilaian kualitatif untuk tahun ini.</p>
    @endif

    <h3>Arsip Kontrak / SK (WP-16)</h3>
    @if($kontrak->isNotEmpty())
        <table>
            <thead><tr><th>Jenis</th><th>Nomor</th><th>Mulai</th><th>Selesai</th><th>Status</th></tr></thead>
            <tbody>
                @foreach($kontrak as $k)
                    <tr>
                        <td>{{ \Modules\Payroll\Models\KontrakKerja::JENIS[$k->jenis] ?? $k->jenis }}</td>
                        <td>{{ $k->nomor }}</td>
                        <td>{{ $k->mulai?->format('d M Y') ?? '-' }}</td>
                        <td>{{ $k->selesai?->format('d M Y') ?? '-' }}</td>
                        <td>{{ \Modules\Payroll\Models\KontrakKerja::STATUS[$k->status] ?? $k->status }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
        <p class="empty">Berkas arsip lengkap: menu Kontrak &amp; SK di panel admin.</p>
    @else
        <p class="empty">Belum ada arsip kontrak/SK untuk pegawai ini.</p>
    @endif

    <p class="print-note">Dicetak {{ now()->format('d M Y H:i') }}. Catatan metode: % kehadiran sesi dihitung dari jurnal mengajar (terlaksana vs total jurnal); ekspektasi jurnal = slot jadwal mingguan × 52 minggu (estimasi kasar).</p>
</body>
</html>
