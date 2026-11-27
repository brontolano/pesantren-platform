@props(['title', 'tone' => 'info'])

@php
    $tones = [
        'info' => 'pu-status--info',
        'success' => 'pu-status--success',
        'warning' => 'pu-status--warning',
        'danger' => 'pu-status--danger',
    ];
@endphp

<div {{ $attributes->class(['pu-status', $tones[$tone] ?? $tones['info']]) }} role="status">
    <p class="pu-status__title">{{ $title }}</p>
    @if (filled($slot))
        <div class="pu-status__body">{{ $slot }}</div>
    @endif
</div>
