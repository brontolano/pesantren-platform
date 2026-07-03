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
<style data-arabic-util>
    .text-arabic,
    [lang="ar"] {
        font-family: 'Amiri', 'Scheherazade New', serif !important;
        direction: rtl;
        line-height: 1.9;
    }
</style>
