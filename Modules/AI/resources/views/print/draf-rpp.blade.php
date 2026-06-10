{{-- T-A041 — lembar cetak/unduh dokumen RPP (print-friendly + HTML ber-mime
     Word untuk unduhan). Status draf diberi tanda jelas; sumber materi per
     seksi (sitasi deterministik pipeline) dicantumkan di bawah tiap seksi. --}}
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $paket->judul }}</title>
    <style>
        * { box-sizing: border-box; }
        body { font-family: Georgia, 'Times New Roman', serif; color: #111; margin: 2rem auto; max-width: 48rem; padding: 0 1rem; line-height: 1.5; }
        header { border-bottom: 2px solid #111; padding-bottom: .75rem; margin-bottom: 1.25rem; }
        h1 { font-size: 1.25rem; margin: 0 0 .25rem; }
        h2 { font-size: 1rem; margin: 1.25rem 0 .35rem; border-bottom: 1px solid #999; padding-bottom: .2rem; }
        .meta { font-size: .85rem; color: #333; }
        .tanda-draf { display: inline-block; border: 2px solid #b45309; color: #b45309; font-weight: bold; padding: .15rem .6rem; border-radius: .35rem; font-size: .8rem; letter-spacing: .08em; }
        .tanda-sah { display: inline-block; border: 2px solid #14532d; color: #14532d; font-weight: bold; padding: .15rem .6rem; border-radius: .35rem; font-size: .8rem; letter-spacing: .08em; }
        .konten { white-space: pre-line; font-size: .95rem; }
        .konten-kosong { color: #888; font-style: italic; font-size: .9rem; }
        .sumber { font-size: .75rem; color: #444; margin-top: .35rem; }
        .toolbar { margin-bottom: 1rem; }
        .toolbar button { font-size: .9rem; padding: .4rem 1rem; cursor: pointer; }
        @media print { .toolbar { display: none; } body { margin: 0 auto; } }
    </style>
</head>
<body>
    @unless ($unduh ?? false)
        <div class="toolbar">
            <button type="button" onclick="window.print()">Cetak halaman ini</button>
        </div>
    @endunless

    <header>
        <h1>{{ $paket->judul }}</h1>
        <p class="meta">
            Mapel/Kitab: {{ $paket->mapel }}
            @if ($paket->kelas) &middot; Kelas/Jenjang: {{ $paket->kelas }} @endif
            @if ($paket->template?->nama) &middot; Template: {{ $paket->template->nama }} @endif
            &middot; {{ count((array) $paket->isi) }} seksi
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

    @foreach ((array) $paket->isi as $seksi)
        <section aria-label="{{ $seksi['judul'] ?? ($seksi['kunci'] ?? '') }}">
            <h2>{{ $seksi['judul'] ?? ($seksi['kunci'] ?? '') }}</h2>
            @if (trim((string) ($seksi['konten'] ?? '')) !== '')
                <p class="konten">{{ $seksi['konten'] }}</p>
            @else
                <p class="konten-kosong">(belum diisi — silakan lengkapi oleh guru)</p>
            @endif
            @php($labelSitasi = collect($seksi['sitasi'] ?? [])->pluck('label')->filter())
            @if ($labelSitasi->isNotEmpty())
                <p class="sumber">Sumber materi: {{ $labelSitasi->implode('; ') }}</p>
            @endif
        </section>
    @endforeach
</body>
</html>
