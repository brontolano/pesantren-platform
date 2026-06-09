<x-filament-panels::page>
    <div class="grid md:grid-cols-2 gap-4">
        @foreach ($this->laporanTersedia() as $item)
            <a href="{{ $item['page']::getUrl() }}" class="rounded-lg border p-4 hover:bg-gray-50 dark:hover:bg-gray-800 transition">
                <div class="font-semibold">{{ $item['label'] }}</div>
                <div class="text-sm text-gray-500">{{ $item['desc'] }}</div>
            </a>
        @endforeach
    </div>
</x-filament-panels::page>
