{{--
    Issue #328 — Template cetak Ijazah siap-pakai (parameterik per jenjang & jenis).

    $jenis   : 'muadalah' (Satuan Pendidikan Muadalah) | 'pkpps' (Pendidikan
               Kesetaraan pada Pondok Pesantren Salafiyah).
    $jenjang : 'ula' | 'wustha' | 'ulya' (label besar pada kop).

    Elemen wajib juknis Kemenag yang dimuat:
    - Lambang negara Garuda (aset public/images/sertifikat/garuda.svg —
      PLACEHOLDER, owner WAJIB ganti dengan aset resmi)
    - Nomor ijazah, nama lembaga, NSPP (lembaga.nomor_statistik)
    - Nama santri, tempat/tanggal lahir, NIS & NISN, jenjang
    - Tempat & tanggal penerbitan
    - Tanda tangan pimpinan + slot stempel + slot pasfoto 3x4

    Review kesesuaian final terhadap juknis resmi (pesantrenmuadalah.id) = owner.
--}}
@php
    $santri = $alumni->santri;
    $lembaga = $alumni->lembaga;
    $jenisLabel = $jenis === 'pkpps'
        ? 'PENDIDIKAN KESETARAAN PADA PONDOK PESANTREN SALAFIYAH'
        : 'SATUAN PENDIDIKAN MUADALAH';
    $jenjangLabel = ['ula' => 'ULA', 'wustha' => 'WUSTHA', 'ulya' => 'ULYA'][$jenjang] ?? strtoupper($jenjang);
    $nomor = $alumni->nomor_ijazah ?: $alumni->nomor_syahadah;
@endphp
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>Ijazah — {{ $santri?->nama }}</title>
    <style>
        @page { size: A4 portrait; margin: 0; }
        body { font-family: 'DejaVu Sans', Georgia, serif; font-size: 13px; color: #1F2A24; margin: 0; }
        .lembar { box-sizing: border-box; width: 210mm; min-height: 297mm; margin: 0 auto; padding: 14mm;
                  border: 6px double #B8860B; outline: 2px solid #1F2A24; outline-offset: -12mm; }
        .kop { text-align: center; }
        .garuda { width: 84px; height: 84px; }
        h1 { font-size: 22px; letter-spacing: 6px; margin: 10px 0 2px; }
        .sub { font-size: 13px; letter-spacing: 1px; margin: 2px 0; }
        .jenjang { font-size: 16px; font-weight: bold; letter-spacing: 3px; margin: 4px 0; }
        .nomor { margin: 8px 0 0; font-size: 12px; }
        .lembaga-meta { margin: 14px 0 4px; text-align: center; }
        .lembaga-meta .nama-lembaga { font-size: 16px; font-weight: bold; }
        table.bio { margin: 12px auto 0; border-collapse: collapse; }
        table.bio td { padding: 3px 6px; vertical-align: top; }
        .pernyataan { text-align: center; margin: 18px 24px; line-height: 1.7; }
        .lulus { font-size: 18px; font-weight: bold; letter-spacing: 4px; }
        .bawah { margin-top: 28px; width: 100%; }
        .bawah td { vertical-align: top; }
        .foto-slot { width: 3cm; height: 4cm; border: 1px solid #1F2A24; display: inline-block;
                     text-align: center; font-size: 10px; color: #777; padding-top: 1.6cm; box-sizing: border-box; }
        .ttd { text-align: center; }
        .stempel { display: inline-block; width: 90px; height: 90px; border: 1px dashed #999;
                   border-radius: 50%; font-size: 10px; color: #999; line-height: 90px; }
        .garis-ttd { margin-top: 56px; border-top: 1px solid #1F2A24; display: inline-block; min-width: 220px; padding-top: 4px; }
        @media print { .noprint { display: none; } }
    </style>
</head>
<body>
<div class="lembar">
    <div class="kop">
        <img class="garuda" src="{{ asset('images/sertifikat/garuda.svg') }}" alt="Lambang Negara Garuda Pancasila">
        <h1>IJAZAH</h1>
        <div class="sub">{{ $jenisLabel }}</div>
        <div class="jenjang">JENJANG {{ $jenjangLabel }}</div>
        <div class="nomor">Nomor: <strong>{{ $nomor }}</strong></div>
    </div>

    <div class="lembaga-meta">
        <div class="nama-lembaga">{{ $lembaga?->nama ?? $tenantName }}</div>
        <div>{{ $tenantName }}</div>
        <div>NSPP: {{ $lembaga?->nomor_statistik ?: '—' }}</div>
    </div>

    <p class="pernyataan">Yang bertanda tangan di bawah ini, pimpinan {{ $tenantName }}, menerangkan bahwa:</p>

    <table class="bio">
        <tr><td>Nama</td><td>:</td><td><strong>{{ $santri?->nama ?? '—' }}</strong></td></tr>
        <tr><td>Tempat, Tanggal Lahir</td><td>:</td>
            <td>{{ trim(($santri?->tempat_lahir ? $santri->tempat_lahir.', ' : '').($santri?->tanggal_lahir?->translatedFormat('d F Y') ?? '—')) }}</td></tr>
        <tr><td>Nomor Induk Santri (NIS)</td><td>:</td><td>{{ $santri?->nis ?? '—' }}</td></tr>
        <tr><td>NISN</td><td>:</td><td>{{ $santri?->nisn ?: '—' }}</td></tr>
    </table>

    <div class="pernyataan">
        telah menyelesaikan seluruh program pendidikan pada
        {{ $jenis === 'pkpps' ? 'Pendidikan Kesetaraan Pondok Pesantren Salafiyah' : 'Satuan Pendidikan Muadalah' }}
        jenjang <strong>{{ ucfirst($jenjang) }}</strong> dan dinyatakan<br>
        <span class="lulus">LULUS</span><br>
        pada tahun pelajaran {{ $alumni->tahun_lulus }}.
    </div>

    <table class="bawah">
        <tr>
            <td style="width:30%; text-align:center;">
                <div class="foto-slot">Pasfoto<br>3 × 4</div>
            </td>
            <td style="width:30%; text-align:center;">
                <div class="stempel">Stempel</div>
            </td>
            <td style="width:40%;" class="ttd">
                <div>Diterbitkan di {{ $tempatTerbit ?: '________________' }}</div>
                <div>pada tanggal {{ $tanggalTerbit->translatedFormat('d F Y') }}</div>
                <div style="margin-top:8px;">Pimpinan {{ $tenantName }},</div>
                <div class="garis-ttd">{{ $namaPimpinan ?: '( ................................ )' }}</div>
            </td>
        </tr>
    </table>
</div>
<p class="noprint" style="text-align:center; margin:16px;"><button onclick="window.print()">Cetak</button></p>
</body>
</html>
