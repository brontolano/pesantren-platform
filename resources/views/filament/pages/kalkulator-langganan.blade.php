<x-filament-panels::page>
    <form wire:submit.prevent="save">
        {{ $this->form }}
    </form>

    @php($p = $this->previewData())

    <div class="rounded-xl border border-gray-200 bg-gray-50 p-6 dark:border-white/10 dark:bg-white/5">
        <div class="text-sm text-gray-500 dark:text-gray-400">
            Pratinjau tagihan
            @if($p['type'] === 'per_santri')
                ({{ $p['count'] }} santri × Rp {{ number_format($p['price'], 0, ',', '.') }}@if($p['discount'] > 0) − {{ rtrim(rtrim(number_format($p['discount'], 2), '0'), '.') }}%@endif)
            @elseif($p['type'] === 'gratis')
                (gratis)
            @else
                (Rp {{ number_format($p['price'], 0, ',', '.') }}@if($p['discount'] > 0) − {{ rtrim(rtrim(number_format($p['discount'], 2), '0'), '.') }}%@endif)
            @endif
        </div>
        <div class="mt-1 text-3xl font-bold text-gray-950 dark:text-white">
            Rp {{ number_format($p['total'], 0, ',', '.') }}
            <span class="text-base font-normal text-gray-500 dark:text-gray-400">/ {{ $p['cycle'] }}</span>
        </div>
    </div>

    <div class="flex flex-wrap justify-end gap-3">
        <x-filament::button wire:click="save" color="gray" icon="heroicon-o-check">
            Simpan pengaturan
        </x-filament::button>
        <x-filament::button wire:click="generateInvoice" color="success" icon="heroicon-o-document-text">
            Generate invoice
        </x-filament::button>
    </div>
</x-filament-panels::page>
