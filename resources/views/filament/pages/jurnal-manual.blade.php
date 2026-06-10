<x-filament-panels::page>
    <form wire:submit="simpan" class="space-y-6">
        {{ $this->form }}

        <div class="flex justify-end">
            {{-- Nonaktifkan tombol saat memproses (anti double-click); idempotency token
                 di backend tetap jaring pengaman utama bila klik ganda lolos. --}}
            <x-filament::button
                type="submit"
                icon="heroicon-o-check"
                wire:loading.attr="disabled"
                wire:target="simpan"
            >
                <span wire:loading.remove wire:target="simpan">Simpan Jurnal</span>
                <span wire:loading wire:target="simpan">Menyimpan…</span>
            </x-filament::button>
        </div>
    </form>
</x-filament-panels::page>
