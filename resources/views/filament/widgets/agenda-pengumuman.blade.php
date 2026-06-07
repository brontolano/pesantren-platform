<x-filament-widgets::widget>
    <div class="flex flex-col gap-6">
        {{-- ===== Pengumuman Terbaru (state kosong jujur) ===== --}}
        <section class="rounded-xl bg-white p-5 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
            <div class="mb-3 flex items-center justify-between">
                <h3 class="text-base font-semibold text-gray-900 dark:text-white">Pengumuman Terbaru</h3>
            </div>
            <div class="flex flex-col items-center justify-center gap-2 py-6 text-center">
                <x-filament::icon icon="heroicon-o-megaphone" class="h-8 w-8 text-gray-300 dark:text-gray-600" />
                <p class="text-sm text-gray-500 dark:text-gray-400">Belum ada pengumuman.</p>
                <p class="text-xs text-gray-400 dark:text-gray-500">Modul pengumuman akan segera hadir.</p>
            </div>
        </section>

        {{-- ===== Agenda Mendatang (Kegiatan) ===== --}}
        <section class="rounded-xl bg-white p-5 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
            <div class="mb-3 flex items-center justify-between">
                <h3 class="text-base font-semibold text-gray-900 dark:text-white">Agenda Mendatang</h3>
            </div>

            @if ($agenda->isEmpty())
                <div class="flex flex-col items-center justify-center gap-2 py-6 text-center">
                    <x-filament::icon icon="heroicon-o-calendar-days" class="h-8 w-8 text-gray-300 dark:text-gray-600" />
                    <p class="text-sm text-gray-500 dark:text-gray-400">Belum ada agenda mendatang.</p>
                </div>
            @else
                <ul class="flex flex-col divide-y divide-gray-100 dark:divide-white/5">
                    @foreach ($agenda as $item)
                        <li class="flex items-start gap-3 py-3 first:pt-0 last:pb-0">
                            <div class="flex h-12 w-12 shrink-0 flex-col items-center justify-center rounded-lg bg-primary-50 text-primary-700 dark:bg-primary-500/10 dark:text-primary-300">
                                <span class="text-base font-bold leading-none">{{ $item->mulai?->format('d') }}</span>
                                <span class="text-[0.625rem] font-medium uppercase leading-none">{{ $item->mulai?->translatedFormat('M') }}</span>
                            </div>
                            <div class="min-w-0 flex-1">
                                <p class="truncate text-sm font-medium text-gray-900 dark:text-gray-100">{{ $item->nama }}</p>
                                <p class="mt-0.5 text-xs text-gray-500 dark:text-gray-400">
                                    {{ $item->mulai?->translatedFormat('l, d M Y') }} &middot; {{ $item->mulai?->format('H:i') }} WIB
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
