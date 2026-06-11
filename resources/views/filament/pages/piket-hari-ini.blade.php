<x-filament-panels::page>
    <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
        @forelse ($this->ringkasan as $baris)
            @php
                $terakhir = $baris['ceklis']->first();
                $warna = match ($terakhir?->status) {
                    'bersih' => 'text-success-600',
                    'kotor' => 'text-warning-600',
                    'rusak' => 'text-danger-600',
                    default => 'text-gray-400',
                };
            @endphp
            <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-700 dark:bg-gray-900">
                <div class="flex items-center justify-between">
                    <h3 class="font-semibold">{{ $baris['area']->nama }}</h3>
                    <span class="text-sm font-bold uppercase {{ $warna }}">
                        {{ $terakhir?->status ?? 'belum dicek' }}
                    </span>
                </div>
                @if ($terakhir)
                    <p class="mt-1 text-sm text-gray-500">
                        {{ $terakhir->jam->format('H:i') }} — {{ $terakhir->petugas?->name ?? '—' }}
                        @if ($terakhir->catatan)
                            <br><span class="italic">{{ $terakhir->catatan }}</span>
                        @endif
                    </p>
                @else
                    <p class="mt-1 text-sm text-gray-400">Belum ada scan piket hari ini.</p>
                @endif
            </div>
        @empty
            <p class="text-gray-500">Belum ada area piket. Tambahkan lewat menu Area Piket.</p>
        @endforelse
    </div>
</x-filament-panels::page>
