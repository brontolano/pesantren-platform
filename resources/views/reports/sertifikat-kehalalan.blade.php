{{-- F8 — Sertifikat Kehalalan Pengelolaan Dana (melengkapi F3 yang tertunda). --}}
@extends('reports.layout')

@section('isi')
    <div class="judul" style="font-size:18px;">SERTIFIKAT KEHALALAN PENGELOLAAN DANA</div>

    <p style="text-align:center;">Dengan ini menyatakan bahwa pengelolaan keuangan</p>
    <p style="text-align:center; font-size:16px; font-weight:700;">{{ $brand['nama'] ?? 'Pesantren' }}</p>

    <p style="text-align:center;">
        untuk periode <strong>{{ $periode ?? '-' }}</strong> telah ditelaah dimensi kehalalannya
        dengan hasil:
    </p>

    <table style="width:70%; margin:12px auto;">
        <tr><th>Persentase Pendapatan Halal</th><td>{{ $persen_halal ?? '-' }}%</td></tr>
        <tr><th>Pendapatan Syubhat</th><td>Rp {{ number_format((float)($nominal_syubhat ?? 0), 0, ',', '.') }}</td></tr>
        <tr><th>Saldo Dana Kebajikan</th><td>Rp {{ number_format((float)($saldo_kebajikan ?? 0), 0, ',', '.') }}</td></tr>
        <tr><th>Status</th><td><strong>{{ $status ?? 'Sesuai Prinsip Syariah' }}</strong></td></tr>
    </table>

    @if(!empty($catatan))
        <p style="margin-top:12px;"><em>{{ $catatan }}</em></p>
    @endif

    <div class="ttd">
        <div>{{ $tempat_tanggal ?? '' }}</div>
        <div style="margin-top:48px; font-weight:700;">{{ $penandatangan ?? 'Pengawas Syariah' }}</div>
        @if(!empty($jabatan))<div>{{ $jabatan }}</div>@endif
    </div>
@endsection
