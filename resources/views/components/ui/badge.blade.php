@props([
    'variant' => 'accent', // accent (diskon/live) | gold (rating/premium) | success | muted
])

@php
    $variants = [
        'accent' => 'bg-[var(--brand-accent)] text-white',
        'gold' => 'bg-[var(--pst-gold-bright)] text-[var(--pst-gold-ink)]',
        'success' => 'bg-[var(--pst-success-bg)] text-[var(--pst-success-ink)]',
        'muted' => 'bg-[var(--surface-muted)] text-[var(--text-muted)]',
    ];

    $classes = 'inline-flex items-center gap-1 font-display font-bold text-[13px] leading-none rounded-full px-2.5 py-1 '
        . ($variants[$variant] ?? $variants['accent']);
@endphp

<span {{ $attributes->class($classes) }}>
    {{ $slot }}
</span>
