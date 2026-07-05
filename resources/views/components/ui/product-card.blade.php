{{-- Kartu produk gaya Shoppe (kartu rapi + badge diskon), direkolorasi tone islami "Nur". --}}
@props([
    'image' => null,
    'title',
    'price',
    'oldPrice' => null,
    'discount' => null, // mis. "20%"
])

<div {{ $attributes->class('rounded-[var(--radius-2xl)] elevation-card bg-[var(--surface-card)] overflow-hidden') }}>
    <div class="relative aspect-square bg-[var(--surface-muted)]">
        @if ($image)
            <img src="{{ $image }}" alt="{{ $title }}" class="w-full h-full object-cover">
        @endif

        @if ($discount)
            <x-ui.badge variant="accent" class="absolute top-2 right-2">-{{ $discount }}</x-ui.badge>
        @endif
    </div>

    <div class="p-3">
        <p class="font-body text-[var(--fs-xs)] text-[var(--text-body)] line-clamp-2">{{ $title }}</p>

        <div class="mt-1 flex items-baseline gap-2">
            <span class="font-display font-bold text-[var(--fs-md)] text-[var(--text-strong)]">{{ $price }}</span>
            @if ($oldPrice)
                <span class="font-body text-[var(--fs-xs)] text-[var(--text-faint)] line-through">{{ $oldPrice }}</span>
            @endif
        </div>
    </div>
</div>
