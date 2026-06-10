{{-- T-A040 — lembar cetak paket soal (print-friendly). Status draf diberi
     tanda jelas; lembar kunci & kisi dipisah halaman dari lembar soal. --}}
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $paket->judul }} — Cetak</title>
    <style>
        * { box-sizing: border-box; }
        body { font-family: Georgia, 'Times New Roman', serif; color: #111; margin: 2rem auto; max-width: 48rem; padding: 0 1rem; line-height: 1.5; }
        header { border-bottom: 2px solid #111; padding-bottom: .75rem; margin-bottom: 1.25rem; }
        h1 { font-size: 1.25rem; margin: 0 0 .25rem; }
        .meta { font-size: .85rem; color: #333; }
        .tanda-draf { display: inline-block; border: 2px solid #b45309; color: #b45309; font-weight: bold; padding: .15rem .6rem; border-radius: .35rem; font-size: .8rem; letter-spacing: .08em; }
        .tanda-sah { display: inline-block; border: 2px solid #14532d; color: #14532d; font-weight: bold; padding: .15rem .6rem; border-radius: .35rem; font-size: .8rem; letter-spacing: .08em; }
        ol.soal { padding-left: 1.5rem; }
        ol.soal > li { margin-bottom: 1rem; }
        ol.opsi { list-style-type: upper-alpha; padding-left: 1.5rem; margin: .35rem 0; }
        .lembar-kunci { page-break-before: always; border-top: 2px solid #111; margin-top: 2rem; padding-top: 1rem; }
        table { width: 100%; border-collapse: collapse; font-size: .9rem; }
        th, td { border: 1px solid #555; padding: .35rem .5rem; text-align: left; vertical-align: top; }
        .sumber { font-size: .75rem; color: #444; }
        .toolbar { margin-bottom: 1rem; }
        .toolbar button { font-size: .9rem; padding: .4rem 1rem; cursor: pointer; }
        @media print { .toolbar { display: none; } body { margin: 0 auto; } }
    </style>
</head>
<body>
    <div class="toolbar">
        <button type="button" onclick="window.print()">Cetak halaman ini</button>
    </div>

    <header>
        <h1>{{ $paket->judul }}</h1>
        <p class="meta">
            Mapel/Kitab: {{ $paket->mapel }}
            @if ($paket->bab) &middot; Bab: {{ $paket->bab }} @endif
            @if ($paket->jenjang) &middot; Jenjang: {{ $paket->jenjang }} @endif
            &middot; Tipe: {{ str_replace('_', ' ', $paket->tipe) }}
            @if ($paket->tingkat_kognitif) &middot; Tingkat kognitif: {{ $paket->tingkat_kognitif }} @endif
            &middot; {{ count((array) $paket->butir) }} butir
        </p>
        <p class="meta">
            @if ($paket->status === 'disahkan')
                <span class="tanda-sah">DISAHKAN</span>
                @if ($namaPengesah) oleh {{ $namaPengesah }} @endif
                @if ($paket->disahkan_pada) pada {{ $paket->disahkan_pada->translatedFormat('d F Y H:i') }} @endif
            @else
                <span class="tanda-draf">DRAF — BELUM DISAHKAN</span>
                &nbsp;Disusun dengan bantuan AI; periksa kembali sebelum digunakan.
            @endif
        </p>
    </header>

    <section aria-label="Lembar soal">
        <h2 style="font-size:1rem;">Lembar Soal</h2>
        <ol class="soal">
            @foreach ((array) $paket->butir as $butir)
                <li>
                    {{ $butir['pertanyaan'] ?? '' }}
                    @if (is_array($butir['opsi'] ?? null) && $butir['opsi'] !== [])
                        <ol class="opsi">
                            @foreach ($butir['opsi'] as $opsi)
                                <li>{{ $opsi }}</li>
                            @endforeach
                        </ol>
                    @endif
                </li>
            @endforeach
        </ol>
    </section>

    <section class="lembar-kunci" aria-label="Kunci jawaban dan kisi-kisi">
        <h2 style="font-size:1rem;">Kunci Jawaban, Kisi-kisi &amp; Sumber Materi</h2>
        <table>
            <thead>
                <tr>
                    <th style="width:3rem;">No</th>
                    <th>Kunci</th>
                    <th>Kisi-kisi / indikator</th>
                    <th>Sumber materi</th>
                </tr>
            </thead>
            <tbody>
                @foreach ((array) $paket->butir as $butir)
                    <tr>
                        <td>{{ $butir['no'] ?? '' }}</td>
                        <td>{{ $butir['kunci'] ?? '-' }}</td>
                        <td>{{ $butir['kisi'] ?? '-' }}</td>
                        <td class="sumber">
                            @foreach (collect($butir['sitasi'] ?? [])->pluck('label')->filter() as $label)
                                {{ $label }}@if (! $loop->last); @endif
                            @endforeach
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </section>
</body>
</html>
