{{-- T-A042 — handout cetak draf ikhtisar materi kitab / kerangka kultum
     (print-friendly). Status draf diberi tanda jelas; sumber rujukan =
     sitasi deterministik pipeline. Kultum: blok PERIKSA DALIL menonjol
     (rujukan kosong diisi tangan ustadz) + disclaimer wajib PRD §7.5. --}}
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
        ol.daftar { padding-left: 1.4rem; font-size: .95rem; }
        ol.daftar li { margin-bottom: .35rem; }
        .ref { font-size: .75rem; color: #444; }
        table.istilah { width: 100%; border-collapse: collapse; font-size: .9rem; }
        table.istilah th, table.istilah td { border: 1px solid #999; padding: .35rem .5rem; text-align: left; vertical-align: top; }
        .blok-dalil { border: 2px solid #b45309; border-radius: .4rem; padding: .75rem 1rem; margin: 1rem 0; }
        .blok-dalil h2 { border: none; margin-top: 0; color: #b45309; }
        .blok-dalil .rujukan { color: #444; font-size: .85rem; }
        .garis-isian { display: inline-block; min-width: 18rem; border-bottom: 1px dotted #555; }
        .disclaimer { border: 1px solid #555; border-radius: .4rem; background: #f6f6f6; padding: .75rem 1rem; margin-top: 1.25rem; font-size: .9rem; white-space: pre-line; font-weight: bold; }
        .toolbar { margin-bottom: 1rem; }
        .toolbar button { font-size: .9rem; padding: .4rem 1rem; cursor: pointer; }
        @media print { .toolbar { display: none; } body { margin: 0 auto; } }
    </style>
</head>
<body>
    <div class="toolbar">
        <button type="button" onclick="window.print()">Cetak halaman ini</button>
    </div>

    @php($isi = (array) $paket->isi)
    @php($sitasi = (array) $paket->sitasi)

    <header>
        <h1>{{ $paket->judul }}</h1>
        <p class="meta">
            @if ($paket->jenis === 'ikhtisar')
                Ikhtisar materi
                @if ($paket->source?->judul) &middot; Sumber: {{ $paket->source->judul }} @endif
                @if ($paket->bab) &middot; Bagian: {{ $paket->bab }} @endif
            @else
                Kerangka kultum
                @if ($paket->tema) &middot; Tema: {{ $paket->tema }} @endif
                @if ($paket->durasi_menit) &middot; &plusmn;{{ $paket->durasi_menit }} menit @endif
            @endif
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

    @if ($paket->jenis === 'ikhtisar')
        <section aria-label="Poin pembelajaran">
            <h2>Poin Pembelajaran</h2>
            <ol class="daftar">
                @foreach ((array) ($isi['poin'] ?? []) as $poin)
                    <li>
                        {{ is_array($poin) ? ($poin['teks'] ?? '') : $poin }}
                        @php($ref = collect(is_array($poin) ? ($poin['sitasi_idx'] ?? []) : [])->map(fn ($x) => '['.((int) $x + 1).']'))
                        @if ($ref->isNotEmpty())
                            <span class="ref">Sumber {{ $ref->implode(' ') }}</span>
                        @endif
                    </li>
                @endforeach
            </ol>
        </section>

        @if (! empty($isi['istilah']))
            <section aria-label="Istilah kunci">
                <h2>Istilah Kunci</h2>
                <table class="istilah">
                    <thead>
                        <tr><th>Istilah</th><th>Arti</th></tr>
                    </thead>
                    <tbody>
                        @foreach ((array) $isi['istilah'] as $istilah)
                            <tr>
                                <td>{{ $istilah['istilah'] ?? '' }}</td>
                                <td>{{ $istilah['arti'] ?? '' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </section>
        @endif

        @if (! empty($isi['pertanyaan_diskusi']))
            <section aria-label="Pertanyaan diskusi">
                <h2>Pertanyaan Diskusi</h2>
                <ol class="daftar">
                    @foreach ((array) $isi['pertanyaan_diskusi'] as $tanya)
                        <li>{{ is_array($tanya) ? ($tanya['teks'] ?? '') : $tanya }}</li>
                    @endforeach
                </ol>
            </section>
        @endif
    @else
        <section aria-label="Kerangka kultum">
            <h2>Kerangka</h2>
            <ol class="daftar">
                @foreach ((array) ($isi['kerangka'] ?? []) as $bagian)
                    <li>{{ is_array($bagian) ? ($bagian['teks'] ?? '') : $bagian }}</li>
                @endforeach
            </ol>
        </section>

        @if (! empty($isi['pengingat_dalil']))
            <section class="blok-dalil" aria-label="Periksa dalil">
                <h2>PERIKSA DALIL — wajib dilengkapi Ustadz</h2>
                <ol class="daftar">
                    @foreach ((array) $isi['pengingat_dalil'] as $pengingat)
                        <li>
                            {{ $pengingat['klaim'] ?? '' }}<br>
                            <span class="rujukan">
                                Rujukan:
                                @if (trim((string) ($pengingat['rujukan'] ?? '')) !== '')
                                    {{ $pengingat['rujukan'] }}
                                @else
                                    <span class="garis-isian">&nbsp;</span>
                                @endif
                            </span>
                        </li>
                    @endforeach
                </ol>
            </section>
        @endif

        @if (! empty($isi['disclaimer']))
            <p class="disclaimer">{{ $isi['disclaimer'] }}</p>
        @endif
    @endif

    @php($labelSitasi = collect($sitasi)->pluck('label')->filter())
    @if ($labelSitasi->isNotEmpty())
        <section aria-label="Sumber rujukan">
            <h2>Sumber Rujukan</h2>
            <ol class="daftar">
                @foreach ($labelSitasi as $label)
                    <li>{{ $label }}</li>
                @endforeach
            </ol>
        </section>
    @endif
</body>
</html>
