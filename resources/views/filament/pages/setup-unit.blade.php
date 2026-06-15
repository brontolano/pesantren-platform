<x-filament-panels::page>
    @if ($mode === 'hub')
        <p class="text-sm text-stone-500 dark:text-stone-400">
            Selesaikan setup tiap unit secara bertahap. Klik sebuah unit untuk dipandu langkah demi langkah.
        </p>

        <div class="grid gap-4 sm:grid-cols-2">
            @foreach ($kartu as $u)
                <a href="{{ $u['url'] }}"
                   class="block rounded-2xl border border-stone-200 bg-white p-5 transition hover:border-emerald-300 hover:bg-emerald-50/40 dark:border-white/10 dark:bg-white/5 dark:hover:bg-emerald-950/20">
                    <div class="flex items-center gap-3">
                        <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full
                            {{ $u['progress']['tuntas'] ? 'bg-emerald-600 text-white' : 'bg-stone-100 text-stone-500 dark:bg-white/10 dark:text-stone-300' }}">
                            <x-filament::icon :icon="$u['progress']['tuntas'] ? 'heroicon-m-check' : $u['icon']" class="h-5 w-5" />
                        </span>
                        <div class="min-w-0 flex-1">
                            <span class="block text-sm font-semibold text-stone-900 dark:text-stone-100">{{ $u['label'] }}</span>
                            <span class="block text-xs text-stone-500 dark:text-stone-400">
                                {{ $u['progress']['selesai'] }} dari {{ $u['progress']['total'] }} langkah
                            </span>
                        </div>
                        <span class="text-sm font-semibold {{ $u['progress']['tuntas'] ? 'text-emerald-600' : 'text-stone-400' }}">
                            {{ $u['progress']['persen'] }}%
                        </span>
                    </div>
                    <div class="mt-3 h-2 w-full overflow-hidden rounded-full bg-stone-200/70 dark:bg-white/10">
                        <div class="h-full rounded-full bg-emerald-600 transition-all dark:bg-emerald-500" style="width: {{ $u['progress']['persen'] }}%"></div>
                    </div>
                </a>
            @endforeach
        </div>
    @else
        <div class="flex items-center justify-between gap-4">
            <a href="{{ $hubUrl }}" class="text-sm text-stone-500 hover:text-emerald-700 dark:text-stone-400">&larr; Semua unit</a>
            <span class="text-sm font-semibold {{ $progress['tuntas'] ? 'text-emerald-600' : 'text-stone-400' }}">
                {{ $progress['persen'] }}% selesai
            </span>
        </div>

        <ul class="mt-2 space-y-2">
            @foreach ($steps as $i => $step)
                <li>
                    <div class="flex items-center gap-3 rounded-xl border px-4 py-3
                        {{ $step['key'] === $nextKey
                            ? 'border-emerald-400 bg-emerald-50/60 dark:border-emerald-700 dark:bg-emerald-950/30'
                            : ($step['done'] ? 'border-emerald-200 bg-white/70 dark:border-emerald-900/40 dark:bg-white/5' : 'border-stone-200 bg-white dark:border-white/10 dark:bg-white/5') }}">
                        <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full
                            {{ $step['done'] ? 'bg-emerald-600 text-white' : 'bg-stone-100 text-stone-500 dark:bg-white/10 dark:text-stone-300' }}">
                            @if ($step['done'])
                                <x-filament::icon icon="heroicon-m-check" class="h-5 w-5" />
                            @else
                                <span class="text-sm font-semibold">{{ $i + 1 }}</span>
                            @endif
                        </span>
                        <span class="min-w-0 flex-1">
                            <span class="block text-sm font-semibold {{ $step['done'] ? 'text-stone-400 line-through dark:text-stone-500' : 'text-stone-900 dark:text-stone-100' }}">
                                {{ $step['label'] }}
                            </span>
                            <span class="block text-xs text-stone-500 dark:text-stone-400">{{ $step['desc'] }}</span>
                        </span>
                        @if ($step['url'])
                            <a href="{{ $step['url'] }}"
                               class="shrink-0 rounded-lg px-3 py-1.5 text-xs font-semibold
                                   {{ $step['done']
                                       ? 'text-stone-500 hover:bg-stone-100 dark:text-stone-400 dark:hover:bg-white/10'
                                       : 'bg-emerald-600 text-white hover:bg-emerald-700' }}">
                                {{ $step['done'] ? 'Tinjau' : 'Buka' }}
                            </a>
                        @endif
                    </div>
                </li>
            @endforeach
        </ul>

        @if ($progress['tuntas'])
            <div class="mt-2 rounded-xl border border-emerald-200 bg-emerald-50/60 px-4 py-3 text-sm text-emerald-800 dark:border-emerald-900/50 dark:bg-emerald-950/20 dark:text-emerald-200">
                <x-filament::icon icon="heroicon-m-check-badge" class="mr-1 inline h-5 w-5" />
                Unit {{ $label }} sudah lengkap. 🎉
            </div>
        @endif
    @endif
</x-filament-panels::page>
