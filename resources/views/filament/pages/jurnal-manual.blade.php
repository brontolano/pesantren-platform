<x-filament-panels::page>
    <form wire:submit="simpan" class="space-y-6">
        {{ $this->form }}

        <div class="flex justify-end">
            <x-filament::button type="submit" icon="heroicon-o-check">
                Simpan Jurnal
            </x-filament::button>
        </div>
    </form>
</x-filament-panels::page>
