{{-- WP-10 — Cetak Rapor Diniyah. $blokAktif (array nama blok dari Setting
     akademik.rapor_diniyah_blok) menentukan blok mana yang TERCETAK —
     blok dimatikan tenant TIDAK muncul sama sekali (policy-first). --}}
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Rapor Diniyah {{ $rapor->santri->nama }}</title>
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
        .catatan { margin-top: 16px; font-size: 12px; }
    </style>
</head>
<body onload="window.print()">
    @php($blok = $rapor->blok ?? [])
    <h1>{{ $tenantName }}</h1>
    <h2>Rapor Diniyah</h2>
    <div class="meta">
        <p><strong>Nama:</strong> {{ $rapor->santri->nama }}</p>
        <p><strong>NIS:</strong> {{ $rapor->santri->nis }}</p>
        <p><strong>Kelas:</strong> {{ $rapor->santri->kelas->nama_kelas ?? '-' }}</p>
        <p><strong>Periode:</strong> {{ $periode?->nama ?? '-' }} ({{ $periode?->tahun_akademik ?? '-' }}@if($periode?->tahun_hijriyah) / {{ $periode->tahun_hijriyah }} H @endif)</p>
    </div>

    @if(in_array('nilai_imtihan', $blokAktif, true))
        <h3>Nilai Imtihan</h3>
        @if(!empty($blok['nilai_imtihan']['daftar']))
            <table>
                <thead><tr><th>Pelajaran</th><th>Kode</th><th>Nilai Akhir</th></tr></thead>
                <tbody>
                @foreach($blok['nilai_imtihan']['daftar'] as $item)
                    <tr>
                        <td>{{ $item['pelajaran'] ?? '-' }}</td>
                        <td>{{ $item['kode'] ?? '-' }}</td>
                        <td>{{ $item['nilai_akhir'] ?? '-' }}</td>
                    </tr>
                @endforeach
                    <tr><th colspan="2">Rata-rata</th><th>{{ $blok['nilai_imtihan']['rata_rata'] ?? '-' }}</th></tr>
                </tbody>
            </table>
        @else
            <p class="empty">Belum ada nilai imtihan pada periode ini.</p>
        @endif
    @endif

    @if(in_array('capaian_kitab', $blokAktif, true))
        <h3>Capaian Kitab</h3>
        @if(!empty($blok['capaian_kitab']['daftar']))
            <table>
                <thead><tr><th>Kitab</th><th>Metode</th><th>Posisi</th><th>Status</th><th>Tanggal</th></tr></thead>
                <tbody>
                @foreach($blok['capaian_kitab']['daftar'] as $item)
                    <tr>
                        <td>{{ $item['kitab'] ?? '-' }}</td>
                        <td>{{ $item['metode'] ?? '-' }}</td>
                        <td>{{ $item['posisi'] ?? '-' }}</td>
                        <td>{{ $item['status'] ?? '-' }}</td>
                        <td>{{ $item['tanggal'] ?? '-' }}</td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        @else
            <p class="empty">Belum ada capaian kitab pada periode ini.</p>
        @endif
    @endif

    @if(in_array('sorogan', $blokAktif, true))
        <h3>Sorogan &amp; Ngaji Kitab</h3>
        @if(!empty($blok['sorogan']['daftar']))
            <table>
                <thead><tr><th>Kitab</th><th>Setoran</th><th>Posisi Terakhir</th><th>Status</th><th>Nilai Rata</th></tr></thead>
                <tbody>
                @foreach($blok['sorogan']['daftar'] as $item)
                    <tr>
                        <td>{{ $item['kitab'] ?? '-' }}</td>
                        <td>{{ $item['jumlah_setoran'] ?? 0 }}</td>
                        <td>{{ $item['posisi_terakhir'] ?? '-' }}</td>
                        <td>{{ !empty($item['khatam']) ? 'khatam' : ($item['status_terakhir'] ?? '-') }}</td>
                        <td>{{ $item['nilai_rata'] ?? '-' }}</td>
                    </tr>
                @endforeach
                </tbody>
            </table>
            <p>
                Total setoran: {{ $blok['sorogan']['jumlah_setoran'] ?? 0 }} —
                Kitab khatam: {{ $blok['sorogan']['kitab_khatam'] ?? 0 }} —
                Nilai rata: {{ $blok['sorogan']['nilai_rata'] ?? '-' }} —
                Sesi bandongan/halaqah: hadir {{ $blok['sorogan']['sesi_bandongan']['hadir'] ?? 0 }} dari {{ $blok['sorogan']['sesi_bandongan']['ikut'] ?? 0 }}
            </p>
        @else
            <p class="empty">Belum ada setoran sorogan pada periode ini.</p>
        @endif
    @endif

    @if(in_array('tahfidz', $blokAktif, true))
        <h3>Tahfidz</h3>
        <table>
            <thead><tr><th>Jumlah Setoran Selesai</th><th>Posisi Terakhir</th><th>Nilai Rata-rata</th></tr></thead>
            <tbody>
                <tr>
                    <td>{{ $blok['tahfidz']['jumlah_setoran'] ?? 0 }}</td>
                    <td>
                        @if(!empty($blok['tahfidz']['terakhir']))
                            {{ $blok['tahfidz']['terakhir']['surah'] ?? '-' }}
                            : {{ $blok['tahfidz']['terakhir']['ayat_awal'] ?? '?' }}–{{ $blok['tahfidz']['terakhir']['ayat_akhir'] ?? '?' }}
                        @else
                            Belum ada setoran
                        @endif
                    </td>
                    <td>{{ $blok['tahfidz']['nilai_rata'] ?? '-' }}</td>
                </tr>
            </tbody>
        </table>
    @endif

    @if(in_array('ubudiyah_akhlak', $blokAktif, true))
        <h3>Ubudiyah &amp; Akhlak</h3>
        @if(!empty($blok['ubudiyah_akhlak']['daftar']))
            <table>
                <thead><tr><th>Kriteria</th><th>Bobot</th><th>Skor</th></tr></thead>
                <tbody>
                @foreach($blok['ubudiyah_akhlak']['daftar'] as $item)
                    <tr>
                        <td>{{ $item['kriteria'] ?? '-' }}</td>
                        <td>{{ $item['bobot'] ?? '-' }}</td>
                        <td>{{ $item['skor'] ?? '-' }}</td>
                    </tr>
                @endforeach
                    <tr><th colspan="2">Nilai Agregat</th><th>{{ $blok['ubudiyah_akhlak']['agregat'] ?? '-' }}</th></tr>
                </tbody>
            </table>
        @else
            <p class="empty">Belum ada penilaian rubrik pada periode ini.</p>
        @endif
    @endif

    @if(in_array('kehadiran_kegiatan', $blokAktif, true))
        <h3>Kehadiran Kegiatan</h3>
        <table>
            <thead><tr><th>Hadir</th><th>Hari Efektif</th><th>Persentase</th></tr></thead>
            <tbody>
                <tr>
                    <td>{{ $blok['kehadiran_kegiatan']['hadir'] ?? 0 }}</td>
                    <td>{{ $blok['kehadiran_kegiatan']['hari_efektif'] ?? '-' }}</td>
                    <td>{{ isset($blok['kehadiran_kegiatan']['persentase']) && $blok['kehadiran_kegiatan']['persentase'] !== null ? $blok['kehadiran_kegiatan']['persentase'].'%' : '-' }}</td>
                </tr>
            </tbody>
        </table>
    @endif

    @if(in_array('disiplin', $blokAktif, true))
        <h3>Disiplin</h3>
        <table>
            <thead><tr><th>Poin Pelanggaran (bersih)</th></tr></thead>
            <tbody><tr><td>{{ $blok['disiplin']['poin_bersih'] ?? 0 }}</td></tr></tbody>
        </table>
    @endif

    <div class="catatan">
        <p><strong>Catatan Mustahiq:</strong> {{ $rapor->catatan_mustahiq ?: '-' }}</p>
        <p><strong>Catatan Pengasuh:</strong> {{ $rapor->catatan_pengasuh ?: '-' }}</p>
    </div>

    <p class="print-note">Gunakan menu print browser lalu pilih "Save as PDF" untuk menyimpan sebagai PDF.</p>
</body>
</html>
