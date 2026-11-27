<x-filament-widgets::widget>
    <section aria-labelledby="onboarding-title" class="rounded-2xl border border-emerald-200 bg-white p-5 shadow-sm dark:border-emerald-900/50 dark:bg-gray-900 sm:p-6">
        <div class="flex items-start justify-between gap-4">
            <div>
                <p class="text-xs font-bold uppercase tracking-wider text-emerald-700 dark:text-emerald-300">Langkah awal</p>
                <h2 id="onboarding-title" class="mt-1 text-lg font-bold tracking-tight text-slate-950 dark:text-white">Mulai dari satu hal dulu</h2>
                <p class="mt-1 text-sm text-slate-600 dark:text-gray-400">{{ $selesai }} dari {{ $total }} langkah tercatat selesai. Langkah lain bisa dilanjutkan nanti.</p>
            </div>
            <button
                type="button"
                wire:click="dismiss"
                aria-label="Tutup panduan langkah awal"
                class="inline-flex min-h-11 min-w-11 shrink-0 items-center justify-center rounded-lg text-sm font-semibold text-slate-600 transition hover:bg-slate-100 focus-visible:outline focus-visible:outline-2 focus-visible:outline-emerald-700 dark:text-gray-300 dark:hover:bg-gray-800"
            >
                Tutup
            </button>
        </div>

        <div
            class="mt-4 h-2 w-full overflow-hidden rounded-full bg-emerald-100 dark:bg-emerald-950/70"
            role="progressbar"
            aria-label="Progres langkah awal"
            aria-valuemin="0"
            aria-valuemax="100"
            aria-valuenow="{{ $persen }}"
        >
            <div class="h-full rounded-full bg-emerald-700 transition-[width] duration-200 dark:bg-emerald-500" style="width: {{ $persen }}%"></div>
        </div>

        @if (! empty($lanjut) && ! empty($lanjut['url']))
            <a href="{{ $lanjut['url'] }}"
               class="mt-4 flex min-h-16 items-center gap-3 rounded-xl bg-emerald-800 px-4 py-3 text-white transition hover:bg-emerald-900 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-emerald-800 dark:bg-emerald-700 dark:hover:bg-emerald-600">
                <x-filament::icon icon="heroicon-m-arrow-right-circle" class="h-6 w-6 shrink-0" aria-hidden="true" />
                <span class="min-w-0 flex-1">
                    <span class="block text-xs font-medium text-emerald-100">Langkah berikutnya</span>
                    <span class="block text-sm font-bold">{{ $lanjut['label'] }}</span>
                </span>
                <span aria-hidden="true" class="text-xl">→</span>
            </a>
        @endif

        <details class="mt-4 group">
            <summary class="flex min-h-11 cursor-pointer list-none items-center justify-between gap-3 rounded-lg px-2 text-sm font-semibold text-slate-700 hover:bg-slate-50 focus-visible:outline focus-visible:outline-2 focus-visible:outline-emerald-700 dark:text-gray-200 dark:hover:bg-gray-800 [&::-webkit-details-marker]:hidden">
                <span>Lihat seluruh langkah ({{ $total }})</span>
                <x-filament::icon icon="heroicon-m-chevron-down" class="h-4 w-4 shrink-0 transition-transform group-open:rotate-180" aria-hidden="true" />
            </summary>

            <ul class="mt-2 space-y-2">
                @foreach ($steps as $step)
                    @php
                        $stepClass = $step['done']
                            ? 'border-emerald-200 bg-emerald-50/60 dark:border-emerald-900/50 dark:bg-emerald-950/20'
                            : 'border-slate-200 bg-white hover:border-emerald-300 hover:bg-emerald-50/40 dark:border-white/10 dark:bg-white/5 dark:hover:bg-emerald-950/30';
                    @endphp
                    <li>
                        @if ($step['url'])
                            <a href="{{ $step['url'] }}" class="flex min-h-14 items-center gap-3 rounded-xl border px-3 py-3 transition {{ $stepClass }} focus-visible:outline focus-visible:outline-2 focus-visible:outline-emerald-700">
                        @else
                            <div class="flex min-h-14 items-center gap-3 rounded-xl border px-3 py-3 {{ $stepClass }}">
                        @endif
                            <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full {{ $step['done'] ? 'bg-emerald-700 text-white dark:bg-emerald-600' : 'bg-slate-100 text-slate-600 dark:bg-white/10 dark:text-gray-300' }}">
                                @if ($step['done'])
                                    <x-filament::icon icon="heroicon-m-check" class="h-5 w-5" aria-hidden="true" />
                                @else
                                    <x-filament::icon :icon="$step['icon']" class="h-5 w-5" aria-hidden="true" />
                                @endif
                            </span>
                            <span class="min-w-0 flex-1">
                                <span class="block text-sm font-semibold {{ $step['done'] ? 'text-slate-600 dark:text-gray-300' : 'text-slate-950 dark:text-white' }}">
                                    @if ($step['done']) <span class="sr-only">Selesai: </span> @endif{{ $step['label'] }}
                                </span>
                                <span class="block text-xs leading-5 text-slate-600 dark:text-gray-400">{{ $step['desc'] }}</span>
                            </span>
                            @unless ($step['done'])
                                <x-filament::icon icon="heroicon-m-chevron-right" class="h-5 w-5 shrink-0 text-slate-500 dark:text-gray-400" aria-hidden="true" />
                            @endunless
                        @if ($step['url'])
                            </a>
                        @else
                            </div>
                        @endif
                    </li>
                @endforeach
            </ul>

            @if (! empty($pusatSetupUrl))
                <a href="{{ $pusatSetupUrl }}" class="mt-3 inline-flex min-h-11 items-center gap-2 rounded-lg px-2 text-sm font-semibold text-emerald-800 hover:bg-emerald-50 focus-visible:outline focus-visible:outline-2 focus-visible:outline-emerald-700 dark:text-emerald-300 dark:hover:bg-emerald-950/40">
                    Buka setup lanjutan per unit
                    <x-filament::icon icon="heroicon-m-arrow-right" class="h-4 w-4" aria-hidden="true" />
                </a>
            @endif
        </details>
    </section>
</x-filament-widgets::widget>
