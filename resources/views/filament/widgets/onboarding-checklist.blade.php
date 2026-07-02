<x-filament-widgets::widget>
    <div class="rounded-2xl border border-emerald-200 bg-emerald-50/60 p-5 dark:border-emerald-900/50 dark:bg-emerald-950/20">
        <div class="flex items-start justify-between gap-4">
            <div>
                <h3 class="text-base font-semibold text-emerald-900 dark:text-emerald-100">
                    Selamat datang! Yuk lengkapi langkah awal
                </h3>
                <p class="mt-0.5 text-sm text-emerald-700/80 dark:text-emerald-200/70">
                    {{ $selesai }} dari {{ $total }} langkah selesai — tinggal sedikit lagi.
                </p>
            </div>
            <button
                type="button"
                wire:click="dismiss"
                class="shrink-0 rounded-lg px-2.5 py-1 text-xs font-medium text-emerald-700/70 hover:bg-emerald-100 dark:text-emerald-300/70 dark:hover:bg-emerald-900/40"
            >
                Tutup
            </button>
        </div>

        {{-- Progress bar --}}
        <div class="mt-3 h-2 w-full overflow-hidden rounded-full bg-emerald-200/70 dark:bg-emerald-900/50">
            <div class="h-full rounded-full bg-emerald-600 transition-all dark:bg-emerald-500" style="width: {{ $persen }}%"></div>
        </div>

        {{-- Penunjuk "Lanjut ke" (#350 poin 3): satu jalan jelas ke langkah berikutnya. --}}
        @if (! empty($lanjut) && ! empty($lanjut['url']))
            <a href="{{ $lanjut['url'] }}"
               class="mt-3 flex items-center gap-2 rounded-xl border border-emerald-300 bg-white px-4 py-2.5 transition hover:bg-emerald-50 dark:border-emerald-800/60 dark:bg-white/5 dark:hover:bg-emerald-950/40">
                <x-filament::icon icon="heroicon-m-arrow-right-circle" class="h-5 w-5 shrink-0 text-emerald-600" />
                <span class="min-w-0 flex-1 text-sm font-semibold text-emerald-900 dark:text-emerald-100">
                    Lanjut ke: {{ $lanjut['label'] }}
                </span>
                <span class="shrink-0 rounded-lg bg-emerald-600 px-3 py-1 text-xs font-semibold text-white">Lanjutkan</span>
            </a>
        @endif

        {{-- Daftar langkah --}}
        <ul class="mt-4 space-y-2">
            @foreach ($steps as $step)
                <li>
                    <a
                        @if ($step['url']) href="{{ $step['url'] }}" @endif
                        class="flex items-center gap-3 rounded-xl border px-4 py-3 transition
                            {{ $step['done']
                                ? 'border-emerald-200 bg-white/70 dark:border-emerald-900/40 dark:bg-white/5'
                                : 'border-stone-200 bg-white hover:border-emerald-300 hover:bg-emerald-50/50 dark:border-white/10 dark:bg-white/5 dark:hover:bg-emerald-950/30' }}"
                    >
                        <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full
                            {{ $step['done'] ? 'bg-emerald-600 text-white' : 'bg-stone-100 text-stone-500 dark:bg-white/10 dark:text-stone-300' }}">
                            @if ($step['done'])
                                <x-filament::icon icon="heroicon-m-check" class="h-5 w-5" />
                            @else
                                <x-filament::icon :icon="$step['icon']" class="h-5 w-5" />
                            @endif
                        </span>
                        <span class="min-w-0 flex-1">
                            <span class="block text-sm font-semibold {{ $step['done'] ? 'text-stone-400 line-through dark:text-stone-500' : 'text-stone-900 dark:text-stone-100' }}">
                                {{ $step['label'] }}
                            </span>
                            <span class="block text-xs text-stone-500 dark:text-stone-400">{{ $step['desc'] }}</span>
                        </span>
                        @unless ($step['done'])
                            <x-filament::icon icon="heroicon-m-chevron-right" class="h-5 w-5 shrink-0 text-stone-400" />
                        @endunless
                    </a>
                </li>
            @endforeach
        </ul>

        @if (! empty($pusatSetupUrl))
            <a href="{{ $pusatSetupUrl }}"
               class="mt-3 inline-flex items-center gap-1 text-sm font-medium text-emerald-700 hover:text-emerald-800 dark:text-emerald-300">
                Buka Pusat Setup lengkap (per unit)
                <x-filament::icon icon="heroicon-m-arrow-right" class="h-4 w-4" />
            </a>
        @endif
    </div>
</x-filament-widgets::widget>
