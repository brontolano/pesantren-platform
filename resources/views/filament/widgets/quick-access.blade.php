<x-filament-widgets::widget>
    @php
        $chip = [
            'success' => 'bg-green-50 text-green-700 dark:bg-green-500/10 dark:text-green-400',
            'warning' => 'bg-amber-50 text-amber-700 dark:bg-amber-500/10 dark:text-amber-400',
            'info'    => 'bg-blue-50 text-blue-700 dark:bg-blue-500/10 dark:text-blue-400',
            'primary' => 'bg-emerald-50 text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-400',
            'danger'  => 'bg-red-50 text-red-700 dark:bg-red-500/10 dark:text-red-400',
            'gray'    => 'bg-gray-100 text-gray-600 dark:bg-gray-700/30 dark:text-gray-400',
        ];
    @endphp

    <section class="tema-quick rounded-lg bg-gradient-to-b from-[#fffaf3] to-white p-5 shadow-sm ring-1 ring-[#efe7da]
                    dark:from-gray-800 dark:to-[#1C1C24] dark:ring-gray-700">
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
