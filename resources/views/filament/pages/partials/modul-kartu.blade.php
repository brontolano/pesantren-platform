@php
    $total = $m['progress']['total'] ?? 0;
    $selesai = $m['progress']['selesai'] ?? 0;
@endphp
<div class="flex flex-col rounded-2xl border border-stone-200 bg-white p-4 dark:border-white/10 dark:bg-white/5">
    <div class="flex items-start justify-between gap-2">
        <span class="text-sm font-semibold text-stone-900 dark:text-stone-100">{{ $m['label'] }}</span>
        @if ($badge)
            <span class="shrink-0 rounded-full bg-emerald-100 px-2 py-0.5 text-[11px] font-semibold text-emerald-700 dark:bg-emerald-500/15 dark:text-emerald-300">{{ $badge }}</span>
        @elseif ($mode === 'aktif')
            <span class="shrink-0 rounded-full bg-emerald-100 px-2 py-0.5 text-[11px] font-semibold text-emerald-700 dark:bg-emerald-500/15 dark:text-emerald-300">Aktif & siap</span>
        @endif
    </div>

    <p class="mt-1 flex-1 text-xs text-stone-500 dark:text-stone-400">{{ $m['deskripsi'] }}</p>

    @if (! empty($m['prasyarat']))
        <p class="mt-2 text-[11px] text-amber-700 dark:text-amber-400">
            <x-filament::icon icon="heroicon-m-link" class="mr-0.5 inline h-3.5 w-3.5" />{{ $m['prasyarat'] }} (dipasang sekalian)
        </p>
    @endif

    <div class="mt-3 flex items-center justify-between gap-2">
        @if ($mode === 'pasang')
            <span class="text-[11px] text-stone-400">
                {{ $total > 0 ? $total . ' langkah setup' : 'Langsung siap' }}
            </span>
            <x-filament::button
                size="sm"
                icon="heroicon-m-arrow-down-tray"
                wire:click="pasang('{{ $m['key'] }}')"
                wire:confirm="Pasang fitur &quot;{{ $m['label'] }}&quot;?{{ ! empty($m['prasyarat']) ? ' Prasyaratnya akan dipasang sekalian.' : '' }} Anda tetap perlu melengkapi datanya nanti."
            >
                Pasang
            </x-filament::button>

        @elseif ($mode === 'lanjutkan')
            <span class="text-[11px] font-medium text-amber-700 dark:text-amber-400">{{ $selesai }}/{{ $total }} langkah</span>
            @if ($m['setupUrl'])
                <x-filament::button size="sm" tag="a" href="{{ $m['setupUrl'] }}" icon="heroicon-m-arrow-right" color="warning">
                    Lanjutkan setup
                </x-filament::button>
            @endif

        @else {{-- aktif --}}
            <div class="flex items-center gap-3">
                @if ($m['setupUrl'])
                    <x-filament::link size="sm" tag="a" href="{{ $m['setupUrl'] }}">Tinjau</x-filament::link>
                @endif
                <button type="button"
                    class="text-[11px] text-stone-400 hover:text-red-600 dark:hover:text-red-400"
                    wire:click="nonaktif('{{ $m['key'] }}')"
                    wire:confirm="Nonaktifkan fitur &quot;{{ $m['label'] }}&quot;? Menunya disembunyikan, tetapi datanya tetap aman dan bisa diaktifkan lagi kapan saja.">
                    Nonaktifkan
                </button>
            </div>
        @endif
    </div>
</div>
