{{-- Pembatas ornamental islami (port dari OrnamentDivider.vue). Var: $color. --}}
@php($oc = $color ?? 'var(--gold)')
<div aria-hidden="true" style="display:flex;align-items:center;gap:12px;width:100%">
    <span style="height:1px;flex:1;border-radius:9999px;background-image:linear-gradient(90deg,transparent,{{ $oc }});opacity:.55"></span>
    <svg width="34" height="14" viewBox="0 0 34 14" fill="none" stroke="{{ $oc }}" stroke-width="1" stroke-linejoin="round" style="flex-shrink:0">
        <path d="M17 1 L20 7 L17 13 L14 7 Z"/>
        <path d="M11 7 L17 4 L23 7 L17 10 Z"/>
        <circle cx="4" cy="7" r="1.1" fill="{{ $oc }}" stroke="none"/>
        <circle cx="30" cy="7" r="1.1" fill="{{ $oc }}" stroke="none"/>
    </svg>
    <span style="height:1px;flex:1;border-radius:9999px;background-image:linear-gradient(90deg,{{ $oc }},transparent);opacity:.55"></span>
</div>
