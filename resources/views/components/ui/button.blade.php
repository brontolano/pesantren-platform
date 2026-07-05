@props([
    'variant' => 'primary', // primary | outline | ghost
    'tag' => 'button',
])

@php
    $base = 'inline-flex items-center justify-center gap-2 font-display font-semibold text-[15px] rounded-[var(--radius-pill)] px-6 py-3 min-h-[var(--tap-min)] transition active:scale-[var(--press-scale)]';

    $variants = [
        'primary' => 'bg-[var(--brand-primary)] text-white shadow-[var(--shadow-brand)] hover:bg-[var(--brand-primary-strong)]',
        'outline' => 'bg-transparent text-[var(--brand-primary)] ring-1 ring-inset ring-[var(--brand-primary)] hover:bg-[var(--brand-primary-tint)]',
        'ghost' => 'bg-[var(--surface-muted)] text-[var(--text-strong)] hover:bg-[var(--brand-primary-tint)]',
    ];

    $classes = $base . ' ' . ($variants[$variant] ?? $variants['primary']);
@endphp

<{{ $tag }} {{ $attributes->class($classes) }}>
    {{ $slot }}
</{{ $tag }}>
