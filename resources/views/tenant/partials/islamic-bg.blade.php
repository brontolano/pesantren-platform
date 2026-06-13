{{-- Overlay tekstur girih (bintang 8-titik Rub el Hizb), di-port dari
     resources/js/Components/Ui/IslamicPattern.vue. Dekoratif: aria-hidden,
     pointer-events none, opacity rendah. Vars: $id, $color, $opacity. --}}
@php($pid = $id ?? 'igp')
@php($pc = $color ?? '#ffffff')
@php($po = $opacity ?? 0.07)
<div aria-hidden="true" style="position:absolute;inset:0;width:100%;height:100%;pointer-events:none;opacity:{{ $po }}">
    <svg width="100%" height="100%" preserveAspectRatio="xMidYMid slice" aria-hidden="true">
        <defs>
            <pattern id="{{ $pid }}" width="56" height="56" patternUnits="userSpaceOnUse">
                <g transform="translate(28 28)" fill="none" stroke="{{ $pc }}" stroke-width="1" stroke-linejoin="round" stroke-linecap="round">
                    <rect x="-12" y="-12" width="24" height="24"/>
                    <rect x="-12" y="-12" width="24" height="24" transform="rotate(45)"/>
                    <polygon points="-12,0 -8.49,-8.49 0,-12 8.49,-8.49 12,0 8.49,8.49 0,12 -8.49,8.49"/>
                </g>
                <g fill="none" stroke="{{ $pc }}" stroke-width="1" stroke-linejoin="round">
                    <path d="M0,-5 L5,0 L0,5 L-5,0 Z"/>
                    <path d="M56,-5 L61,0 L56,5 L51,0 Z"/>
                    <path d="M0,51 L5,56 L0,61 L-5,56 Z"/>
                    <path d="M56,51 L61,56 L56,61 L51,56 Z"/>
                </g>
            </pattern>
        </defs>
        <rect width="100%" height="100%" fill="url(#{{ $pid }})"/>
    </svg>
</div>
