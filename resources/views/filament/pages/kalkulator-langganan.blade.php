<x-filament-panels::page>
    @php($tenant = $this->selectedTenant())
    @if($tenant)
        <div style="display:flex;align-items:flex-start;justify-content:space-between;gap:1rem;border:1px solid rgba(120,120,120,0.25);border-radius:12px;padding:1.25rem 1.5rem;">
            <div>
                <div style="font-size:1.15rem;font-weight:700;">{{ $tenant->name }}</div>
                <div style="margin-top:0.25rem;font-size:0.875rem;opacity:0.65;">
                    Pengaturan langganan &middot; tenant: {{ $tenant->id }} &middot; {{ $tenant->domains->first()?->domain ?? '—' }}
                </div>
            </div>
            @if($tenant->is_active)
                <span style="white-space:nowrap;border-radius:9999px;padding:0.3rem 0.9rem;font-size:0.8rem;font-weight:600;background:rgba(34,197,94,0.15);color:#22c55e;">Aktif</span>
            @else
                <span style="white-space:nowrap;border-radius:9999px;padding:0.3rem 0.9rem;font-size:0.8rem;font-weight:600;background:rgba(239,68,68,0.15);color:#ef4444;">Nonaktif</span>
            @endif
        </div>
    @endif

    <form wire:submit.prevent="save">
        {{ $this->form }}
    </form>

    @php($p = $this->previewData())

    <div style="border:1px solid rgba(120,120,120,0.25);border-radius:12px;padding:1.5rem;background:rgba(120,120,120,0.06);">
        <div style="font-size:0.875rem;opacity:0.65;">
            Pratinjau tagihan
            @if($p['type'] === 'per_santri')
                ({{ $p['count'] }} santri × Rp {{ number_format($p['price'], 0, ',', '.') }}@if($p['discount'] > 0) − {{ rtrim(rtrim(number_format($p['discount'], 2), '0'), '.') }}%@endif)
            @elseif($p['type'] === 'gratis')
                (gratis)
            @else
                (Rp {{ number_format($p['price'], 0, ',', '.') }}@if($p['discount'] > 0) − {{ rtrim(rtrim(number_format($p['discount'], 2), '0'), '.') }}%@endif)
            @endif
        </div>
        <div style="margin-top:0.35rem;font-size:1.875rem;font-weight:800;">
            Rp {{ number_format($p['total'], 0, ',', '.') }}
            <span style="font-size:1rem;font-weight:400;opacity:0.6;">/ {{ $p['cycle'] }}</span>
        </div>
    </div>

    <div style="display:flex;flex-wrap:wrap;justify-content:flex-end;gap:0.75rem;">
        <x-filament::button wire:click="save" color="gray" icon="heroicon-o-check">
            Simpan pengaturan
        </x-filament::button>
        <x-filament::button wire:click="generateInvoice" color="success" icon="heroicon-o-document-text">
            Generate invoice
        </x-filament::button>
    </div>
</x-filament-panels::page>
