<x-filament-widgets::widget>
    <div class="flex flex-col gap-4">
        {{-- ===== Pengumuman Terbaru ===== --}}
        <section class="rounded-xl bg-white p-4 shadow-sm ring-1 ring-gray-950/5
                        dark:bg-[#1C1C24] dark:ring-[#2A2A35]">
            <div class="mb-2.5 flex items-center justify-between">
                <h3 class="text-sm font-semibold text-gray-900 dark:text-white">Pengumuman</h3>
            </div>
            <div class="flex flex-col items-center justify-center gap-2 py-5 text-center">
                <x-filament::icon icon="heroicon-o-megaphone"
                                  class="h-7 w-7 text-gray-300 dark:text-gray-600" />
                <p class="text-xs text-gray-500 dark:text-gray-400">Belum ada pengumuman.</p>
                <p class="text-[0.65rem] text-gray-400 dark:text-gray-500">Modul segera hadir.</p>
            </div>
        </section>

        {{-- ===== Agenda Mendatang ===== --}}
        <section class="rounded-xl bg-white p-4 shadow-sm ring-1 ring-gray-950/5
                        dark:bg-[#1C1C24] dark:ring-[#2A2A35]">
            <div class="mb-2.5 flex items-center justify-between">
                <h3 class="text-sm font-semibold text-gray-900 dark:text-white">Agenda Mendatang</h3>
            </div>

            @if ($agenda->isEmpty())
                <div class="flex flex-col items-center justify-center gap-2 py-5 text-center">
                    <x-filament::icon icon="heroicon-o-calendar-days"
                                      class="h-7 w-7 text-gray-300 dark:text-gray-600" />
                    <p class="text-xs text-gray-500 dark:text-gray-400">Belum ada agenda mendatang.</p>
                </div>
            @else
                <ul class="flex flex-col divide-y divide-gray-100 dark:divide-[#2A2A35]">
                    @foreach ($agenda as $item)
                        <li class="flex items-start gap-3 py-2.5 first:pt-0 last:pb-0">
                            <div class="flex h-10 w-10 shrink-0 flex-col items-center justify-center
                                        rounded-lg bg-green-50 text-green-700
                                        dark:bg-green-500/10 dark:text-green-400">
                                <span class="text-sm font-bold leading-none">
                                    {{ $item->mulai?->format('d') }}
                                </span>
                                <span class="text-[0.55rem] font-semibold uppercase leading-none">
                                    {{ $item->mulai?->translatedFormat('M') }}
                                </span>
                            </div>
                            <div class="min-w-0 flex-1">
                                <p class="truncate text-sm font-medium text-gray-900 dark:text-gray-100">
                                    {{ $item->nama }}
                                </p>
                                <p class="mt-0.5 text-xs text-gray-500 dark:text-gray-400">
                                    {{ $item->mulai?->format('H:i') }} WIB
                                    @if ($item->lokasi)
                                        &middot; {{ $item->lokasi }}
                                    @endif
                                </p>
                            </div>
                        </li>
                    @endforeach
                </ul>
            @endif
        </section>
    </div>
</x-filament-widgets::widget>
