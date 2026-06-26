<x-filament-widgets::widget>
    @php
        $chip = [
            'success' => 'bg-green-50 text-green-700 dark:bg-green-500/10 dark:text-green-400',
            'warning' => 'bg-amber-50 text-amber-700 dark:bg-amber-500/10 dark:text-amber-400',
            'info'    => 'bg-sky-50 text-sky-700 dark:bg-sky-500/10 dark:text-sky-400',
            'primary' => 'bg-primary-50 text-primary-700 dark:bg-primary-500/10 dark:text-primary-400',
            'danger'  => 'bg-red-50 text-red-700 dark:bg-red-500/10 dark:text-red-400',
            'gray'    => 'bg-gray-100 text-gray-500 dark:bg-white/5 dark:text-gray-400',
        ];
    @endphp

    <section class="tema-quick rounded-xl bg-white p-4 shadow-sm ring-1 ring-gray-950/5
                    dark:bg-[#1C1C24] dark:ring-white/8">
        <h3 class="mb-4 text-sm font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">
            Akses Cepat
        </h3>

        <div class="space-y-4">
            @foreach ($groups as $group)
                <div>
                    <h4 class="mb-2 text-xs font-medium uppercase tracking-wider text-gray-400 dark:text-gray-500">
                        {{ $group['label'] }}
                    </h4>
                    <div class="grid grid-cols-4 gap-2 sm:grid-cols-7 lg:grid-cols-8">
                        @foreach ($group['items'] as $item)
                            @php $c = $chip[$item['color']] ?? $chip['primary']; @endphp
                            <a href="{{ $item['url'] }}"
                               class="group flex flex-col items-center gap-1.5 rounded-xl px-2 py-2.5 text-center
                                      transition hover:bg-gray-50 dark:hover:bg-white/5">
                                <span class="flex h-10 w-10 items-center justify-center rounded-xl {{ $c }}
                                             transition group-hover:scale-105 group-hover:shadow-sm">
                                    <x-filament::icon :icon="$item['icon']" class="h-5 w-5" />
                                </span>
                                <span class="text-[0.7rem] font-medium leading-tight text-gray-700 dark:text-gray-300">
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
