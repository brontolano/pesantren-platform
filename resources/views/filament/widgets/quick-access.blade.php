<x-filament-widgets::widget>
    @php
        $chip = [
            'success' => 'bg-emerald-50 text-emerald-600 dark:bg-emerald-500/10 dark:text-emerald-400',
            'warning' => 'bg-amber-50 text-amber-600 dark:bg-amber-500/10 dark:text-amber-400',
            'info'    => 'bg-sky-50 text-sky-600 dark:bg-sky-500/10 dark:text-sky-400',
            'primary' => 'bg-primary-50 text-primary-600 dark:bg-primary-500/10 dark:text-primary-400',
            'gray'    => 'bg-gray-100 text-gray-400 dark:bg-white/5 dark:text-gray-500',
        ];
    @endphp

    <section class="rounded-xl bg-white p-5 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
        <h3 class="mb-4 text-base font-semibold text-gray-900 dark:text-white">Akses Cepat</h3>

        <div class="grid grid-cols-3 gap-3 sm:grid-cols-4">
            @foreach ($shortcuts as $item)
                @php $c = $chip[$item['color']] ?? $chip['primary']; @endphp

                @if ($item['enabled'])
                    <a href="{{ $item['url'] }}"
                       class="group flex flex-col items-center gap-2 rounded-xl p-3 text-center transition hover:bg-gray-50 dark:hover:bg-white/5">
                        <span class="flex h-11 w-11 items-center justify-center rounded-xl {{ $c }} transition group-hover:scale-105">
                            <x-filament::icon :icon="$item['icon']" class="h-5 w-5" />
                        </span>
                        <span class="text-xs font-medium leading-tight text-gray-700 dark:text-gray-300">{{ $item['label'] }}</span>
                    </a>
                @else
                    <div class="relative flex cursor-not-allowed flex-col items-center gap-2 rounded-xl p-3 text-center opacity-60"
                         title="Segera hadir">
                        <span class="flex h-11 w-11 items-center justify-center rounded-xl {{ $c }}">
                            <x-filament::icon :icon="$item['icon']" class="h-5 w-5" />
                        </span>
                        <span class="text-xs font-medium leading-tight text-gray-500 dark:text-gray-400">{{ $item['label'] }}</span>
                        <span class="absolute right-1 top-1 rounded-full bg-gray-200 px-1.5 py-px text-[0.5rem] font-semibold uppercase text-gray-500 dark:bg-white/10 dark:text-gray-400">Segera</span>
                    </div>
                @endif
            @endforeach
        </div>
    </section>
</x-filament-widgets::widget>
