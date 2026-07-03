<x-filament-widgets::widget>
    @php
        $chip = [
            'success' => 'bg-green-50 text-green-700 dark:bg-green-500/10 dark:text-green-400',
            'warning' => 'bg-amber-50 text-amber-700 dark:bg-amber-500/10 dark:text-amber-400',
            'info'    => 'bg-blue-50 text-blue-700 dark:bg-blue-500/10 dark:text-blue-400',
            'primary' => 'bg-primary-50 text-primary-700 dark:bg-primary-500/10 dark:text-primary-400',
            'danger'  => 'bg-red-50 text-red-700 dark:bg-red-500/10 dark:text-red-400',
            'gray'    => 'bg-gray-100 text-gray-600 dark:bg-gray-700/30 dark:text-gray-400',
        ];
    @endphp

    {{-- THEME-AWARE: netral (kartu putih standar); identitas krem bawaan
         dipulihkan blok .tema-quick di theme.css BAWAAN saja. --}}
    <section class="tema-quick rounded-lg bg-white p-5 shadow-sm ring-1 ring-gray-950/5
                    dark:bg-gray-900 dark:ring-white/10">
        <h3 class="mb-4 text-sm font-semibold font-display text-gray-900 dark:text-white">
            🚀 Akses Cepat
        </h3>

        <div class="space-y-5">
            @foreach ($groups as $group)
                <div class="space-y-2.5">
                    <h4 class="text-xs font-semibold font-display uppercase tracking-wide text-gray-700 dark:text-gray-300">
                        {{ $group['label'] }}
                    </h4>
                    <div class="grid grid-cols-4 gap-2.5 sm:grid-cols-6 md:grid-cols-8">
                        @foreach ($group['items'] as $item)
                            @php $c = $chip[$item['color']] ?? $chip['primary']; @endphp
                            <a href="{{ $item['url'] }}"
                               title="{{ $item['label'] }}"
                               class="group flex flex-col items-center gap-2 rounded-lg px-2 py-2.5 text-center
                                      transition-all duration-200 hover:bg-white/60 dark:hover:bg-white/10
                                      active:scale-95">
                                <div class="flex h-11 w-11 items-center justify-center rounded-lg {{ $c }}
                                            shadow-sm transition-all duration-200 group-hover:shadow-md
                                            group-hover:scale-110">
                                    <x-filament::icon :icon="$item['icon']" class="h-5 w-5" />
                                </div>
                                <span class="text-[0.65rem] font-medium leading-tight text-gray-700 dark:text-gray-300
                                           group-hover:text-gray-900 dark:group-hover:text-white">
                                    {{ $item['label'] }}
                                </span>
                            </a>
                        @endforeach
                    </div>
                </div>
            @endforeach
        </div>
    </section>
</x-filament-widgets::widget>
