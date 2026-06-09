{{-- F8 — laporan generik: judul + tabel opsional (kolom/baris) + ringkasan. --}}
@extends('reports.layout')

@section('isi')
    <div class="judul">{{ $judul ?? 'Laporan' }}</div>

    @if(!empty($ringkasan))
        <p>{!! nl2br(e($ringkasan)) !!}</p>
    @endif

    @if(!empty($kolom) && isset($baris))
        <table>
            <thead>
                <tr>
                    @foreach($kolom as $k)
                        <th>{{ $k }}</th>
                    @endforeach
                </tr>
            </thead>
            <tbody>
                @forelse($baris as $row)
                    <tr>
                        @foreach((array) $row as $sel)
                            <td>{{ is_scalar($sel) ? $sel : json_encode($sel) }}</td>
                        @endforeach
                    </tr>
                @empty
                    <tr><td colspan="{{ count($kolom) }}">Tidak ada data.</td></tr>
                @endforelse
            </tbody>
        </table>
    @endif

    @if(!empty($penandatangan))
        <div class="ttd">
            <div>{{ $tempat_tanggal ?? '' }}</div>
            <div style="margin-top:48px; font-weight:700;">{{ $penandatangan }}</div>
            @if(!empty($jabatan))<div>{{ $jabatan }}</div>@endif
        </div>
    @endif
@endsection
