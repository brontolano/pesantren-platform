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
