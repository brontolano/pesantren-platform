@props(['label'])

<div {{ $attributes->class(['pu-data-table']) }}>
    <div class="pu-data-table__desktop" role="region" aria-label="{{ $label }}: tampilan tabel" tabindex="0">
        {{ $desktop ?? '' }}
    </div>
    <div class="pu-data-table__mobile" role="region" aria-label="{{ $label }}: tampilan kartu">
        {{ $mobile ?? '' }}
    </div>
</div>
