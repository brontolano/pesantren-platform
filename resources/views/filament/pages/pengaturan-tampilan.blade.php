<x-filament-panels::page>
    <form wire:submit="save" class="space-y-6">
        {{ $this->form }}

        {{-- Pratinjau swatch preset warna --}}
        <div class="flex flex-wrap gap-3">
            @foreach (\App\Support\PanelTheme::COLORS as $key => $w)
                <div class="flex items-center gap-2 rounded-lg border border-gray-200 px-3 py-1.5 dark:border-gray-700">
                    <span class="h-4 w-4 rounded-full ring-1 ring-black/10"
                          style="background: {{ $w['primary'] ?? '#059669' }}"></span>
                    <span class="text-xs text-gray-600 dark:text-gray-300">{{ $w['label'] }}</span>
                </div>
            @endforeach
        </div>

        <div class="flex justify-end">
            <x-filament::button type="submit" icon="heroicon-o-check">
                Simpan Tampilan
            </x-filament::button>
        </div>
    </form>
</x-filament-panels::page>
