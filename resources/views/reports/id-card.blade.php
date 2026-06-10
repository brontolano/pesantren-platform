{{-- ID Card santri — print-friendly (browser print / dompdf bila terpasang).
     Grid 2 kolom; tiap kartu ±85.6×54mm (CR80) diskalakan untuk A4. --}}
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="utf-8">
<title>{{ $judul ?? 'ID Card Santri' }}</title>
<style>
    * { box-sizing: border-box; margin: 0; padding: 0; }
    body { font-family: DejaVu Sans, Arial, sans-serif; background: #fff; padding: 10mm; }
    .grid { display: flex; flex-wrap: wrap; gap: 6mm; }
    .card {
        width: 86mm; height: 54mm; border: 0.4mm solid #d1d5db; border-radius: 3mm;
        overflow: hidden; page-break-inside: avoid; display: flex; flex-direction: column;
    }
    .card-head {
        background: {{ $brand['warna'] ?? '#0f766e' }}; color: #fff;
        padding: 2.5mm 4mm; display: flex; align-items: center; gap: 2.5mm;
    }
    .card-head img { height: 8mm; width: 8mm; object-fit: contain; background: #fff; border-radius: 1mm; padding: 0.5mm; }
    .card-head .nama-pesantren { font-size: 3.2mm; font-weight: bold; line-height: 1.15; }
    .card-head .label { font-size: 2.3mm; opacity: .85; letter-spacing: .4mm; }
    .card-body { flex: 1; display: flex; gap: 3mm; padding: 3mm 4mm; align-items: center; }
    .qr { width: 20mm; height: 20mm; flex: 0 0 auto; }
    .qr img { width: 100%; height: 100%; }
    .identitas { flex: 1; min-width: 0; }
    .identitas .nama { font-size: 3.6mm; font-weight: bold; margin-bottom: 1.2mm; }
    .identitas table { font-size: 2.7mm; border-collapse: collapse; }
    .identitas td { padding: 0.4mm 0; vertical-align: top; }
    .identitas td.k { color: #6b7280; padding-right: 2mm; white-space: nowrap; }
    .card-foot {
        border-top: 0.3mm solid #e5e7eb; font-size: 2.2mm; color: #6b7280;
        padding: 1.2mm 4mm; display: flex; justify-content: space-between;
    }
    @media print { body { padding: 6mm; } .card { border-color: #9ca3af; } }
</style>
</head>
<body>
<div class="grid">
    @foreach ($kartu as $k)
        <div class="card">
            <div class="card-head">
                @if (!empty($brand['logo_url']))<img src="{{ $brand['logo_url'] }}" alt="">@endif
                <div>
                    <div class="nama-pesantren">{{ $brand['nama'] ?? 'Pesantren' }}</div>
                    <div class="label">KARTU IDENTITAS SANTRI</div>
                </div>
            </div>
            <div class="card-body">
                <div class="qr"><img src="{{ $k['qr'] }}" alt="QR {{ $k['nis'] }}"></div>
                <div class="identitas">
                    <div class="nama">{{ $k['nama'] }}</div>
                    <table>
                        <tr><td class="k">NIS</td><td>: {{ $k['nis'] }}</td></tr>
                        <tr><td class="k">Kelas</td><td>: {{ $k['kelas'] ?? '-' }}</td></tr>
                        <tr><td class="k">TA</td><td>: {{ $k['tahun'] ?? '-' }}</td></tr>
                    </table>
                </div>
            </div>
            <div class="card-foot">
                <span>{{ $brand['tagline'] ?? '' }}</span>
                <span>{{ $k['nis'] }}</span>
            </div>
        </div>
    @endforeach
</div>
</body>
</html>
