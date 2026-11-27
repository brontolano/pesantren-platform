@props(['variant' => 'primary', 'size' => 'md', 'type' => 'button'])

@php
    $variants = [
        'primary' => 'pu-button--primary',
        'secondary' => 'pu-button--secondary',
        'quiet' => 'pu-button--quiet',
        'danger' => 'pu-button--danger',
    ];
    $sizes = [
        'sm' => 'pu-button--sm',
        'md' => 'pu-button--md',
        'lg' => 'pu-button--lg',
    ];
@endphp

<button type="{{ $type }}" {{ $attributes->class(['pu-button', $variants[$variant] ?? $variants['primary'], $sizes[$size] ?? $sizes['md']]) }}>
    {{ $slot }}
</button>
