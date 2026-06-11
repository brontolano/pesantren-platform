<x-filament-panels::page>
    @php($santri = $this->santri())

    {{-- Scan NIS --}}
    <x-filament::section heading="Scan Kartu Santri (NIS)">
        <div class="flex items-end gap-3">
            <div class="flex-1">
                <x-filament::input.wrapper>
                    <x-filament::input
                        type="text"
                        wire:model="nis"
                        wire:keydown.enter="cariSantri"
                        placeholder="Scan QR kartu / ketik NIS lalu Enter"
                        autofocus
                    />
                </x-filament::input.wrapper>
            </div>
            <x-filament::button wire:click="cariSantri" icon="heroicon-o-magnifying-glass">Cari</x-filament::button>
            @if ($santri)
                <x-filament::button color="gray" wire:click="batal">Reset</x-filament::button>
            @endif
        </div>
    </x-filament::section>

    @if ($santri)
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
            <x-filament::section>
                <div class="text-sm text-gray-500 dark:text-gray-400">Santri</div>
                <div class="mt-1 text-xl font-bold tracking-tight">{{ $santri->nama }}</div>
                <div class="mt-1 text-xs text-gray-400">NIS {{ $santri->nis }}</div>
            </x-filament::section>
            <x-filament::section>
                <div class="text-sm text-gray-500 dark:text-gray-400">Saldo tabungan</div>
                <div class="mt-1 text-2xl font-bold tracking-tight text-primary-600 dark:text-primary-400">
                    Rp {{ number_format($this->saldo(), 0, ',', '.') }}
                </div>
            </x-filament::section>
        </div>

        <x-filament::section heading="Belanja">
            <form wire:submit="belanja" class="space-y-4">
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                    <div>
                        <label class="text-sm font-medium">Nominal belanja (Rp)</label>
                        <x-filament::input.wrapper class="mt-1">
                            <x-filament::input type="number" min="1" step="1" wire:model="nominal" required />
                        </x-filament::input.wrapper>
                    </div>
                    <div>
                        <label class="text-sm font-medium">Keterangan / item (opsional)</label>
                        <x-filament::input.wrapper class="mt-1">
                            <x-filament::input type="text" wire:model="keterangan" placeholder="cth: nasi + es teh" />
                        </x-filament::input.wrapper>
                    </div>
                    <div>
                        <label class="text-sm font-medium">PIN santri (6 digit)</label>
                        <x-filament::input.wrapper class="mt-1">
                            <x-filament::input type="password" inputmode="numeric" maxlength="6" wire:model="pin" required
                                placeholder="Dimasukkan oleh santri" />
                        </x-filament::input.wrapper>
                    </div>
                </div>
                <div class="flex justify-end">
                    <x-filament::button type="submit" icon="heroicon-o-shopping-cart">Potong Saldo</x-filament::button>
                </div>
            </form>
        </x-filament::section>
    @endif

    {{-- Transaksi hari ini --}}
    <x-filament::section heading="Transaksi kantin saya hari ini">
        @php($items = $this->transaksiHariIni())
        @if ($items->isEmpty())
            <div class="py-4 text-sm text-gray-500 dark:text-gray-400">Belum ada transaksi kantin hari ini.</div>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="text-left text-gray-500 dark:text-gray-400">
                            <th class="py-2 pr-4 font-medium">#</th>
                            <th class="py-2 pr-4 font-medium">Waktu</th>
                            <th class="py-2 pr-4 font-medium">Santri</th>
                            <th class="py-2 pr-4 font-medium">Jenis</th>
                            <th class="py-2 pr-4 text-right font-medium">Nominal</th>
                            <th class="py-2 font-medium">Keterangan</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-white/10">
                        @foreach ($items as $tx)
                            <tr>
                                <td class="py-2 pr-4 text-gray-500">{{ $tx->id }}</td>
                                <td class="py-2 pr-4 whitespace-nowrap text-gray-500">{{ optional($tx->created_at)->format('H:i') }}</td>
                                <td class="py-2 pr-4">{{ $tx->tabungan?->santri?->nama ?? '—' }}</td>
                                <td class="py-2 pr-4">
                                    @if ($tx->tipe === 'belanja_kantin')
                                        <x-filament::badge color="danger">Belanja</x-filament::badge>
                                    @else
                                        <x-filament::badge color="success">Refund</x-filament::badge>
                                    @endif
                                </td>
                                <td class="py-2 pr-4 text-right font-medium whitespace-nowrap">
                                    Rp {{ number_format((float) $tx->nominal, 0, ',', '.') }}
                                </td>
                                <td class="py-2 text-gray-500">{{ $tx->keterangan }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </x-filament::section>
</x-filament-panels::page>
