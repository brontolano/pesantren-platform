<x-filament-widgets::widget>
    @php
        $chip = [
            'success' => 'bg-green-50 text-green-700 dark:bg-green-500/10 dark:text-green-400',
            'warning' => 'bg-amber-50 text-amber-700 dark:bg-amber-500/10 dark:text-amber-400',
            'info'    => 'bg-sky-50 text-sky-700 dark:bg-sky-500/10 dark:text-sky-400',
            'primary' => 'bg-primary-50 text-primary-700 dark:bg-primary-500/10 dark:text-primary-400',
            'gray'    => 'bg-gray-100 text-gray-400 dark:bg-white/5 dark:text-gray-500',
        ];
    @endphp

    <section class="rounded-xl bg-white p-4 shadow-sm ring-1 ring-gray-950/5
                    dark:bg-[#1C1C24] dark:ring-white/8">
        <h3 class="mb-3 text-sm font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">
            Akses Cepat
        </h3>

        {{-- Grid full-width: 4 kolom mobile → 8 kolom desktop → semua shortcut satu baris --}}
        <div class="grid grid-cols-4 gap-2 sm:grid-cols-8">
            @foreach ($shortcuts as $item)
                @php $c = $chip[$item['color']] ?? $chip['primary']; @endphp

                @if ($item['enabled'])
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
                @else
                    <div class="relative flex cursor-not-allowed flex-col items-center gap-1.5 rounded-xl
                                px-2 py-2.5 text-center opacity-50"
                         title="Segera hadir">
                        <span class="flex h-10 w-10 items-center justify-center rounded-xl {{ $c }}">
                            <x-filament::icon :icon="$item['icon']" class="h-5 w-5" />
                        </span>
                        <span class="text-[0.7rem] font-medium leading-tight text-gray-500 dark:text-gray-400">
                            {{ $item['label'] }}
                        </span>
                        <span class="absolute right-0.5 top-0.5 rounded-full bg-gray-200 px-1 py-px
                                     text-[0.5rem] font-semibold uppercase text-gray-500
                                     dark:bg-white/10 dark:text-gray-400">
                            Segera
                        </span>
                    </div>
                @endif
            @endforeach
        </div>
    </section>
</x-filament-widgets::widget>
