@props(['tone' => 'neutral'])

@php
    $tones = [
        'neutral' => 'pu-badge--neutral',
        'info' => 'pu-badge--info',
        'success' => 'pu-badge--success',
        'warning' => 'pu-badge--warning',
        'danger' => 'pu-badge--danger',
    ];
@endphp

<span {{ $attributes->class(['pu-badge', $tones[$tone] ?? $tones['neutral']]) }}>{{ $slot }}</span>