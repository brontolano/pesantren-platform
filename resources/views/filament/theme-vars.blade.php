@php
    $temaVars = \App\Support\PanelTheme::cssVars();
    $temaKelas = \App\Support\PanelTheme::bodyClass();
@endphp

@if ($temaVars !== '')
    <style data-tema-vars>:root{ {!! $temaVars !!} }</style>
@endif

@if ($temaKelas !== null)
    <script data-tema-kelas>document.body.classList.add(@js($temaKelas));</script>
@endif

{{-- Font Arab (Amiri) GLOBAL: ayat/teks Arab tampil rapi di SEMUA tema,
     bukan cuma Baitul Maal. Hanya elemen ber-.text-arabic atau [lang="ar"]
     yang terpengaruh — TIDAK memaksa RTL/serif ke seluruh halaman. --}}
<link rel="preconnect" href="https://fonts.bunny.net">
<link href="https://fonts.bunny.net/css?family=amiri:400,700" rel="stylesheet">

{{-- Font per-tema (SKIN LENGKAP) via bunny.net (privacy-friendly, no Google).
     Dimuat hanya bila tema aktif memakai body-kelas (tema non-bawaan); tema
     'bawaan' pakai Plus Jakarta Sans yang sudah dimuat global — TIDAK
     menambah request font di default. Family di-set --tema-font-sans /
     --tema-font-heading di PanelTheme::vars, dengan fallback system-ui. --}}
@if ($temaKelas !== null)
    @php
        // Peta bodyClass → daftar family bunny.net yang dibutuhkan tema itu.
        // Minimal: hanya font tema aktif yang di-request.
        $temaFonts = [
            'tema-slate-pro' => 'jost:400,500,600,700',
            'tema-glass' => 'inter:400,500,600,700',
            'tema-corporate' => 'ibm-plex-sans:400,500,600,700',
            'tema-midnight' => 'space-grotesk:400,500,600,700',
            'tema-warm' => 'nunito:400,600,700,800',
            'tema-aurora' => 'outfit:400,500,600,700',
            'tema-nature' => 'lora:400,500,600,700|plus-jakarta-sans:400,500,600,700',
            'tema-neon' => 'orbitron:400,600,700,800|inter:400,500,600,700',
        ];
    @endphp
    @if (isset($temaFonts[$temaKelas]))
        <link href="https://fonts.bunny.net/css?family={{ $temaFonts[$temaKelas] }}&display=swap" rel="stylesheet" data-tema-font>
    @endif
@endif
<style data-arabic-util>
    .text-arabic,
    [lang="ar"] {
        font-family: 'Amiri', 'Scheherazade New', serif !important;
        direction: rtl;
        line-height: 1.9;
    }
</style>
