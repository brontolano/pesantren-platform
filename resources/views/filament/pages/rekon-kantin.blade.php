<x-filament-panels::page>
    <x-filament::section>
        <div class="flex items-end gap-3">
            <div>
                <label class="text-sm font-medium">Tanggal</label>
                <x-filament::input.wrapper class="mt-1">
                    <x-filament::input type="date" wire:model.live="tanggal" />
                </x-filament::input.wrapper>
            </div>
        </div>
    </x-filament::section>

    <x-filament::section heading="Rekap per kasir">
        @php($rows = $this->rekap())
        @if ($rows->isEmpty())
            <div class="py-4 text-sm text-gray-500 dark:text-gray-400">Tidak ada transaksi kantin pada tanggal ini.</div>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="text-left text-gray-500 dark:text-gray-400">
                            <th class="py-2 pr-4 font-medium">Kasir</th>
                            <th class="py-2 pr-4 text-right font-medium">Jml Belanja</th>
                            <th class="py-2 pr-4 text-right font-medium">Total Belanja</th>
                            <th class="py-2 pr-4 text-right font-medium">Jml Refund</th>
                            <th class="py-2 pr-4 text-right font-medium">Total Refund</th>
                            <th class="py-2 text-right font-medium">Netto</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-white/10">
                        @foreach ($rows as $row)
                            <tr>
                                <td class="py-2 pr-4">{{ $row['kasir'] }}</td>
                                <td class="py-2 pr-4 text-right">{{ $row['jumlah_belanja'] }}</td>
                                <td class="py-2 pr-4 text-right">Rp {{ number_format($row['total_belanja'], 0, ',', '.') }}</td>
                                <td class="py-2 pr-4 text-right">{{ $row['jumlah_refund'] }}</td>
                                <td class="py-2 pr-4 text-right">Rp {{ number_format($row['total_refund'], 0, ',', '.') }}</td>
                                <td class="py-2 text-right font-semibold">Rp {{ number_format($row['netto'], 0, ',', '.') }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </x-filament::section>
</x-filament-panels::page>
